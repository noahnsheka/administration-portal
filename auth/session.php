<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function destroyCurrentSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function redirectToLogin(): void
{
    destroyCurrentSession();
    header('Location: ../index.php');
    exit;
}

function redirectToSetup(): void
{
    destroyCurrentSession();
    header('Location: ../setup.php');
    exit;
}

function requireRole(string $role): void
{
    if (!administration_runtime_config_exists()) {
        redirectToSetup();
    }

    $currentUser = $_SESSION['user'] ?? null;

    if (!$currentUser || ($currentUser['role'] ?? '') !== $role) {
        redirectToLogin();
    }

    $statement = getDatabaseConnection()->prepare('SELECT id, full_name, account_number, role, is_active FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => (int) ($currentUser['id'] ?? 0)]);
    $databaseUser = $statement->fetch();

    if (!$databaseUser || ($databaseUser['role'] ?? '') !== $role || (int) ($databaseUser['is_active'] ?? 0) !== 1) {
        redirectToLogin();
    }

    $_SESSION['user'] = [
        'id' => (int) $databaseUser['id'],
        'account_number' => (string) $databaseUser['account_number'],
        'full_name' => (string) $databaseUser['full_name'],
        'role' => (string) $databaseUser['role'],
    ];
}
