<?php

declare(strict_types=1);

class Seat
{
    public static function findByEvent(int $eventId): array
    {
        return Database::fetchAll(
            'SELECT s.*, sc.name as category_name, sc.price, sc.color
             FROM seats s
             JOIN seat_categories sc ON s.category_id = sc.id
             WHERE s.event_id = :eid
             ORDER BY sc.sort_order, s.section, s.row_label, s.seat_number',
            [':eid' => $eventId]
        );
    }

    public static function findByEventGrouped(int $eventId): array
    {
        $seats = self::findByEvent($eventId);
        $grouped = [];
        foreach ($seats as $seat) {
            $catName = $seat['category_name'];
            if (!isset($grouped[$catName])) {
                $grouped[$catName] = [
                    'category_name' => $catName,
                    'price' => (float) $seat['price'],
                    'color' => $seat['color'],
                    'sections' => [],
                ];
            }
            $section = $seat['section'];
            if (!isset($grouped[$catName]['sections'][$section])) {
                $grouped[$catName]['sections'][$section] = [];
            }
            $row = $seat['row_label'];
            if (!isset($grouped[$catName]['sections'][$section][$row])) {
                $grouped[$catName]['sections'][$section][$row] = [];
            }
            $grouped[$catName]['sections'][$section][$row][] = $seat;
        }
        return $grouped;
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT s.*, sc.name as category_name, sc.price, sc.color
             FROM seats s
             JOIN seat_categories sc ON s.category_id = sc.id
             WHERE s.id = :id',
            [':id' => $id]
        );
    }

    public static function findMany(array $ids): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Database::fetchAll(
            "SELECT s.*, sc.name as category_name, sc.price, sc.color
             FROM seats s
             JOIN seat_categories sc ON s.category_id = sc.id
             WHERE s.id IN ($placeholders)",
            $ids
        );
    }

    public static function lock(int $seatId, string $sessionId): bool
    {
        // Release expired locks first
        self::releaseExpiredLocks();

        $seat = self::find($seatId);
        if ($seat === null) return false;
        if ($seat['status'] === 'sold') return false;

        // If already locked by another session, fail
        if ($seat['status'] === 'locked' && $seat['locked_by_session'] !== $sessionId) {
            // Check if lock expired
            if (self::isLockExpired($seat)) {
                // Take over the lock
                return self::doLock($seatId, $sessionId);
            }
            return false;
        }

        return self::doLock($seatId, $sessionId);
    }

    private static function doLock(int $seatId, string $sessionId): bool
    {
        $lockUntil = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        Database::query(
            "UPDATE seats SET status = 'locked', locked_by_session = :sid, locked_at = datetime('now'), locked_until = :until WHERE id = :id AND status = 'available'",
            [':sid' => $sessionId, ':until' => $lockUntil, ':id' => $seatId]
        );
        return Database::pdo()->query("SELECT changes()")->fetchColumn() > 0;
    }

    public static function unlock(int $seatId, string $sessionId): bool
    {
        Database::query(
            "UPDATE seats SET status = 'available', locked_by_session = NULL, locked_at = NULL, locked_until = NULL
             WHERE id = :id AND locked_by_session = :sid",
            [':id' => $seatId, ':sid' => $sessionId]
        );
        return Database::pdo()->query("SELECT changes()")->fetchColumn() > 0;
    }

    public static function unlockAllBySession(string $sessionId): void
    {
        Database::query(
            "UPDATE seats SET status = 'available', locked_by_session = NULL, locked_at = NULL, locked_until = NULL
             WHERE locked_by_session = :sid",
            [':sid' => $sessionId]
        );
    }

    public static function releaseExpiredLocks(): void
    {
        Database::query(
            "UPDATE seats SET status = 'available', locked_by_session = NULL, locked_at = NULL, locked_until = NULL
             WHERE status = 'locked' AND locked_until IS NOT NULL AND locked_until < datetime('now')"
        );
    }

    private static function isLockExpired(array $seat): bool
    {
        if ($seat['locked_until'] === null) return true;
        return strtotime($seat['locked_until']) < time();
    }

    public static function markAsSold(array $seatIds): bool
    {
        if (empty($seatIds)) return false;
        $placeholders = implode(',', array_fill(0, count($seatIds), '?'));
        Database::query(
            "UPDATE seats SET status = 'sold', locked_by_session = NULL, locked_at = NULL, locked_until = NULL
             WHERE id IN ($placeholders)",
            $seatIds
        );
        return true;
    }

    public static function seatLabel(array $seat): string
    {
        return $seat['section'] . ' · Rang ' . $seat['row_label'] . ' · Siège ' . $seat['seat_number'];
    }

    public static function lockedBySession(string $sessionId): array
    {
        self::releaseExpiredLocks();
        return Database::fetchAll(
            'SELECT s.*, sc.name as category_name, sc.price, sc.color
             FROM seats s
             JOIN seat_categories sc ON s.category_id = sc.id
             WHERE s.locked_by_session = :sid
             ORDER BY sc.sort_order, s.section, s.row_label, s.seat_number',
            [':sid' => $sessionId]
        );
    }
}
