<?php
// ============================================================
//  auth/logout.php  —  Cierre de sesión completo
//  Revoca JWT + desactiva sesión en BD + destruye sesión PHP
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Security.php';

$userId      = $_SESSION['user_id']      ?? null;
$accessToken = $_SESSION['access_token'] ?? '';
$sessionUuid = $_SESSION['session_uuid'] ?? '';

// Revocar access token en blacklist
if ($accessToken) {
    JWT::revoke($accessToken, $pdo);
}

// Desactivar sesión en BD
if ($sessionUuid) {
    $pdo->prepare("UPDATE sesiones SET activa=0 WHERE session_uuid=?")
        ->execute([$sessionUuid]);
}

if ($userId) {
    Security::log($pdo, $userId, 'logout',
        Security::getClientIp(), $_SERVER['HTTP_USER_AGENT'] ?? '');
}

// Destruir sesión PHP (igual que tu logout original)
$_SESSION = [];
session_destroy();

if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

header("Location: login.php?logout=1");
exit();