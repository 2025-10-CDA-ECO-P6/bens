<?php

declare(strict_types=1);

namespace App\Controllers;

class HomeController
{
    public function index(): void
    {
        $events = \Event::upcoming();
        $featured = $events[0] ?? null;
        $others = array_slice($events, 1);

        layout('home/index', [
            'pageTitle' => 'PulseTicket — Billetterie en ligne · Festivals, Concerts & Sport',
            'featured' => $featured,
            'events' => $others,
            'allEvents' => $events,
        ]);
    }
}
