<?php

declare(strict_types=1);

namespace App\Controllers;

class InventoryController
{
    /**
     * GET /event/{slug}/buy — quantity selector for a quota (large venue) event.
     */
    public function showBuy(array $params): void
    {
        $slug = $params['slug'];
        $event = \Event::findBySlug($slug);
        if (!$event) {
            http_response_code(404);
            layout('errors/404', ['pageTitle' => 'Événement non trouvé']);
            return;
        }

        // Sales not open yet → block access to the purchase page.
        if (!\Event::isSaleOpen($event['sale_open_at'])) {
            flash('error', 'La vente n\'est pas encore ouverte pour cet événement.');
            redirect(url('event/' . $event['slug']));
            return;
        }

        $categories = \Event::categories((int) $event['id']);
        // Keep only quota categories
        $quotaCats = array_values(array_filter(
            $categories,
            fn($c) => $c['quota_total'] !== null
        ));

        if (empty($quotaCats)) {
            // Not a quota event — redirect to the seat map.
            redirect(url('event/' . $event['slug'] . '/seats'));
            return;
        }

        $stats = \Event::stats((int) $event['id']);
        $sessionId = session_id();
        $cart = \Inventory::lockedBySession($sessionId);

        layout('event/buy', [
            'pageTitle' => 'Acheter mes billets — ' . $event['title'],
            'event' => $event,
            'categories' => $quotaCats,
            'stats' => $stats,
            'cart' => $cart,
        ]);
    }

    /**
     * POST /api/inventory/lock  { category_id, quantity }
     */
    public function lock(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $categoryId = (int) ($input['category_id'] ?? 0);
        $quantity = (int) ($input['quantity'] ?? 0);
        $sessionId = session_id();

        if ($categoryId === 0 || $quantity <= 0) {
            json_response(['success' => false, 'error' => 'category_id et quantity requis'], 400);
        }

        // Defense in depth: refuse to lock if the event sale is not open.
        $cat = \Inventory::category($categoryId);
        if ($cat) {
            $event = \Event::find((int) $cat['event_id']);
            if ($event && !\Event::isSaleOpen($event['sale_open_at'])) {
                json_response(['success' => false, 'error' => 'La vente n\'est pas encore ouverte', 'code' => 'SALE_NOT_OPEN'], 403);
            }
        }

        $success = \Inventory::lock($categoryId, $quantity, $sessionId);

        if ($success) {
            $cat = \Inventory::category($categoryId);
            json_response([
                'success' => true,
                'message' => $quantity . ' billet(s) réservé(s) — TTL 10 minutes',
                'item' => [
                    'category_id' => $categoryId,
                    'category_name' => $cat['name'],
                    'unit_price' => (float) $cat['price'],
                    'color' => $cat['color'],
                    'quantity' => $quantity,
                ],
                'available' => \Inventory::available($categoryId),
                'ttl' => 600,
            ]);
        } else {
            json_response([
                'success' => false,
                'error' => 'Pas assez de billets disponibles pour cette catégorie',
                'code' => 'OUT_OF_STOCK',
            ], 409);
        }
    }

    /**
     * POST /api/inventory/unlock  { category_id, quantity? }
     * If quantity omitted, release everything held for that category.
     */
    public function unlock(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $categoryId = (int) ($input['category_id'] ?? 0);
        $quantity = (int) ($input['quantity'] ?? 0);
        $sessionId = session_id();

        if ($categoryId > 0) {
            \Inventory::unlock($categoryId, $quantity, $sessionId);
        } else {
            \Inventory::unlockAllBySession($sessionId);
        }

        json_response(['success' => true]);
    }

    /**
     * GET /api/inventory/status/{eventId} — live availability per category.
     */
    public function status(array $params): void
    {
        $eventId = (int) $params['eventId'];
        json_response([
            'success' => true,
            'availability' => \Inventory::availabilityForEvent($eventId),
        ]);
    }
}
