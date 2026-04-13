<?php
// ============================================================
//  api/auth/refresh.php  —  Renovar access token
//  POST { refresh_token: "..." }
// ============================================================
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../lib/JWT.php';
require_once __DIR__ . '/../../lib/Security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

$input        = json_decode(file_get_contents('php://input'), true);
$refreshToken = $input['refresh_token'] ?? '';

if (!$refreshToken) {
    http_response_code(400);
    echo json_encode(['error' => 'refresh_token requerido']); exit;
}

$payload = JWT::verify($refreshToken, true);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Refresh token inválido o expirado']); exit;
}

$userId      = (int)$payload['sub'];
$sessionUuid = $payload['session_uuid'];

$stmt = $pdo->prepare(
    "SELECT id, refresh_token_hash FROM sesiones
     WHERE session_uuid=? AND usuario_id=? AND activa=1
     AND (expira_en IS NULL OR expira_en>NOW())"
);
$stmt->execute([$sessionUuid, $userId]);
$sesion = $stmt->fetch();

if (!$sesion || !password_verify($refreshToken, $sesion['refresh_token_hash'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Sesión inválida']); exit;
}

$user = $pdo->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id=? AND estado='activo'");
$user->execute([$userId]);
$user = $user->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario no encontrado']); exit;
}

// Rotar refresh token
$newRefresh  = JWT::generateRefresh($userId, $sessionUuid);
$newHash     = password_hash($newRefresh, PASSWORD_BCRYPT);
$nuevaExpira = date('Y-m-d H:i:s', time() + 604800);

$pdo->prepare(
    "UPDATE sesiones SET refresh_token_hash=?, ultima_actividad=NOW(), expira_en=? WHERE id=?"
)->execute([$newHash, $nuevaExpira, $sesion['id']]);

$accessToken = JWT::generate([
    'sub'          => $userId,
    'email'        => $user['email'],
    'nombre'       => $user['nombre'],
    'role'         => $user['rol'],
    'session_uuid' => $sessionUuid,
]);

echo json_encode([
    'access_token'  => $accessToken,
    'refresh_token' => $newRefresh,
    'expires_in'    => 900,
]);