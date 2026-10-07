<?php

declare(strict_types=1);

/**
 * Quota-based inventory for large venues (e.g. 50 000-seat stadium, fosse).
 * Opposed to the per-seat `Seat` model, here we only manipulate counters:
 *
 *   available = quota_total - quota_sold - quota_locked
 *
 * Locking is an atomic conditional UPDATE on the category counter. The
 * session holds the user's cart:
 *   $_SESSION['quota_cart'] = [categoryId => quantity, ...]
 */
class Inventory
{
    public static function category(int $categoryId): ?array
    {
        return Database::fetch(
            'SELECT * FROM seat_categories WHERE id = :id',
            [':id' => $categoryId]
        );
    }

    public static function available(int $categoryId): int
    {
        $cat = self::category($categoryId);
        if (!$cat || $cat['quota_total'] === null) return 0;
        return (int) $cat['quota_total'] - (int) $cat['quota_sold'] - (int) $cat['quota_locked'];
    }

    /**
     * Atomically reserve $quantity units from a category for this session.
     * Returns true on success, false if not enough availability.
     */
    public static function lock(int $categoryId, int $quantity, string $sessionId): bool
    {
        if ($quantity <= 0) return false;
        $cat = self::category($categoryId);
        if (!$cat || $cat['quota_total'] === null) return false;

        Database::pdo()->beginTransaction();
        try {
            // Atomic conditional decrement — only succeeds if enough is available.
            Database::query(
                "UPDATE seat_categories
                 SET quota_locked = quota_locked + :q
                 WHERE id = :id
                   AND (quota_total - quota_sold - quota_locked) >= :q",
                [':q' => $quantity, ':id' => $categoryId]
            );
            $changed = (int) Database::pdo()->query("SELECT changes()")->fetchColumn();
            Database::pdo()->commit();
        } catch (Throwable $e) {
            Database::pdo()->rollBack();
            return false;
        }

        if ($changed === 0) return false;

        $cart = Session::get('quota_cart', []);
        $cart[$categoryId] = ($cart[$categoryId] ?? 0) + $quantity;
        Session::set('quota_cart', $cart);
        return true;
    }

    /**
     * Release $quantity units of a category held by this session.
     * If $quantity is 0, release the whole amount held for that category.
     */
    public static function unlock(int $categoryId, int $quantity, string $sessionId): bool
    {
        $cart = Session::get('quota_cart', []);
        $held = $cart[$categoryId] ?? 0;
        if ($held <= 0) return false;
        $release = $quantity > 0 ? min($quantity, $held) : $held;

        Database::query(
            "UPDATE seat_categories
             SET quota_locked = MAX(0, quota_locked - :q)
             WHERE id = :id",
            [':q' => $release, ':id' => $categoryId]
        );

        $remaining = $held - $release;
        if ($remaining > 0) {
            $cart[$categoryId] = $remaining;
        } else {
            unset($cart[$categoryId]);
        }
        Session::set('quota_cart', $cart);
        return true;
    }

    /**
     * Release every quota lock held by this session.
     */
    public static function unlockAllBySession(string $sessionId): void
    {
        $cart = Session::get('quota_cart', []);
        foreach ($cart as $categoryId => $qty) {
            Database::query(
                "UPDATE seat_categories SET quota_locked = MAX(0, quota_locked - :q) WHERE id = :id",
                [':q' => $qty, ':id' => $categoryId]
            );
        }
        Session::remove('quota_cart');
    }

    /**
     * Move locked → sold for everything in the session cart. Called after a
     * successful payment. Returns the list of items (for order_items creation).
     */
    public static function commitBySession(string $sessionId): array
    {
        $cart = Session::get('quota_cart', []);
        $items = [];
        foreach ($cart as $categoryId => $qty) {
            $cat = self::category((int) $categoryId);
            if (!$cat) continue;
            Database::query(
                "UPDATE seat_categories
                 SET quota_sold = quota_sold + :q,
                     quota_locked = MAX(0, quota_locked - :q)
                 WHERE id = :id",
                [':q' => $qty, ':id' => $categoryId]
            );
            $items[] = [
                'category_id' => (int) $categoryId,
                'category_name' => $cat['name'],
                'unit_price' => (float) $cat['price'],
                'color' => $cat['color'],
                'quantity' => (int) $qty,
                'seat_label' => $cat['name'] . ' × ' . $qty,
            ];
        }
        Session::remove('quota_cart');
        return $items;
    }

    /**
     * Read the session cart joined with category info (for checkout display).
     */
    public static function lockedBySession(string $sessionId): array
    {
        $cart = Session::get('quota_cart', []);
        $items = [];
        foreach ($cart as $categoryId => $qty) {
            $cat = self::category((int) $categoryId);
            if (!$cat) continue;
            $items[] = [
                'category_id' => (int) $categoryId,
                'category_name' => $cat['name'],
                'unit_price' => (float) $cat['price'],
                'color' => $cat['color'],
                'quantity' => (int) $qty,
                'seat_label' => $cat['name'] . ' × ' . $qty,
                'event_id' => (int) $cat['event_id'],
            ];
        }
        return $items;
    }

    /**
     * Live availability map for an event: [categoryId => available].
     */
    public static function availabilityForEvent(int $eventId): array
    {
        $rows = Database::fetchAll(
            'SELECT id, quota_total, quota_sold, quota_locked FROM seat_categories WHERE event_id = :eid AND quota_total IS NOT NULL',
            [':eid' => $eventId]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['id']] = (int) $r['quota_total'] - (int) $r['quota_sold'] - (int) $r['quota_locked'];
        }
        return $map;
    }
}
