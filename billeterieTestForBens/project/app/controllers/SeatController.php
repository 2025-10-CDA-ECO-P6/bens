<?php

declare(strict_types=1);

namespace App\Controllers;

class SeatController
{
    public function showMap(array $params): void
    {
        $slug = $params['slug'];
        $event = \Event::findBySlug($slug);
        if (!$event) {
            http_response_code(404);
            layout('errors/404', ['pageTitle' => 'Événement non trouvé']);
            return;
        }

        $categories = \Event::categories((int) $event['id']);
        $grouped = \Seat::findByEventGrouped((int) $event['id']);
        $stats = \Event::stats((int) $event['id']);
        $sessionId = session_id();
        $lockedSeats = \Seat::lockedBySession($sessionId);

        layout('seats/map', [
            'pageTitle' => 'Plan des sièges — ' . $event['title'],
            'event' => $event,
            'categories' => $categories,
            'grouped' => $grouped,
            'stats' => $stats,
            'sessionId' => $sessionId,
            'lockedSeats' => $lockedSeats,
        ]);
    }

    public function lockSeats(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $seatId = (int) ($input['seat_id'] ?? 0);
        $sessionId = session_id();

        if ($seatId === 0) {
            json_response(['success' => false, 'error' => 'Seat ID required'], 400);
        }

        \Seat::releaseExpiredLocks();
        $success = \Seat::lock($seatId, $sessionId);

        if ($success) {
            $seat = \Seat::find($seatId);
            json_response([
                'success' => true,
                'message' => 'Siège verrouillé — TTL 10 minutes',
                'seat' => [
                    'id' => $seat['id'],
                    'label' => \Seat::seatLabel($seat),
                    'category' => $seat['category_name'],
                    'price' => (float) $seat['price'],
                    'color' => $seat['color'],
                ],
                'ttl' => 600,
            ]);
        } else {
            json_response([
                'success' => false,
                'error' => 'Siège déjà réservé',
                'code' => 'SEAT_ALREADY_LOCKED',
            ], 409);
        }
    }

    public function unlockSeats(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $seatId = (int) ($input['seat_id'] ?? 0);
        $sessionId = session_id();

        if ($seatId > 0) {
            \Seat::unlock($seatId, $sessionId);
        } else {
            \Seat::unlockAllBySession($sessionId);
        }

        json_response(['success' => true]);
    }

    public function getSeatStatus(array $params): void
    {
        $eventId = (int) $params['eventId'];
        \Seat::releaseExpiredLocks();
        $seats = \Seat::findByEvent($eventId);

        $statusMap = [];
        foreach ($seats as $seat) {
            $statusMap[$seat['id']] = $seat['status'];
        }

        json_response(['success' => true, 'seats' => $statusMap]);
    }
}
