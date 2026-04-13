<?php
// ============================================================
//  auth/recuperar.php  —  Recuperación de contraseña (frontend)
//  Métodos: email | preguntas secretas | SMS | llamada
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/JWT.php';

$paso    = $_GET['paso'] ?? 'inicio';  // inicio | preguntas | reset
$mensaje = '';
$tipo    = '';
$preguntas_usuario = [];
$reset_token_temp  = '';

// ── PASO 1: Solicitar recuperación ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $ip     = Security::getClientIp();

    // Rate limiting
    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM tokens_recuperacion tr
         JOIN usuarios u ON u.id=tr.usuario_id
         WHERE u.email=? AND tr.creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $count->execute([$email]);
    if ((int)$count->fetchColumn() >= 3) {
        $mensaje = " Demasiadas solicitudes. Espera 15 minutos."; $tipo = "error";
        goto renderizar;
    }

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email=? AND estado='activo' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $userId = $user['id'] ?? null;

    $genericMsg = " Si el correo existe, recibirás instrucciones en breve.";

    if ($accion === 'email') {
        if ($userId) {
            // Invalidar tokens anteriores
            $pdo->prepare(
                "UPDATE tokens_recuperacion SET usado=1 WHERE usuario_id=? AND tipo='email'"
            )->execute([$userId]);

            $rawToken  = Security::generateToken(32);
            $tokenHash = hash('sha256', $rawToken);
            $expira    = date('Y-m-d H:i:s', time() + 3600);

            $pdo->prepare(
                "INSERT INTO tokens_recuperacion (usuario_id, token_hash, tipo, expira_en)
                 VALUES (?,?,'email',?)"
            )->execute([$userId, $tokenHash, $expira]);

            $enlace = "http://localhost/auth/reset_password.php?token=$rawToken";
            // En producción: mail($email, "Recuperar contraseña", "Enlace: $enlace");
            Security::log($pdo, $userId, 'recuperacion_email', $ip, '');
            // En dev mostramos el enlace:
            $mensaje = " Enlace generado (dev): <a href='$enlace'>$enlace</a>";
            $tipo    = "success";
        } else {
            $mensaje = $genericMsg; $tipo = "success";
        }

    } elseif ($accion === 'preguntas') {
        if ($userId) {
            $pStmt = $pdo->prepare(
                "SELECT id, pregunta FROM preguntas_secretas WHERE usuario_id=?"
            );
            $pStmt->execute([$userId]);
            $preguntas_usuario = $pStmt->fetchAll();
        }
        if (!empty($preguntas_usuario)) {
            $paso = 'preguntas';
            $_SESSION['recovery_user_id'] = $userId;
        } else {
            $mensaje = " No tienes preguntas secretas configuradas."; $tipo = "error";
        }

    } elseif ($accion === 'sms' || $accion === 'llamada') {
        if ($userId) {
            $otp     = Security::generateOtp();
            $otpHash = password_hash($otp, PASSWORD_BCRYPT);
            $pdo->prepare("DELETE FROM codigos_mfa WHERE usuario_id=? AND tipo='sms'")->execute([$userId]);
            $pdo->prepare(
                "INSERT INTO codigos_mfa (usuario_id, codigo_hash, tipo, expira_en)
                 VALUES (?,?,'sms', DATE_ADD(NOW(), INTERVAL 10 MINUTE))"
            )->execute([$userId, $otpHash]);
            Security::log($pdo, $userId, "recuperacion_$accion", $ip, '');
            // En dev mostramos el código:
            $tipo_acc = $accion === 'sms' ? 'SMS' : 'llamada de voz simulada';
            $mensaje  = " Código enviado por $tipo_acc (dev): <strong>$otp</strong>";
            $tipo     = "success";
            $paso     = 'otp';
            $_SESSION['recovery_user_id'] = $userId;
        } else {
            $mensaje = $genericMsg; $tipo = "success";
        }
    }

    // ── PASO 2: Verificar pregunta secreta ─────────────────
    if ($accion === 'verificar_pregunta') {
        $userId    = $_SESSION['recovery_user_id'] ?? 0;
        $pregId    = (int)($_POST['pregunta_id'] ?? 0);
        $respuesta = strtolower(trim($_POST['respuesta'] ?? ''));

        $stmt = $pdo->prepare(
            "SELECT respuesta_hash FROM preguntas_secretas WHERE id=? AND usuario_id=?"
        );
        $stmt->execute([$pregId, $userId]);
        $rec = $stmt->fetch();

        if (!$rec || !password_verify($respuesta, $rec['respuesta_hash'])) {
            Security::log($pdo, $userId, 'pregunta_secreta_fallida', $ip, '');
            $mensaje = " Respuesta incorrecta."; $tipo = "error";
            $paso = 'preguntas';
            // Recargar preguntas
            $pStmt = $pdo->prepare("SELECT id, pregunta FROM preguntas_secretas WHERE usuario_id=?");
            $pStmt->execute([$userId]);
            $preguntas_usuario = $pStmt->fetchAll();
        } else {
            // Generar token de reset
            $rawToken  = Security::generateToken(32);
            $tokenHash = hash('sha256', $rawToken);
            $expira    = date('Y-m-d H:i:s', time() + 900);
            $pdo->prepare(
                "INSERT INTO tokens_recuperacion (usuario_id, token_hash, tipo, expira_en)
                 VALUES (?,?,'email',?)"
            )->execute([$userId, $tokenHash, $expira]);
            header("Location: reset_password.php?token=$rawToken");
            exit();
        }
    }

    // ── PASO 2b: Verificar OTP (sms/llamada) ───────────────
    if ($accion === 'verificar_otp') {
        $userId = $_SESSION['recovery_user_id'] ?? 0;
        $otp    = trim($_POST['otp'] ?? '');

        $stmt = $pdo->prepare(
            "SELECT id, codigo_hash, intentos FROM codigos_mfa
             WHERE usuario_id=? AND tipo='sms' AND usado=0 AND expira_en>NOW()
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $rec = $stmt->fetch();

        if (!$rec || !password_verify($otp, $rec['codigo_hash'])) {
            $pdo->prepare("UPDATE codigos_mfa SET intentos=intentos+1 WHERE id=?")->execute([$rec['id'] ?? 0]);
            $mensaje = " Código incorrecto."; $tipo = "error"; $paso = 'otp';
        } else {
            $pdo->prepare("UPDATE codigos_mfa SET usado=1 WHERE id=?")->execute([$rec['id']]);
            $rawToken  = Security::generateToken(32);
            $tokenHash = hash('sha256', $rawToken);
            $expira    = date('Y-m-d H:i:s', time() + 900);
            $pdo->prepare(
                "INSERT INTO tokens_recuperacion (usuario_id, token_hash, tipo, expira_en)
                 VALUES (?,?,'email',?)"
            )->execute([$userId, $tokenHash, $expira]);
            header("Location: reset_password.php?token=$rawToken");
            exit();
        }
    }
}

renderizar:
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style2.css">
  <style>
    .tab-btns { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
    .tab-btns button { padding:8px 14px; border:2px solid #ccc; background:#fff; cursor:pointer; border-radius:6px; font-size:0.9em; }
    .tab-btns button.active { border-color:#4CAF50; background:#4CAF50; color:#fff; }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }
    .info-box { background:#e8f5e9; border:1px solid #a5d6a7; padding:12px; border-radius:6px; margin-bottom:12px; font-size:0.9em; }
  </style>
</head>
<body>
<header class="header"><h1>Bussimindness</h1></header>
<main>
  <h2> Recuperar contraseña</h2>

  <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?= $tipo ?>"><?= $mensaje ?></div>
  <?php endif; ?>

  <?php if ($paso === 'inicio'): ?>
  <p>Elige cómo quieres recuperar tu contraseña:</p>

  <div class="tab-btns">
    <button class="active" onclick="showTab('email',this)"> Email</button>
    <button onclick="showTab('sms',this)"> SMS</button>
    <button onclick="showTab('llamada',this)"> Llamada</button>
    <button onclick="showTab('preguntas',this)"> Preguntas secretas</button>
  </div>

  <!-- Email -->
  <div id="tab-email" class="tab-panel active">
    <div class="info-box">Recibirás un enlace único válido por 1 hora.</div>
    <form method="POST">
      <input type="hidden" name="accion" value="email">
      <label>Email de tu cuenta</label>
      <input type="email" name="email" required>
      <button type="submit">Enviar enlace</button>
    </form>
  </div>

  <!-- SMS -->
  <div id="tab-sms" class="tab-panel">
    <div class="info-box">Recibirás un código OTP de 6 dígitos por SMS (simulado).</div>
    <form method="POST">
      <input type="hidden" name="accion" value="sms">
      <label>Email de tu cuenta</label>
      <input type="email" name="email" required>
      <button type="submit">Enviar código SMS</button>
    </form>
  </div>

  <!-- Llamada -->
  <div id="tab-llamada" class="tab-panel">
    <div class="info-box">Recibirás un código de voz simulado (válido 5 minutos).</div>
    <form method="POST">
      <input type="hidden" name="accion" value="llamada">
      <label>Email de tu cuenta</label>
      <input type="email" name="email" required>
      <button type="submit">Solicitar llamada</button>
    </form>
  </div>

  <!-- Preguntas secretas -->
  <div id="tab-preguntas" class="tab-panel">
    <div class="info-box">Responde tu pregunta secreta para acceder.</div>
    <form method="POST">
      <input type="hidden" name="accion" value="preguntas">
      <label>Email de tu cuenta</label>
      <input type="email" name="email" required>
      <button type="submit">Ver mis preguntas</button>
    </form>
  </div>

  <?php elseif ($paso === 'preguntas' && !empty($preguntas_usuario)): ?>
  <!-- Responder pregunta -->
  <form method="POST">
    <input type="hidden" name="accion" value="verificar_pregunta">
    <?php foreach ($preguntas_usuario as $p): ?>
    <div style="margin-bottom:16px;">
      <label><?= htmlspecialchars($p['pregunta']) ?></label>
      <input type="text" name="respuesta" required>
      <input type="hidden" name="pregunta_id" value="<?= $p['id'] ?>">
    </div>
    <?php break; // Solo mostrar 1 pregunta a la vez ?>
    <?php endforeach; ?>
    <button type="submit">Verificar respuesta</button>
  </form>

  <?php elseif ($paso === 'otp'): ?>
  <!-- Verificar OTP de SMS/llamada -->
  <div class="info-box">Ingresa el código de 6 dígitos que recibiste.</div>
  <form method="POST">
    <input type="hidden" name="accion" value="verificar_otp">
    <label>Código OTP</label>
    <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}"
           style="letter-spacing:6px;font-size:1.3em;text-align:center;" required>
    <button type="submit">Verificar</button>
  </form>
  <?php endif; ?>

  <p><a href="login.php">← Volver al login</a></p>
</main>
<footer><p> 2026 Bussimindness</p></footer>
<script>
function showTab(id, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btns button').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>