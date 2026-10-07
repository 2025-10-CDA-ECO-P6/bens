<?php

declare(strict_types=1);

/**
 * Front controller / router for the PHP built-in server.
 *
 * Serve the app with:
 *   php -S localhost:8000 project/public/index.php
 *
 * When run under the built-in server (CLI_SERVER), we first check whether the
 * request maps to an existing static file inside public/. If it does, we
 * `return false;` so the built-in server streams the file directly. Otherwise
 * we fall through to the application router.
 */

if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($reqPath !== '/' && $reqPath !== false) {
        $candidate = __DIR__ . $reqPath;
        // Resolve and guard against path traversal outside public/
        $real = realpath($candidate);
        if ($real !== false && is_file($real) && str_starts_with($real, __DIR__)) {
            // Serve the static asset directly so the server works even when
            // launched without `-t` (docroot defaults to the working dir).
            $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'css'   => 'text/css; charset=utf-8',
                'js'    => 'application/javascript; charset=utf-8',
                'svg'   => 'image/svg+xml',
                'png'   => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'   => 'image/gif',
                'webp'  => 'image/webp',
                'ico'   => 'image/x-icon',
                'woff2' => 'font/woff2',
                'woff'  => 'font/woff',
                default => (mime_content_type($real) ?: 'application/octet-stream'),
            };
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($real));
            readfile($real);
            return; // script ends → response is sent; server does not fall back
        }
    }
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\HomeController;
use App\Controllers\EventController;
use App\Controllers\SeatController;
use App\Controllers\InventoryController;
use App\Controllers\CheckoutController;
use App\Controllers\AccountController;
use App\Controllers\AuthController;

$router = new Router();

// Home
$router->get('/', [HomeController::class, 'index']);

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'processLogin']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'processRegister']);
$router->get('/logout', [AuthController::class, 'logout']);

// Events
$router->get('/events', [EventController::class, 'index']);
$router->get('/event/{slug}', [EventController::class, 'show']);

// Seats (numbered-seat events)
$router->get('/event/{slug}/seats', [SeatController::class, 'showMap']);
$router->post('/api/seats/lock', [SeatController::class, 'lockSeats']);
$router->post('/api/seats/unlock', [SeatController::class, 'unlockSeats']);
$router->get('/api/seats/status/{eventId}', [SeatController::class, 'getSeatStatus']);

// Inventory (quota / large-venue events, e.g. 50 000-seat stadium)
$router->get('/event/{slug}/buy', [InventoryController::class, 'showBuy']);
$router->post('/api/inventory/lock', [InventoryController::class, 'lock']);
$router->post('/api/inventory/unlock', [InventoryController::class, 'unlock']);
$router->get('/api/inventory/status/{eventId}', [InventoryController::class, 'status']);

// Checkout
$router->get('/checkout', [CheckoutController::class, 'showCheckout']);
$router->post('/checkout/process', [CheckoutController::class, 'processPayment']);
$router->get('/confirmation/{reference}', [CheckoutController::class, 'confirmation']);

// Account
$router->get('/account', [AccountController::class, 'index']);
$router->get('/account/order/{id}', [AccountController::class, 'orderDetail']);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

$router->dispatch($method, $path);
