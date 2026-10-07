<?php

declare(strict_types=1);

class Order
{
    public static function create(array $data): int
    {
        $ref = self::generateReference();
        Database::query(
            'INSERT INTO orders (order_reference, user_id, event_id, customer_email, customer_first_name, customer_last_name, customer_phone, total_amount, status, payment_status, payment_method)
             VALUES (:ref, :uid, :eid, :email, :fname, :lname, :phone, :total, :status, :pstatus, :pmethod)',
            [
                ':ref' => $ref,
                ':uid' => $data['user_id'] ?? null,
                ':eid' => $data['event_id'],
                ':email' => $data['customer_email'],
                ':fname' => $data['customer_first_name'],
                ':lname' => $data['customer_last_name'],
                ':phone' => $data['customer_phone'] ?? null,
                ':total' => $data['total_amount'],
                ':status' => 'pending',
                ':pstatus' => 'pending',
                ':pmethod' => $data['payment_method'] ?? 'card',
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function addItems(int $orderId, array $seats): void
    {
        foreach ($seats as $seat) {
            Database::query(
                'INSERT INTO order_items (order_id, seat_id, category_name, seat_label, unit_price, quantity)
                 VALUES (:oid, :sid, :cname, :slabel, :price, 1)',
                [
                    ':oid' => $orderId,
                    ':sid' => (int) $seat['id'],
                    ':cname' => $seat['category_name'],
                    ':slabel' => Seat::seatLabel($seat),
                    ':price' => (float) $seat['price'],
                ]
            );
        }
    }

    /**
     * Quota items (large venue / fosse): no seat_id, a quantity per category.
     * Each item: {category_name, unit_price, quantity, seat_label}
     */
    public static function addQuotaItems(int $orderId, array $items): void
    {
        foreach ($items as $item) {
            Database::query(
                'INSERT INTO order_items (order_id, seat_id, category_name, seat_label, unit_price, quantity)
                 VALUES (:oid, NULL, :cname, :slabel, :price, :qty)',
                [
                    ':oid' => $orderId,
                    ':cname' => $item['category_name'],
                    ':slabel' => $item['seat_label'],
                    ':price' => (float) $item['unit_price'],
                    ':qty' => (int) $item['quantity'],
                ]
            );
        }
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM orders WHERE id = :id', [':id' => $id]);
    }

    public static function findByReference(string $ref): ?array
    {
        return Database::fetch('SELECT * FROM orders WHERE order_reference = :ref', [':ref' => $ref]);
    }

    public static function items(int $orderId): array
    {
        return Database::fetchAll('SELECT * FROM order_items WHERE order_id = :oid', [':oid' => $orderId]);
    }

    public static function tickets(int $orderId): array
    {
        return Database::fetchAll(
            'SELECT t.*, e.title as event_title, e.venue, e.city, e.event_date, e.show_time, e.door_time
             FROM tickets t
             JOIN events e ON t.event_id = e.id
             WHERE t.order_id = :oid
             ORDER BY t.id',
            [':oid' => $orderId]
        );
    }

    public static function markPaid(int $orderId, string $paymentMethod = 'card', string $paymentId = ''): void
    {
        Database::query(
            "UPDATE orders SET status = 'confirmed', payment_status = 'paid', payment_method = :pmethod, payment_id = :pid WHERE id = :id",
            [':pmethod' => $paymentMethod, ':pid' => $paymentId, ':id' => $orderId]
        );

        $order = self::find($orderId);
        $eventId = (int) $order['event_id'];
        $orderRef = $order['order_reference'];
        $items = self::items($orderId);
        $seatIds = [];

        foreach ($items as $item) {
            $isQuota = $item['seat_id'] === null;
            $count = $isQuota ? max(1, (int) $item['quantity']) : 1;

            for ($k = 0; $k < $count; $k++) {
                $ticketCode = self::generateTicketCode();
                $seatLabel = $isQuota
                    ? $item['category_name'] . ($count > 1 ? ' · #' . ($k + 1) : '')
                    : $item['seat_label'];
                $qrData = json_encode([
                    'ticket' => $ticketCode,
                    'order' => $orderRef,
                    'seat' => $seatLabel,
                    'category' => $item['category_name'],
                    'event_id' => $eventId,
                    'issued' => date('c'),
                ]);

                Database::query(
                    'INSERT INTO tickets (order_id, seat_id, event_id, ticket_code, qr_data, seat_label, category_name, status)
                     VALUES (:oid, :sid, :eid, :code, :qr, :slabel, :cname, :status)',
                    [
                        ':oid' => $orderId,
                        ':sid' => $item['seat_id'] !== null ? (int) $item['seat_id'] : null,
                        ':eid' => $eventId,
                        ':code' => $ticketCode,
                        ':qr' => $qrData,
                        ':slabel' => $seatLabel,
                        ':cname' => $item['category_name'],
                        ':status' => 'active',
                    ]
                );
            }

            if (!$isQuota) {
                $seatIds[] = (int) $item['seat_id'];
            }
        }

        // Mark numbered seats as sold (quota counters are handled separately
        // by Inventory::commitBySession, called by the controller).
        if (!empty($seatIds)) {
            Seat::markAsSold($seatIds);
        }
    }

    public static function byUser(int $userId): array
    {
        return Database::fetchAll(
            'SELECT o.*, e.title as event_title, e.event_date, e.image_url
             FROM orders o
             JOIN events e ON o.event_id = e.id
             WHERE o.user_id = :uid
             ORDER BY o.created_at DESC',
            [':uid' => $userId]
        );
    }

    public static function cancel(int $orderId): void
    {
        // Release locked seats
        $items = self::items($orderId);
        $seatIds = array_map(fn($i) => (int) $i['seat_id'], $items);
        foreach ($seatIds as $sid) {
            Database::query(
                "UPDATE seats SET status = 'available', locked_by_session = NULL, locked_at = NULL, locked_until = NULL WHERE id = :id",
                [':id' => $sid]
            );
        }
        Database::query("UPDATE orders SET status = 'cancelled' WHERE id = :id", [':id' => $orderId]);
    }

    private static function generateReference(): string
    {
        return 'BNS-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private static function generateTicketCode(): string
    {
        return 'TKT-' . strtoupper(bin2hex(random_bytes(8)));
    }
}
