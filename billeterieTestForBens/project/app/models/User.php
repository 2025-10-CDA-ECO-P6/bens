<?php

declare(strict_types=1);

class User
{
    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE email = :email', [':email' => $email]);
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = :id', [':id' => $id]);
    }

    public static function create(string $email, string $password, string $firstName, string $lastName, ?string $phone = null): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Database::query(
            'INSERT INTO users (email, password_hash, first_name, last_name, phone) VALUES (:email, :hash, :fname, :lname, :phone)',
            [
                ':email' => $email,
                ':hash' => $hash,
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':phone' => $phone,
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function verify(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if ($user === null) return null;
        if (!password_verify($password, $user['password_hash'])) return null;
        return $user;
    }
}
