<?php

declare(strict_types=1);

namespace App\Controllers;

class AuthController
{
    public function showLogin(): void
    {
        if (\Auth::check()) {
            redirect(url('account'));
        }
        layout('auth/login', ['pageTitle' => 'Connexion']);
    }

    public function processLogin(): void
    {
        if (!verify_csrf()) {
            flash('error', 'Token de sécurité invalide.');
            redirect(url('login'));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('error', 'Email et mot de passe requis.');
            redirect(url('login'));
        }

        $user = \User::verify($email, $password);
        if (!$user) {
            flash('error', 'Identifiants incorrects.');
            redirect(url('login'));
        }

        \Auth::login($user);
        flash('success', 'Bienvenue, ' . $user['first_name'] . ' !');
        redirect(url('account'));
    }

    public function showRegister(): void
    {
        if (\Auth::check()) {
            redirect(url('account'));
        }
        layout('auth/register', ['pageTitle' => 'Créer un compte']);
    }

    public function processRegister(): void
    {
        if (!verify_csrf()) {
            flash('error', 'Token de sécurité invalide.');
            redirect(url('register'));
        }

        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($firstName === '' || $lastName === '') {
            flash('error', 'Nom et prénom requis.');
            redirect(url('register'));
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Adresse email invalide.');
            redirect(url('register'));
        }
        if (strlen($password) < 6) {
            flash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            redirect(url('register'));
        }
        if ($password !== $passwordConfirm) {
            flash('error', 'Les mots de passe ne correspondent pas.');
            redirect(url('register'));
        }

        if (\User::findByEmail($email)) {
            flash('error', 'Un compte existe déjà avec cette adresse email.');
            redirect(url('register'));
        }

        $userId = \User::create($email, $password, $firstName, $lastName, $phone ?: null);
        $user = \User::find($userId);
        \Auth::login($user);
        flash('success', 'Compte créé avec succès. Bienvenue, ' . $firstName . ' !');
        redirect(url('account'));
    }

    public function logout(): void
    {
        \Auth::logout();
        flash('success', 'Vous êtes déconnecté.');
        redirect(url(''));
    }
}
