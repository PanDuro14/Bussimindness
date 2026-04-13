<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function require_login(string $redirect = '/auth/login.php'): void
{
    if (empty($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header("Location: $redirect");
        exit();
    }
}

function require_admin(string $redirect = '/index.php?error=403'): void
{
    require_login();
    if (($_SESSION['user_rol'] ?? '') !== 'admin') {
        header("Location: $redirect");
        exit();
    }
}

function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function is_admin(): bool     { return is_logged_in() && ($_SESSION['user_rol'] ?? '') === 'admin'; }
function current_user_id(): ?int   { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
function current_user_nombre(): string { return $_SESSION['user_nombre'] ?? 'Invitado'; }

require_login();