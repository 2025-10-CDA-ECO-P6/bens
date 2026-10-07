<?php

declare(strict_types=1);

class Auth
{
    public static function login(array $user): void
    {
        Session::set('user_id', (int) $user['id']);
        Session::set('user_email', $user['email']);
        Session::set('user_name', $user['first_name'] . ' ' . $user['last_name']);
    }

    public static function logout(): void
    {
        Session::remove('user_id');
        Session::remove('user_email');
        Session::remove('user_name');
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function name(): string
    {
        return (string) Session::get('user_name', '');
    }

    public static function email(): string
    {
        return (string) Session::get('user_email', '');
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        return [
            'id' => self::id(),
            'email' => self::email(),
            'name' => self::name(),
        ];
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Vous devez être connecté pour accéder à cette page.');
            redirect(url('login'));
        }
    }
}
