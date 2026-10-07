<?php

declare(strict_types=1);

namespace App\Controllers;

class CheckoutController
{
    public function showCheckout(): void
    {
        $sessionId = session_id();
        $lockedSeats = \Seat::lockedBySession($sessionId);
        $quotaItems  = \Inventory::lockedBySession($sessionId);

        if (empty($lockedSeats) && empty($quotaItems)) {
            flash('error', 'Aucun billet sélectionné. Veuillez choisir vos billets.');
            redirect(url('events'));
        }

        // Determine the event from whichever cart is populated.
        $eventId = !empty($lockedSeats)
            ? (int) $lockedSeats[0]['event_id']
            : (int) $quotaItems[0]['event_id'];
        $event = \Event::find($eventId);

        $total = 0.0;
        foreach ($lockedSeats as $seat) $total += (float) $seat['price'];
        foreach ($quotaItems as $item)  $total += (float) $item['unit_price'] * (int) $item['quantity'];

        layout('checkout/index', [
            'pageTitle' => 'Paiement — ' . $event['title'],
            'event' => $event,
            'seats' => $lockedSeats,
            'quotaItems' => $quotaItems,
            'total' => $total,
            'maxTickets' => (int) $event['max_tickets_per_user'],
        ]);
    }

    public function processPayment(): void
    {
        if (!verify_csrf()) {
            json_response(['success' => false, 'error' => 'Invalid CSRF token'], 403);
        }

        $sessionId = session_id();
        $lockedSeats = \Seat::lockedBySession($sessionId);
        $quotaItems  = \Inventory::lockedBySession($sessionId);

        if (empty($lockedSeats) && empty($quotaItems)) {
            flash('error', 'Votre sélection a expiré. Veuillez recommencer.');
            redirect(url('events'));
        }

        $eventId = !empty($lockedSeats)
            ? (int) $lockedSeats[0]['event_id']
            : (int) $quotaItems[0]['event_id'];
        $event = \Event::find($eventId);
        $maxTickets = (int) $event['max_tickets_per_user'];

        $ticketCount = count($lockedSeats);
        foreach ($quotaItems as $item) $ticketCount += (int) $item['quantity'];
        if ($ticketCount > $maxTickets) {
            flash('error', "Maximum {$maxTickets} billets par personne pour cet événement.");
            redirect(url('checkout'));
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $cardNumber = trim((string) ($_POST['card_number'] ?? ''));
        $cardExpiry = trim((string) ($_POST['card_expiry'] ?? ''));
        $cardCvc = trim((string) ($_POST['card_cvc'] ?? ''));

        $errors = [];
        if ($firstName === '') $errors[] = 'Prénom requis';
        if ($lastName === '') $errors[] = 'Nom requis';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (strlen(str_replace(' ', '', $cardNumber)) < 13) $errors[] = 'Numéro de carte invalide';
        if (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) $errors[] = 'Date d\'expiration invalide';
        if (strlen($cardCvc) < 3) $errors[] = 'CVC invalide';

        if (!empty($errors)) {
            foreach (['first_name', 'last_name', 'email', 'phone'] as $field) {
                Session::setOldInput($field, $$field);
            }
            flash('error', implode(' · ', $errors));
            redirect(url('checkout'));
        }

        $total = 0.0;
        foreach ($lockedSeats as $seat) $total += (float) $seat['price'];
        foreach ($quotaItems as $item)  $total += (float) $item['unit_price'] * (int) $item['quantity'];

        $orderId = \Order::create([
            'user_id' => \Auth::id(),
            'event_id' => (int) $event['id'],
            'customer_email' => $email,
            'customer_first_name' => $firstName,
            'customer_last_name' => $lastName,
            'customer_phone' => $phone,
            'total_amount' => $total,
            'payment_method' => 'card',
        ]);

        if (!empty($lockedSeats)) \Order::addItems($orderId, $lockedSeats);
        if (!empty($quotaItems))  \Order::addQuotaItems($orderId, $quotaItems);

        // Simulate payment processing (classic Web2 PSP call).
        usleep(500000);
        $paymentId = 'pay_' . bin2hex(random_bytes(12));

        // Commit quota counters (locked → sold) before generating tickets.
        if (!empty($quotaItems)) {
            \Inventory::commitBySession($sessionId);
        }

        \Order::markPaid($orderId, 'card', $paymentId);

        // Release any remaining seat locks for this session.
        \Seat::unlockAllBySession($sessionId);

        $order = \Order::find($orderId);
        redirect(url('confirmation/' . $order['order_reference']));
    }

    public function confirmation(array $params): void
    {
        $ref = $params['reference'];
        $order = \Order::findByReference($ref);
        if (!$order) {
            http_response_code(404);
            layout('errors/404', ['pageTitle' => 'Commande non trouvée']);
            return;
        }

        $items = \Order::items((int) $order['id']);
        $tickets = \Order::tickets((int) $order['id']);
        $event = \Event::find((int) $order['event_id']);

        layout('checkout/confirmation', [
            'pageTitle' => 'Confirmation — ' . $order['order_reference'],
            'order' => $order,
            'items' => $items,
            'tickets' => $tickets,
            'event' => $event,
        ]);
    }
}
