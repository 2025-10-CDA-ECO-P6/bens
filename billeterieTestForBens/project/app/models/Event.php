<?php

declare(strict_types=1);

class Event
{
    public static function all(): array
    {
        return Database::fetchAll('SELECT * FROM events ORDER BY event_date ASC');
    }

    public static function upcoming(): array
    {
        return Database::fetchAll(
            "SELECT * FROM events WHERE status = 'upcoming' AND event_date >= date('now') ORDER BY event_date ASC"
        );
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::fetch('SELECT * FROM events WHERE slug = :slug', [':slug' => $slug]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM events WHERE id = :id', [':id' => $id]);
    }

    public static function categories(int $eventId): array
    {
        return Database::fetchAll(
            'SELECT * FROM seat_categories WHERE event_id = :eid ORDER BY sort_order ASC',
            [':eid' => $eventId]
        );
    }

    public static function stats(int $eventId): array
    {
        // Seated categories (rows in `seats`)
        $total = (int) Database::fetch('SELECT COUNT(*) as c FROM seats WHERE event_id = :eid', [':eid' => $eventId])['c'];
        $sold = (int) Database::fetch("SELECT COUNT(*) as c FROM seats WHERE event_id = :eid AND status = 'sold'", [':eid' => $eventId])['c'];
        $locked = (int) Database::fetch("SELECT COUNT(*) as c FROM seats WHERE event_id = :eid AND status = 'locked'", [':eid' => $eventId])['c'];

        // Quota categories (large venues / fosse — no individual seats)
        $quotaRows = Database::fetchAll(
            'SELECT quota_total, quota_sold, quota_locked FROM seat_categories WHERE event_id = :eid AND quota_total IS NOT NULL',
            [':eid' => $eventId]
        );
        foreach ($quotaRows as $q) {
            $total  += (int) $q['quota_total'];
            $sold   += (int) $q['quota_sold'];
            $locked += (int) $q['quota_locked'];
        }

        $available = $total - $sold - $locked;
        return [
            'total' => $total,
            'sold' => $sold,
            'locked' => $locked,
            'available' => $available,
            'percent' => $total > 0 ? round(($sold / $total) * 100) : 0,
        ];
    }

    /**
     * True when the event sells by quota (large venue) rather than numbered seats.
     */
    public static function isQuotaEvent(int $eventId): bool
    {
        $row = Database::fetch(
            'SELECT 1 FROM seat_categories WHERE event_id = :eid AND quota_total IS NOT NULL LIMIT 1',
            [':eid' => $eventId]
        );
        return $row !== null;
    }

    /**
     * True when sales are open for this event right now.
     */
    public static function isSaleOpen(?string $saleOpenAt): bool
    {
        if ($saleOpenAt === null || $saleOpenAt === '') return true;
        return strtotime($saleOpenAt) <= time();
    }
}
