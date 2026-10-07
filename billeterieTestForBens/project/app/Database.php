<?php

declare(strict_types=1);

class Database
{
    private static ?PDO $pdo = null;

    public static function init(): void
    {
        if (self::$pdo !== null) return;

        // Ensure the database directory exists so SQLite can create the file.
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0775, true);
        }

        $isNew = !file_exists(DB_PATH);
        self::$pdo = new PDO('sqlite:' . DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        if ($isNew) {
            self::migrate();
            self::seed();
        }
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::init();
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function lastInsertId(): string
    {
        return self::pdo()->lastInsertId();
    }

    private static function migrate(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    subtitle TEXT,
    description TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'festival',
    venue TEXT NOT NULL,
    city TEXT NOT NULL,
    event_date TEXT NOT NULL,
    door_time TEXT NOT NULL,
    show_time TEXT NOT NULL,
    image_url TEXT NOT NULL,
    gallery_image_1 TEXT,
    gallery_image_2 TEXT,
    gallery_image_3 TEXT,
    status TEXT NOT NULL DEFAULT 'upcoming',
    -- When ticket sales open (local datetime, e.g. '2026-09-05 10:00:00').
    -- NULL = sales already open.
    sale_open_at TEXT,
    max_tickets_per_user INTEGER NOT NULL DEFAULT 4,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS seat_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    price REAL NOT NULL,
    color TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    -- Quota-based categories (large venues / fosse): no individual seats.
    -- NULL  = seated category (rows come from the `seats` table).
    -- INTEGER = total inventory for this category (e.g. 38000 for a tribune).
    quota_total INTEGER,
    quota_sold INTEGER NOT NULL DEFAULT 0,
    quota_locked INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS seats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    section TEXT NOT NULL,
    row_label TEXT NOT NULL,
    seat_number INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'available',
    locked_by_session TEXT,
    locked_at TEXT,
    locked_until TEXT,
    UNIQUE(event_id, section, row_label, seat_number),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES seat_categories(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_reference TEXT NOT NULL UNIQUE,
    user_id INTEGER,
    event_id INTEGER NOT NULL,
    customer_email TEXT NOT NULL,
    customer_first_name TEXT NOT NULL,
    customer_last_name TEXT NOT NULL,
    customer_phone TEXT,
    total_amount REAL NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    payment_status TEXT NOT NULL DEFAULT 'pending',
    payment_method TEXT,
    payment_id TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS order_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    seat_id INTEGER,
    category_name TEXT NOT NULL,
    seat_label TEXT NOT NULL,
    unit_price REAL NOT NULL,
    quantity INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats(id)
);

CREATE TABLE IF NOT EXISTS tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL,
    seat_id INTEGER,
    event_id INTEGER NOT NULL,
    ticket_code TEXT NOT NULL UNIQUE,
    qr_data TEXT NOT NULL,
    seat_label TEXT,
    category_name TEXT,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
);

CREATE INDEX IF NOT EXISTS idx_seats_event ON seats(event_id);
CREATE INDEX IF NOT EXISTS idx_seats_status ON seats(status);
CREATE INDEX IF NOT EXISTS idx_orders_event ON orders(event_id);
CREATE INDEX IF NOT EXISTS idx_tickets_order ON tickets(order_id);
SQL;
        self::$pdo->exec($sql);
    }

    private static function seed(): void
    {
        $events = [
            [
                'slug' => 'eclipse-world-tour-lyon',
                'title' => 'ECLIPSE — World Tour 2026',
                'subtitle' => 'Le concert le plus attendu de la décennie · Vente imminente',
                'description' => "Après trois ans d'attente, ECLIPSE revient sur scène pour une tournée mondiale historique. Une seule date en France, dans le plus grand stade de la région, devant plus de 50 000 fans.\n\nLa demande est exceptionnelle : on s'attend à un afflux massif de connexions au moment de l'ouverture des ventes. Pour garantir une expérience fluide et équitable, la billetterie verrouille chaque sélection pendant 10 minutes le temps de finaliser le paiement, puis libère les places non réglées pour les suivants dans la file d'attente.\n\nRéservation rapide, paiement sécurisé et QR code de contrôle à l'entrée : tout est pensé pour que vous viviez le concert sans souci.",
                'category' => 'rush',
                'venue' => 'Groupama Stadium',
                'city' => 'Décines-Charpieu (Lyon)',
                'event_date' => '2026-09-05',
                'door_time' => '18:30',
                'show_time' => '20:45',
                'image_url' => 'https://images.pexels.com/photos/2747449/pexels-photo-2747449.jpeg?auto=compress&cs=tinysrgb&w=1920',
                'gallery_image_1' => 'https://images.pexels.com/photos/1763075/pexels-photo-1763075.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_2' => 'https://images.pexels.com/photos/2027959/pexels-photo-2027959.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_3' => 'https://images.pexels.com/photos/13830385/pexels-photo-13830385.jpeg?auto=compress&cs=tinysrgb&w=940',
                'status' => 'upcoming',
                'sale_open_at' => '2026-09-05 10:00:00',
                'max_tickets_per_user' => 2,
            ],
            [
                'slug' => 'neon-pulse-festival-2026',
                'title' => 'Neon Pulse Festival 2026',
                'subtitle' => '3 jours · 40 artistes · 1 expérience inoubliable',
                'description' => "Le Neon Pulse Festival revient pour sa 4ᵉ édition avec une affiche explosive. Trois jours de musique électronique, de live acts et de performances visuelles au cœur d'un site entièrement repensé.\n\nCette année, le festival accueille des têtes d'affiche internationales alongside les talents émergents les plus passionnants de la scène française. Entre les scènes Main Stage, Techno Dome et Silent Disco, chaque heure offre une nouvelle découverte.\n\nLe village festival propose food trucks, bars, zones chill, installations artistiques immersives et un espace VR. Réservation rapide, paiement sécurisé et QR code de contrôle à l'entrée.",
                'category' => 'festival',
                'venue' => 'Parc des Expositions',
                'city' => 'Lyon',
                'event_date' => '2026-12-19',
                'door_time' => '14:00',
                'show_time' => '15:30',
                'image_url' => 'https://images.pexels.com/photos/7513442/pexels-photo-7513442.jpeg?auto=compress&cs=tinysrgb&w=1920',
                'gallery_image_1' => 'https://images.pexels.com/photos/6782327/pexels-photo-6782327.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_2' => 'https://images.pexels.com/photos/13230484/pexels-photo-13230484.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_3' => 'https://images.pexels.com/photos/26978133/pexels-photo-26978133.jpeg?auto=compress&cs=tinysrgb&w=940',
                'status' => 'upcoming',
                'max_tickets_per_user' => 4,
            ],
            [
                'slug' => 'inferno-metal-night',
                'title' => 'Inferno Metal Night',
                'subtitle' => 'Les légendes du metal européen réunies pour une nuit',
                'description' => "Une nuit de métal intense avec quatre groupes d'exception sur la scène du Transbordeur. Du thrash old-school au death metal moderne, l'affiche promet un mur de son et un mosh-pit inoubliable.\n\nPlaces assises et fosse debout disponibles. Réservation rapide, paiement sécurisé et QR code de contrôle à l'entrée.",
                'category' => 'concert',
                'venue' => 'Le Transbordeur',
                'city' => 'Villeurbanne',
                'event_date' => '2026-10-31',
                'door_time' => '19:00',
                'show_time' => '20:00',
                'image_url' => 'https://images.pexels.com/photos/4218027/pexels-photo-4218027.jpeg?auto=compress&cs=tinysrgb&w=1920',
                'gallery_image_1' => 'https://images.pexels.com/photos/1613240/pexels-photo-1613240.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_2' => 'https://images.pexels.com/photos/8186275/pexels-photo-8186275.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_3' => 'https://images.pexels.com/photos/7715347/pexels-photo-7715347.jpeg?auto=compress&cs=tinysrgb&w=940',
                'status' => 'upcoming',
                'max_tickets_per_user' => 6,
            ],
            [
                'slug' => 'olympique-derby-stade',
                'title' => 'Olympique Derby — Stade de Lumières',
                'subtitle' => 'Le choc de la saison · Billetterie officielle',
                'description' => "Le derby le plus attendu de la saison au Stade de Lumières. Plus de 50 000 supporters attendus pour un spectacle sportif d'exception.\n\nTribune Nord, Tribune Sud, Virage Populaire et Loges VIP disponibles. Réservation rapide, paiement sécurisé et QR code de contrôle à l'entrée.",
                'category' => 'sport',
                'venue' => 'Stade de Lumières',
                'city' => 'Lyon',
                'event_date' => '2026-11-21',
                'door_time' => '18:30',
                'show_time' => '20:45',
                'image_url' => 'https://images.pexels.com/photos/13830814/pexels-photo-13830814.jpeg?auto=compress&cs=tinysrgb&w=1920',
                'gallery_image_1' => 'https://images.pexels.com/photos/24524185/pexels-photo-24524185.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_2' => 'https://images.pexels.com/photos/35898730/pexels-photo-35898730.jpeg?auto=compress&cs=tinysrgb&w=940',
                'gallery_image_3' => 'https://images.pexels.com/photos/31331114/pexels-photo-31331114.jpeg?auto=compress&cs=tinysrgb&w=940',
                'status' => 'upcoming',
                'max_tickets_per_user' => 4,
            ],
        ];

        foreach ($events as $eventData) {
            self::seedEvent($eventData);
        }
    }

    private static function seedEvent(array $data): void
    {
        $sql = 'INSERT INTO events (slug, title, subtitle, description, category, venue, city, event_date, door_time, show_time, image_url, gallery_image_1, gallery_image_2, gallery_image_3, status, sale_open_at, max_tickets_per_user)
                VALUES (:slug, :title, :subtitle, :description, :category, :venue, :city, :event_date, :door_time, :show_time, :image_url, :g1, :g2, :g3, :status, :sale_open_at, :max_tickets)';
        self::query($sql, [
            ':slug' => $data['slug'], ':title' => $data['title'], ':subtitle' => $data['subtitle'],
            ':description' => $data['description'], ':category' => $data['category'],
            ':venue' => $data['venue'], ':city' => $data['city'],
            ':event_date' => $data['event_date'], ':door_time' => $data['door_time'],
            ':show_time' => $data['show_time'], ':image_url' => $data['image_url'],
            ':g1' => $data['gallery_image_1'], ':g2' => $data['gallery_image_2'],
            ':g3' => $data['gallery_image_3'], ':status' => $data['status'],
            ':sale_open_at' => $data['sale_open_at'] ?? null,
            ':max_tickets' => $data['max_tickets_per_user'],
        ]);
        $eventId = (int) self::lastInsertId();

        $categories = match ($data['category']) {
            'festival' => [
                ['name' => 'PASS 3 JOURS - FOSSE', 'price' => 189.00, 'color' => '#f97316', 'sort_order' => 0],
                ['name' => 'PASS 3 JOURS - TRIBUNE', 'price' => 145.00, 'color' => '#06b6d4', 'sort_order' => 1],
                ['name' => 'PASS 3 JOURS - PREMIUM', 'price' => 299.00, 'color' => '#eab308', 'sort_order' => 2],
            ],
            'concert' => [
                ['name' => 'FOSSE DEBOUT', 'price' => 45.00, 'color' => '#f97316', 'sort_order' => 0],
                ['name' => 'TRIBUNE ASSISE', 'price' => 65.00, 'color' => '#06b6d4', 'sort_order' => 1],
                ['name' => 'TRIBUNE OR', 'price' => 85.00, 'color' => '#eab308', 'sort_order' => 2],
            ],
            'sport' => [
                ['name' => 'VIRAGE POPULAIRE', 'price' => 25.00, 'color' => '#f97316', 'sort_order' => 0],
                ['name' => 'TRIBUNE NORD/SUD', 'price' => 45.00, 'color' => '#06b6d4', 'sort_order' => 1],
                ['name' => 'LOGE VIP', 'price' => 120.00, 'color' => '#eab308', 'sort_order' => 2],
            ],
            'rush' => [
                ['name' => 'PIT GOLDEN', 'price' => 350.00, 'color' => '#ef4444', 'sort_order' => 0, 'quota_total' => 2000],
                ['name' => 'FOSSE', 'price' => 120.00, 'color' => '#f97316', 'sort_order' => 1, 'quota_total' => 10000],
                ['name' => 'TRIBUNE', 'price' => 75.00, 'color' => '#06b6d4', 'sort_order' => 2, 'quota_total' => 38000],
            ],
        };

        foreach ($categories as $cat) {
            $quotaTotal = $cat['quota_total'] ?? null;
            self::query(
                'INSERT INTO seat_categories (event_id, name, price, color, sort_order, quota_total) VALUES (:eid, :name, :price, :color, :sort, :quota)',
                [
                    ':eid' => $eventId, ':name' => $cat['name'], ':price' => $cat['price'],
                    ':color' => $cat['color'], ':sort' => $cat['sort_order'],
                    ':quota' => $quotaTotal,
                ]
            );
            $catId = (int) self::lastInsertId();
            // Quota categories have no individual seats — skip seat generation.
            if ($quotaTotal === null) {
                self::seedSeats($eventId, $catId, $cat['name'], $data['category']);
            }
        }
    }

    private static function seedSeats(int $eventId, int $catId, string $catName, string $category): void
    {
        $layouts = [
            'festival' => [
                'PASS 3 JOURS - FOSSE' => [['section' => 'FOSSSE A', 'rows' => ['A', 'B', 'C', 'D', 'E'], 'seatsPerRow' => 14]],
                'PASS 3 JOURS - TRIBUNE' => [['section' => 'TRIBUNE C', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'seatsPerRow' => 12]],
                'PASS 3 JOURS - PREMIUM' => [['section' => 'PREMIUM P', 'rows' => ['A', 'B', 'C'], 'seatsPerRow' => 8]],
            ],
            'concert' => [
                'FOSSE DEBOUT' => [['section' => 'FOSSE', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'seatsPerRow' => 10]],
                'TRIBUNE ASSISE' => [['section' => 'TRIBUNE T', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F'], 'seatsPerRow' => 12]],
                'TRIBUNE OR' => [['section' => 'TRIBUNE OR', 'rows' => ['A', 'B', 'C'], 'seatsPerRow' => 8]],
            ],
            'sport' => [
                'VIRAGE POPULAIRE' => [['section' => 'VIRAGE V', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'], 'seatsPerRow' => 16]],
                'TRIBUNE NORD/SUD' => [['section' => 'TRIBUNE N', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'seatsPerRow' => 14]],
                'LOGE VIP' => [['section' => 'LOGE L', 'rows' => ['A', 'B'], 'seatsPerRow' => 8]],
            ],
            'rush' => [
                'PIT GOLDEN' => [['section' => 'PIT G', 'rows' => ['A', 'B', 'C', 'D'], 'seatsPerRow' => 12]],
                'FOSSE' => [['section' => 'FOSSE F', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'seatsPerRow' => 14]],
                'TRIBUNE' => [['section' => 'TRIBUNE T', 'rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'], 'seatsPerRow' => 16]],
            ],
        ];

        $layout = $layouts[$category][$catName] ?? [];
        $sql = 'INSERT INTO seats (event_id, category_id, section, row_label, seat_number, status) VALUES (:eid, :cid, :section, :row, :num, :status)';
        $randomlySold = 0;
        // Rush sale events start fully available (sale opens fresh).
        $preSoldRate = ($category === 'rush') ? 0 : 30;

        foreach ($layout as $block) {
            foreach ($block['rows'] as $rowLabel) {
                for ($i = 1; $i <= $block['seatsPerRow']; $i++) {
                    $status = 'available';
                    if ($preSoldRate > 0 && mt_rand(1, 100) <= $preSoldRate) {
                        $status = 'sold';
                        $randomlySold++;
                    }
                    self::query($sql, [
                        ':eid' => $eventId, ':cid' => $catId,
                        ':section' => $block['section'], ':row' => $rowLabel,
                        ':num' => $i, ':status' => $status,
                    ]);
                }
            }
        }
    }
}
