<?php
// ============================================================
//  auth/login.php  
//  Añade: JWT, MFA, brute-force en BD, sesiones multi-device
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Security.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$mensaje      = "";
$tipo_mensaje = "";
$mfa_pendiente = false;
$mfa_token     = '';

if (isset($_GET['logout'])) {
    $mensaje      = "👋 Sesión cerrada con éxito.";
    $tipo_mensaje = "success";
}

$ip = Security::getClientIp();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// ── Control de bloqueo por IP (en BD) ─────────────────────
$bloqueado = Security::isBruteForce($ip, $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    // ── Validación reCAPTCHA desactivada temporalmente ─────
    // El widget sigue visible pero no bloquea el login
    // $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    // ... verificación omitida

    if (!$email || !$password) {
        $mensaje = "❌ Completa todos los campos."; $tipo_mensaje = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "❌ Email inválido."; $tipo_mensaje = "error";

    } else {
        // ── Buscar usuario ─────────────────────────────────
        $stmt = $pdo->prepare(
            "SELECT id, nombre, email, password, rol, estado,
                    mfa_activo, intentos_fallidos, bloqueado_hasta
             FROM usuarios WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // ── Verificar bloqueo de cuenta ────────────────────
        if ($user && $user['bloqueado_hasta'] && strtotime($user['bloqueado_hasta']) > time()) {
            $min = ceil((strtotime($user['bloqueado_hasta']) - time()) / 60);
            $mensaje = "🔒 Cuenta bloqueada. Intenta en $min minutos."; $tipo_mensaje = "error";

        } elseif (!$user || $user['estado'] !== 'activo' || !password_verify($password, $user['password'])) {
            if ($user) {
                $intentos = $user['intentos_fallidos'] + 1;
                $bloquear = $intentos >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                $pdo->prepare(
                    "UPDATE usuarios SET intentos_fallidos=?, bloqueado_hasta=? WHERE id=?"
                )->execute([$intentos, $bloquear, $user['id']]);
                $restantes = max(0, 5 - $intentos);
                $mensaje   = $intentos >= 5
                    ? "🔒 Demasiados intentos. Bloqueado 15 minutos."
                    : "❌ Credenciales incorrectas. Intentos restantes: $restantes";
            } else {
                $mensaje = "❌ Credenciales incorrectas.";
            }
            Security::log($pdo, $user['id'] ?? null, 'login_fallido', $ip, $ua, $email);
            $tipo_mensaje = "error";

        } else {
            // ── Credenciales correctas ─────────────────────
            $pdo->prepare(
                "UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?"
            )->execute([$user['id']]);

            // ── MFA activo: generar OTP ────────────────────
            if ($user['mfa_activo']) {
                $otp     = Security::generateOtp();
                $otpHash = password_hash($otp, PASSWORD_BCRYPT);
                $expira  = date('Y-m-d H:i:s', time() + 600);

                $pdo->prepare("DELETE FROM codigos_mfa WHERE usuario_id=? AND tipo='email'")->execute([$user['id']]);
                $pdo->prepare(
                    "INSERT INTO codigos_mfa (usuario_id, codigo_hash, tipo, expira_en) VALUES (?,?,'email',?)"
                )->execute([$user['id'], $otpHash, $expira]);

                $mfa_token     = JWT::generate(['sub'=>$user['id'],'purpose'=>'mfa_pending'], 600);
                $mfa_pendiente = true;

                Security::log($pdo, $user['id'], 'mfa_otp_generado', $ip, $ua);

                $mensaje      = "📧 Código OTP generado (dev): <strong>$otp</strong> — válido 10 min";
                $tipo_mensaje = "info";

            } else {
                // ── Login completo sin MFA ─────────────────
                $sessionUuid  = bin2hex(random_bytes(32));
                $refreshToken = JWT::generateRefresh($user['id'], $sessionUuid);
                $refreshHash  = password_hash($refreshToken, PASSWORD_BCRYPT);
                $expiraEn     = date('Y-m-d H:i:s', time() + 604800);

                $pdo->prepare(
                    "INSERT INTO sesiones (session_uuid, usuario_id, refresh_token_hash, ip, user_agent, expira_en)
                     VALUES (?,?,?,?,?,?)"
                )->execute([$sessionUuid, $user['id'], $refreshHash, $ip, $ua, $expiraEn]);

                $accessToken = JWT::generate([
                    'sub'          => $user['id'],
                    'email'        => $user['email'],
                    'nombre'       => $user['nombre'],
                    'role'         => $user['rol'],
                    'session_uuid' => $sessionUuid,
                ]);

                $_SESSION['user_id']       = $user['id'];
                $_SESSION['user_nombre']   = $user['nombre'];
                $_SESSION['user_rol']      = $user['rol'];
                $_SESSION['access_token']  = $accessToken;
                $_SESSION['refresh_token'] = $refreshToken;
                $_SESSION['session_uuid']  = $sessionUuid;
                Security::regenerateSession();

                Security::log($pdo, $user['id'], 'login_exitoso', $ip, $ua);
                header("Location: ../index.php");
                exit();
            }
        }
    }
}

if ($bloqueado && empty($mensaje)) {
    $mensaje      = "🔒 Demasiados intentos desde tu IP. Espera 15 minutos.";
    $tipo_mensaje = "error";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style2.css">
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <style>
    .alerta-info { background:#d1ecf1; border:1px solid #bee5eb; color:#0c5460; padding:10px; border-radius:6px; margin-bottom:12px; }
    .mfa-box { background:#f8f9fa; border:2px solid #4CAF50; border-radius:8px; padding:20px; margin-top:16px; }
    .mfa-box h3 { margin:0 0 12px; color:#2d6a4f; }
    .otp-input { letter-spacing:8px; font-size:1.4em; text-align:center; width:160px; padding:8px; border:2px solid #4CAF50; border-radius:6px; }
  </style>
</head>
<body>

<header class="header">
  <h1>Bussimindness</h1>
</header>

<main>
  <h2>Iniciar sesión</h2>

  <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?= $tipo_mensaje ?>">
      <?= $tipo_mensaje === 'info' ? $mensaje : htmlspecialchars($mensaje) ?>
    </div>
  <?php endif; ?>

  <?php if ($mfa_pendiente): ?>
  <div class="mfa-box">
    <h3>🔐 Verificación en dos pasos</h3>
    <p>Ingresa el código OTP de 6 dígitos enviado a tu correo.</p>
    <form action="mfa_verify.php" method="POST">
      <input type="hidden" name="mfa_token" value="<?= htmlspecialchars($mfa_token) ?>">
      <label for="otp">Código OTP</label><br>
      <input type="text" id="otp" name="otp" class="otp-input"
             maxlength="6" pattern="[0-9]{6}" required
             autocomplete="one-time-code" placeholder="000000"><br><br>
      <button type="submit">Verificar</button>
    </form>
  </div>

  <?php elseif (!$bloqueado): ?>
  <form action="login.php" method="POST" autocomplete="on">
    <label for="email">Email</label>
    <input type="email" id="email" name="email"
           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
           required autocomplete="email"><br><br>

    <label for="password">Contraseña</label>
    <input type="password" id="password" name="password"
           required autocomplete="current-password"><br><br>

    <!-- reCAPTCHA visible pero sin validación backend -->
    <div class="g-recaptcha"
         data-sitekey="6Lfvwo0sAAAAAFBbtrGicQr5oiDqkPj_f5cKVHVF"></div>
    <br>
    <button type="submit">Ingresar</button>
  </form>

  <p>
    ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a> |
    <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
  </p>
  <?php endif; ?>
</main>

<footer><p>© 2026 Bussimindness</p></footer>
</body>
</html>