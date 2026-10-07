<?php

declare(strict_types=1);

namespace App\Controllers;

class EventController
{
    public function index(): void
    {
        $events = \Event::upcoming();
        layout('event/index', [
            'pageTitle' => 'Tous les événements',
            'events' => $events,
        ]);
    }

    public function show(array $params): void
    {
        $slug = $params['slug'];
        $event = \Event::findBySlug($slug);
        if (!$event) {
            http_response_code(404);
            layout('errors/404', ['pageTitle' => 'Événement non trouvé']);
            return;
        }

        $categories = \Event::categories((int) $event['id']);
        $stats = \Event::stats((int) $event['id']);
        $saleOpen = \Event::isSaleOpen($event['sale_open_at']);

        layout('event/show', [
            'pageTitle' => $event['title'],
            'event' => $event,
            'categories' => $categories,
            'stats' => $stats,
            'saleOpen' => $saleOpen,
        ]);
    }
}
