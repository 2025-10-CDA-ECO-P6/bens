<?php

declare(strict_types=1);

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) return;
        session_name('ticketing_sid');
        session_start();
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function getCsrfToken(): string
    {
        $token = self::get('_csrf');
        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            self::set('_csrf', $token);
        }
        return (string) $token;
    }

    public static function verifyCsrfToken(string $token): bool
    {
        return hash_equals((string) self::get('_csrf'), $token);
    }

    public static function setFlash(string $key, string $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key): ?string
    {
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value !== null ? (string) $value : null;
    }

    public static function setOldInput(string $key, string $value): void
    {
        $_SESSION['_old'][$key] = $value;
    }

    public static function getOldInput(string $key, ?string $default = null): ?string
    {
        $value = $_SESSION['_old'][$key] ?? null;
        unset($_SESSION['_old'][$key]);
        return $value !== null ? (string) $value : $default;
    }

    public static function clearOldInput(): void
    {
        unset($_SESSION['_old']);
    }
}
