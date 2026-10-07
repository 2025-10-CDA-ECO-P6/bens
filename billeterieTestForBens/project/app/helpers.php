<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function view(string $template, array $data = []): string
{
    $file = APP_PATH . "/views/{$template}.php";
    if (!file_exists($file)) {
        throw new RuntimeException("View not found: {$template}");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

function layout(string $template, array $data = []): void
{
    $content = view($template, $data);
    $data['content'] = $content;
    echo view('layout/main', $data);
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function format_price(float $price): string
{
    return number_format($price, 2, ',', ' ') . ' €';
}

function format_date(string $date): string
{
    $dt = new DateTime($date);
    $months = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];
    $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    return $days[(int) $dt->format('w')] . ' ' . (int) $dt->format('j') . ' ' . $months[(int) $dt->format('n') - 1] . ' ' . $dt->format('Y');
}

function format_time(string $time): string
{
    return substr($time, 0, 5);
}

function csrf_token(): string
{
    return Session::getCsrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['_csrf'] ?? '';
    return Session::verifyCsrfToken((string) $token);
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        Session::setFlash($key, $value);
        return null;
    }
    return Session::getFlash($key);
}

function old(string $key, ?string $default = null): ?string
{
    return Session::getOldInput($key, $default);
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
