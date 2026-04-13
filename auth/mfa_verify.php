<?php
// ============================================================
//  auth/mfa_verify.php  —  Verificación de OTP
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Security.php';

$mensaje      = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mfaToken = trim($_POST['mfa_token'] ?? '');
    $otp      = trim($_POST['otp'] ?? '');
    $ip       = Security::getClientIp();
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $payload = JWT::verify($mfaToken);

    if (!$payload || ($payload['purpose'] ?? '') !== 'mfa_pending') {
        $mensaje = " Sesión MFA expirada. <a href='login.php'>Inicia sesión de nuevo</a>.";
        $tipo_mensaje = "error";

    } else {
        $userId = (int)$payload['sub'];

        $stmt = $pdo->prepare(
            "SELECT id, codigo_hash, intentos FROM codigos_mfa
             WHERE usuario_id=? AND tipo='email' AND usado=0 AND expira_en>NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $record = $stmt->fetch();

        if (!$record) {
            $mensaje = " Código OTP expirado. <a href='login.php'>Vuelve a iniciar sesión</a>.";
            $tipo_mensaje = "error";

        } elseif ($record['intentos'] >= 5) {
            $pdo->prepare("UPDATE codigos_mfa SET usado=1 WHERE id=?")->execute([$record['id']]);
            Security::log($pdo, $userId, 'mfa_bloqueado', $ip, $ua);
            $mensaje = " Demasiados intentos. <a href='login.php'>Inicia sesión de nuevo</a>.";
            $tipo_mensaje = "error";

        } elseif (!password_verify($otp, $record['codigo_hash'])) {
            $pdo->prepare("UPDATE codigos_mfa SET intentos=intentos+1 WHERE id=?")->execute([$record['id']]);
            Security::log($pdo, $userId, 'mfa_fallido', $ip, $ua);
            $restantes = 5 - ($record['intentos'] + 1);
            $mensaje   = " Código incorrecto. Intentos restantes: $restantes";
            $tipo_mensaje = "error";

        } else {
            // ── OTP correcto: crear sesión completa ────────
            $pdo->prepare("UPDATE codigos_mfa SET usado=1 WHERE id=?")->execute([$record['id']]);

            $user = $pdo->prepare("SELECT id, nombre, email, rol FROM usuarios WHERE id=?");
            $user->execute([$userId]);
            $user = $user->fetch();

            $sessionUuid  = bin2hex(random_bytes(32));
            $refreshToken = JWT::generateRefresh($userId, $sessionUuid);
            $refreshHash  = password_hash($refreshToken, PASSWORD_BCRYPT);
            $expiraEn     = date('Y-m-d H:i:s', time() + 604800);

            $pdo->prepare(
                "INSERT INTO sesiones (session_uuid, usuario_id, refresh_token_hash, ip, user_agent, expira_en)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$sessionUuid, $userId, $refreshHash, $ip, $ua, $expiraEn]);

            $accessToken = JWT::generate([
                'sub'          => $userId,
                'email'        => $user['email'],
                'nombre'       => $user['nombre'],
                'role'         => $user['rol'],
                'session_uuid' => $sessionUuid,
            ]);

            $_SESSION['user_id']       = $userId;
            $_SESSION['user_nombre']   = $user['nombre'];
            $_SESSION['user_rol']      = $user['rol'];
            $_SESSION['access_token']  = $accessToken;
            $_SESSION['refresh_token'] = $refreshToken;
            $_SESSION['session_uuid']  = $sessionUuid;
            Security::regenerateSession();
            Security::log($pdo, $userId, 'mfa_exitoso', $ip, $ua);

            header("Location: ../index.php");
            exit();
        }
    }
} else {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificación MFA | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style2.css">
</head>
<body>
<header class="header"><h1>Bussimindness</h1></header>
<main>
  <h2>Verificación fallida</h2>
  <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
  <?php endif; ?>
  <p><a href="login.php">← Volver al login</a></p>
</main>
<footer><p> 2026 Bussimindness</p></footer>
</body>
</html>