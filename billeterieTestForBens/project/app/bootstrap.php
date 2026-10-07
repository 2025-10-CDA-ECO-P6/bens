<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('PUBLIC_PATH', APP_ROOT . '/public');
define('DB_PATH', APP_ROOT . '/database/ticketing.db');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    // Last segment = file name (PascalCase, kept as-is).
    // Directory segments are stored lowercase on disk (app/controllers),
    // so we lowercase them to match the namespace App\Controllers.
    $fileName = array_pop($parts) . '.php';
    $dirPath = APP_PATH;
    foreach ($parts as $segment) {
        $dirPath .= '/' . strtolower($segment);
    }
    $file = $dirPath . '/' . $fileName;
    if (file_exists($file)) {
        require $file;
    }
});

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/Database.php';
require_once APP_PATH . '/Router.php';
require_once APP_PATH . '/Session.php';
require_once APP_PATH . '/Auth.php';
require_once APP_PATH . '/models/Event.php';
require_once APP_PATH . '/models/Seat.php';
require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Inventory.php';

Session::start();
Database::init();
