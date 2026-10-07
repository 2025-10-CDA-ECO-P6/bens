<?php

declare(strict_types=1);

namespace App\Controllers;

class AccountController
{
    public function index(): void
    {
        \Auth::requireLogin();
        $userId = \Auth::id();
        $orders = \Order::byUser($userId);

        // Collect all tickets across orders
        $allTickets = [];
        foreach ($orders as $order) {
            if ($order['status'] === 'confirmed') {
                $tickets = \Order::tickets((int) $order['id']);
                foreach ($tickets as $ticket) {
                    $allTickets[] = $ticket;
                }
            }
        }

        layout('account/index', [
            'pageTitle' => 'Mon compte',
            'orders' => $orders,
            'tickets' => $allTickets,
        ]);
    }

    public function orderDetail(array $params): void
    {
        \Auth::requireLogin();
        $orderId = (int) $params['id'];
        $order = \Order::find($orderId);

        if (!$order || ((int) $order['user_id']) !== \Auth::id()) {
            http_response_code(404);
            layout('errors/404', ['pageTitle' => 'Commande non trouvée']);
            return;
        }

        $items = \Order::items($orderId);
        $tickets = \Order::tickets($orderId);
        $event = \Event::find((int) $order['event_id']);

        layout('account/order', [
            'pageTitle' => 'Commande ' . $order['order_reference'],
            'order' => $order,
            'items' => $items,
            'tickets' => $tickets,
            'event' => $event,
        ]);
    }
}
