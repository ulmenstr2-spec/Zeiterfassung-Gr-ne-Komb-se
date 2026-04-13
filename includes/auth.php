<?php
require_once dirname(__DIR__) . '/config.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure',   '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime',  '28800'); // 8 Stunden
        ini_set('session.cookie_lifetime', '28800');
        session_name('zk_sess');
        session_start();
    }
}

function requireLogin(): void
{
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('Zugriff verweigert.');
    }
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserName(): string
{
    return $_SESSION['user_name'] ?? '';
}

function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

function generateCsrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    startSecureSession();
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
