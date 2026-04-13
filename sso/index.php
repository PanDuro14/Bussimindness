<?php
// ============================================================
//  sso/index.php  —  Demostración SSO (App A + App B)
//  Simula dos aplicaciones en el mismo servidor
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RBAC.php';

$user = RBAC::checkSession();
$userId      = $user['id'];
$sessionUuid = $_SESSION['session_uuid'] ?? '';
$accessToken = $_SESSION['access_token'] ?? '';

$ssoToken    = '';
$ssoUrl      = '';
$appBResult  = null;
$mensaje     = '';
$tipo        = '';

// ── Generar token SSO (App A) ─────────────────────────────
if (isset($_POST['accion']) && $_POST['accion'] === 'generar_sso') {
    if (!Security::csrfVerify($_POST['csrf_token'] ?? '')) die('CSRF error');

    // Verificar que el JWT del usuario sea válido
    $payload = $accessToken ? JWT::verify($accessToken) : false;
    if (!$payload) {
        $mensaje = " Tu sesión JWT no está disponible. Cierra sesión y vuelve a entrar.";
        $tipo    = "error";
    } else {
        // Invalidar SSO tokens anteriores no usados
        $pdo->prepare("DELETE FROM sso_tokens WHERE usuario_id=? AND usado=0")->execute([$userId]);

        $rawToken  = Security::generateToken(32);
        $tokenHash = hash('sha256', $rawToken);
        $expira    = date('Y-m-d H:i:s', time() + 30); // 30 segundos

        $pdo->prepare(
            "INSERT INTO sso_tokens (usuario_id, token_hash, app_origen, expira_en)
             VALUES (?,?,'appA',?)"
        )->execute([$userId, $tokenHash, $expira]);

        Security::log($pdo, $userId, 'sso_token_emitido', Security::getClientIp(), '', 'App A');

        $ssoToken = $rawToken;
        $ssoUrl   = "?validar_sso=" . urlencode($rawToken);
        $mensaje  = " Token SSO generado (válido 30 segundos). App B puede autenticarte.";
        $tipo     = "success";
    }
}

// ── Validar token SSO (simula ser App B) ──────────────────
if (isset($_GET['validar_sso'])) {
    $rawToken  = trim($_GET['validar_sso']);
    $tokenHash = hash('sha256', $rawToken);
    $ip        = Security::getClientIp();
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $pdo->prepare(
        "SELECT st.id, st.usuario_id, u.nombre, u.email, u.rol
         FROM sso_tokens st JOIN usuarios u ON u.id=st.usuario_id
         WHERE st.token_hash=? AND st.usado=0 AND st.expira_en>NOW() AND u.estado='activo'
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $rec = $stmt->fetch();

    if (!$rec) {
        $appBResult = ['error' => 'Token SSO inválido, expirado o ya usado'];
        $mensaje    = " App B rechazó el token SSO."; $tipo = "error";
    } else {
        // Marcar como usado (token de un solo uso)
        $pdo->prepare("UPDATE sso_tokens SET usado=1 WHERE id=?")->execute([$rec['id']]);

        // App B crea su propia sesión y JWT
        $sessionUuidB  = bin2hex(random_bytes(32));
        $accessTokenB  = JWT::generate([
            'sub'          => $rec['usuario_id'],
            'email'        => $rec['email'],
            'nombre'       => $rec['nombre'],
            'role'         => $rec['rol'],
            'session_uuid' => $sessionUuidB,
            'app'          => 'appB',
        ]);
        $expiraEn = date('Y-m-d H:i:s', time() + 604800);
        $pdo->prepare(
            "INSERT INTO sesiones (session_uuid, usuario_id, ip, user_agent, expira_en)
             VALUES (?,?,?,?,?)"
        )->execute([$sessionUuidB, $rec['usuario_id'], $ip, $ua, $expiraEn]);

        Security::log($pdo, $rec['usuario_id'], 'sso_login_appB', $ip, $ua);

        $appBResult = [
            'success'     => true,
            'app'         => 'App B',
            'usuario'     => $rec['nombre'],
            'email'       => $rec['email'],
            'rol'         => $rec['rol'],
            'access_token'=> substr($accessTokenB, 0, 40) . '...',
            'session_uuid'=> substr($sessionUuidB, 0, 16) . '...',
        ];
        $mensaje = " SSO exitoso — App B autenticó al usuario sin pedir contraseña.";
        $tipo    = "success";
    }
}

$csrfToken = Security::csrfGenerate();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SSO Demo | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .sso-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; max-width:900px; margin:0 auto; }
    .app-box  { border:2px solid #e0e0e0; border-radius:10px; padding:20px; }
    .app-box.app-a { border-color:#1976D2; }
    .app-box.app-b { border-color:#388E3C; }
    .app-box h3    { margin-top:0; }
    .app-a h3 { color:#1976D2; }
    .app-b h3 { color:#388E3C; }
    .token-box { background:#f5f5f5; border-radius:6px; padding:10px; font-size:0.85em; word-break:break-all; margin:10px 0; }
    .result-json { background:#1e1e1e; color:#a8ff78; padding:16px; border-radius:8px; font-size:0.82em; white-space:pre-wrap; }
    .arrow { text-align:center; font-size:2em; padding:20px 0; }
    @media(max-width:600px){ .sso-grid { grid-template-columns:1fr } }
  </style>
</head>
<body>
<header class="header"><h1>Bussimindness</h1></header>
<main style="padding:24px;">
  <h2> Single Sign-On (SSO) — Demostración</h2>
  <p>Esta página simula <strong>App A</strong> y <strong>App B</strong> en el mismo servidor.</p>

  <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <div class="sso-grid">
    <!-- APP A -->
    <div class="app-box app-a">
      <h3> App A (Bussimindness)</h3>
      <p>Usuario autenticado: <strong><?= htmlspecialchars($user['nombre']) ?></strong></p>
      <p>Rol: <strong><?= htmlspecialchars($user['rol']) ?></strong></p>
      <p>Sesión UUID: <code><?= htmlspecialchars(substr($sessionUuid, 0, 16)) ?>...</code></p>

      <form method="POST">
        <input type="hidden" name="accion" value="generar_sso">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <button type="submit"> Generar token SSO para App B</button>
      </form>

      <?php if ($ssoToken): ?>
      <div class="token-box">
        <strong>Token SSO generado:</strong><br>
        <code><?= htmlspecialchars(substr($ssoToken, 0, 20)) ?>...</code><br><br>
        <a href="<?= htmlspecialchars($ssoUrl) ?>">
          → Simular acceso a App B con este token
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- APP B -->
    <div class="app-box app-b">
      <h3> App B (Sistema externo)</h3>
      <?php if ($appBResult): ?>
        <?php if (isset($appBResult['error'])): ?>
          <p style="color:#c62828;"> <?= htmlspecialchars($appBResult['error']) ?></p>
        <?php else: ?>
          <p> Usuario autenticado en App B <strong>sin contraseña</strong>:</p>
          <pre class="result-json"><?= json_encode($appBResult, JSON_PRETTY_PRINT) ?></pre>
        <?php endif; ?>
      <?php else: ?>
        <p style="color:#888;">Esperando token SSO de App A...</p>
        <p style="font-size:0.9em;">App B valida el token, lo marca como <em>usado</em> (un solo uso), y emite su propio JWT de sesión.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="arrow"></div>
  <p style="text-align:center; color:#666; font-size:0.9em;">
    Flujo: App A genera token SSO de 30s → App B lo valida → App B emite su propio JWT → Usuario autenticado sin re-login
  </p>

  <p><a href="../index.php">← Volver al inicio</a></p>
</main>
<footer><p> 2026 Bussimindness</p></footer>
</body>
</html>