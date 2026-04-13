<?php
// ============================================================
//  perfil/config.php  —  Panel de configuración del usuario
//  PARTE 3: cambiar pass, MFA, sesiones activas, tema/idioma
// ============================================================
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RBAC.php';
require_once __DIR__ . '/../lib/JWT.php';

Security::setSecurityHeaders();
$user = RBAC::checkSession('usuario'); // requiere login mínimo

$userId      = $user['id'];
$sessionUuid = $_SESSION['session_uuid'] ?? '';
$csrfToken   = Security::csrfGenerate();

$mensaje = '';
$tipo    = '';

// ── Datos del usuario ─────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT id, nombre, email, rol, mfa_activo, tema, idioma FROM usuarios WHERE id=?"
);
$stmt->execute([$userId]);
$perfil = $stmt->fetch();

// ── Sesiones activas ──────────────────────────────────────
$sStmt = $pdo->prepare(
    "SELECT session_uuid, ip, user_agent, creado_en, ultima_actividad,
            (session_uuid=?) AS es_actual
     FROM sesiones WHERE usuario_id=? AND activa=1
     AND (expira_en IS NULL OR expira_en>NOW())
     ORDER BY ultima_actividad DESC"
);
$sStmt->execute([$sessionUuid, $userId]);
$sesiones = $sStmt->fetchAll();

// ── Preguntas secretas configuradas ──────────────────────
$qStmt = $pdo->prepare("SELECT id, pregunta FROM preguntas_secretas WHERE usuario_id=?");
$qStmt->execute([$userId]);
$preguntas = $qStmt->fetchAll();

// ── Procesar POST ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::csrfVerify($_POST['csrf_token'] ?? '')) {
        die("Error CSRF. Recarga la página.");
    }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'cambiar_password') {
        $actual = $_POST['password_actual'] ?? '';
        $nueva  = $_POST['password_nueva']  ?? '';
        $conf   = $_POST['password_conf']   ?? '';

        $uHash = $pdo->prepare("SELECT password FROM usuarios WHERE id=?");
        $uHash->execute([$userId]);
        $hashActual = $uHash->fetchColumn();

        if (!password_verify($actual, $hashActual)) {
            $mensaje = " Contraseña actual incorrecta."; $tipo = "error";
        } elseif ($nueva !== $conf) {
            $mensaje = " Las contraseñas no coinciden."; $tipo = "error";
        } elseif (!Security::isStrongPassword($nueva)) {
            $mensaje = " Contraseña débil."; $tipo = "error";
        } else {
            $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")
                ->execute([Security::hashPassword($nueva), $userId]);
            // Cerrar otras sesiones
            $pdo->prepare("UPDATE sesiones SET activa=0 WHERE usuario_id=? AND session_uuid!=?")
                ->execute([$userId, $sessionUuid]);
            Security::log($pdo, $userId, 'password_cambiado', Security::getClientIp(), '');
            $mensaje = " Contraseña actualizada. Otras sesiones cerradas."; $tipo = "success";
        }

    } elseif ($accion === 'activar_mfa') {
        $pdo->prepare("UPDATE usuarios SET mfa_activo=1 WHERE id=?")->execute([$userId]);
        $perfil['mfa_activo'] = 1;
        $mensaje = " MFA activado. Tu cuenta es más segura."; $tipo = "success";

    } elseif ($accion === 'desactivar_mfa') {
        $pdo->prepare("UPDATE usuarios SET mfa_activo=0, mfa_secret=NULL WHERE id=?")->execute([$userId]);
        $perfil['mfa_activo'] = 0;
        $mensaje = " MFA desactivado."; $tipo = "warning";

    } elseif ($accion === 'preferencias') {
        $tema   = in_array($_POST['tema'] ?? '', ['claro','oscuro']) ? $_POST['tema'] : 'claro';
        $idioma = in_array($_POST['idioma'] ?? '', ['es','en','fr']) ? $_POST['idioma'] : 'es';
        $pdo->prepare("UPDATE usuarios SET tema=?, idioma=? WHERE id=?")
            ->execute([$tema, $idioma, $userId]);
        $_SESSION['tema']   = $tema;
        $perfil['tema']     = $tema;
        $perfil['idioma']   = $idioma;
        $mensaje = " Preferencias guardadas."; $tipo = "success";

    } elseif ($accion === 'cerrar_sesion') {
        $uuidCerrar = $_POST['session_uuid'] ?? '';
        if ($uuidCerrar) {
            $pdo->prepare(
                "UPDATE sesiones SET activa=0 WHERE session_uuid=? AND usuario_id=?"
            )->execute([$uuidCerrar, $userId]);
            // Si cierra la sesión actual, hacer logout
            if ($uuidCerrar === $sessionUuid) {
                header("Location: ../auth/logout.php"); exit();
            }
            Security::log($pdo, $userId, 'sesion_remota_cerrada', Security::getClientIp(), '');
            $mensaje = " Sesión cerrada."; $tipo = "success";
            // Recargar sesiones
            $sStmt->execute([$sessionUuid, $userId]);
            $sesiones = $sStmt->fetchAll();
        }

    } elseif ($accion === 'guardar_preguntas') {
        $pregs = $_POST['pregunta'] ?? [];
        $resps = $_POST['respuesta'] ?? [];
        $validas = 0;
        $pdo->prepare("DELETE FROM preguntas_secretas WHERE usuario_id=?")->execute([$userId]);
        for ($i = 0; $i < min(3, count($pregs)); $i++) {
            $p = Security::sanitize($pregs[$i] ?? '');
            $r = strtolower(trim($resps[$i] ?? ''));
            if ($p && $r) {
                $pdo->prepare(
                    "INSERT INTO preguntas_secretas (usuario_id, pregunta, respuesta_hash)
                     VALUES (?,?,?)"
                )->execute([$userId, $p, password_hash($r, PASSWORD_BCRYPT)]);
                $validas++;
            }
        }
        $mensaje = $validas >= 2
            ? " $validas preguntas secretas guardadas."
            : " Necesitas al menos 2 preguntas válidas.";
        $tipo = $validas >= 2 ? "success" : "error";
        $qStmt->execute([$userId]);
        $preguntas = $qStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configuración | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .config-grid { display:grid; grid-template-columns:220px 1fr; gap:24px; max-width:900px; margin:0 auto; }
    .config-nav  { background:#f8f9fa; border-radius:8px; padding:16px; }
    .config-nav a { display:block; padding:10px 14px; border-radius:6px; text-decoration:none; color:#333; margin-bottom:4px; font-size:0.95em; }
    .config-nav a:hover, .config-nav a.active { background:#4CAF50; color:#fff; }
    .config-panel { display:none; }
    .config-panel.active { display:block; }
    .card { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:16px; }
    .card h3 { margin:0 0 16px; font-size:1.1em; }
    .sesion-item { border:1px solid #e0e0e0; border-radius:6px; padding:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; }
    .sesion-item.actual { border-color:#4CAF50; background:#f0fff0; }
    .sesion-meta { font-size:0.82em; color:#666; }
    .badge { padding:2px 8px; border-radius:10px; font-size:0.78em; background:#4CAF50; color:#fff; }
    .btn-danger { background:#e53935; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-size:0.85em; }
    .btn-danger:hover { background:#c62828; }
    .toggle-mfa { display:flex; align-items:center; gap:16px; }
    .mfa-on  { background:#e8f5e9; padding:10px 16px; border-radius:6px; border:1px solid #a5d6a7; }
    .mfa-off { background:#fff8e1; padding:10px 16px; border-radius:6px; border:1px solid #ffe082; }
    .alerta-warning { background:#fff3cd; border:1px solid #ffc107; color:#856404; padding:10px; border-radius:6px; margin-bottom:12px; }
    @media(max-width:600px){ .config-grid{grid-template-columns:1fr} }
  </style>
</head>
<body class="tema-<?= htmlspecialchars($perfil['tema'] ?? 'claro') ?>">
<header class="header">
  <h1>Bussimindness</h1>
  <nav>
    <?php foreach (\RBAC::getNavMenu($perfil['rol']) as $item): ?>
      <a href="<?= $item['url'] ?>"><?= $item['icon'] ?> <?= $item['label'] ?></a>
    <?php endforeach; ?>
    <a href="../auth/logout.php"> Salir</a>
  </nav>
</header>

<main style="padding:24px;">
  <h2> Configuración de cuenta</h2>

  <?php if (!empty($mensaje)): ?>
    <div class="alerta alerta-<?= $tipo ?>"><?= htmlspecialchars($mensaje) ?></div>
  <?php endif; ?>

  <div class="config-grid">
    <!-- Navegación lateral -->
    <nav class="config-nav">
      <a href="#" class="active" onclick="showPanel('password',this)"> Contraseña</a>
      <a href="#" onclick="showPanel('mfa',this)"> Doble factor (MFA)</a>
      <a href="#" onclick="showPanel('sesiones',this)"> Sesiones activas</a>
      <a href="#" onclick="showPanel('preguntas',this)"> Preguntas secretas</a>
      <a href="#" onclick="showPanel('preferencias',this)"> Preferencias</a>
    </nav>

    <div>
      <!-- Cambiar contraseña -->
      <div id="panel-password" class="config-panel active">
        <div class="card">
          <h3> Cambiar contraseña</h3>
          <form method="POST">
            <input type="hidden" name="accion" value="cambiar_password">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <label>Contraseña actual</label>
            <input type="password" name="password_actual" required><br><br>
            <label>Nueva contraseña</label>
            <input type="password" name="password_nueva" required><br>
            <small>Mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.</small><br><br>
            <label>Confirmar nueva contraseña</label>
            <input type="password" name="password_conf" required><br><br>
            <button type="submit">Actualizar contraseña</button>
          </form>
        </div>
      </div>

      <!-- MFA -->
      <div id="panel-mfa" class="config-panel">
        <div class="card">
          <h3> Autenticación de dos factores (MFA)</h3>
          <?php if ($perfil['mfa_activo']): ?>
          <div class="mfa-on toggle-mfa">
            <span>🟢 <strong>MFA activado</strong> — Se pedirá un código OTP al iniciar sesión.</span>
            <form method="POST" style="margin:0">
              <input type="hidden" name="accion" value="desactivar_mfa">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <button type="submit" class="btn-danger">Desactivar MFA</button>
            </form>
          </div>
          <?php else: ?>
          <div class="mfa-off toggle-mfa">
            <span> <strong>MFA desactivado</strong> — Tu cuenta es más vulnerable.</span>
            <form method="POST" style="margin:0">
              <input type="hidden" name="accion" value="activar_mfa">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <button type="submit"> Activar MFA</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sesiones activas -->
      <div id="panel-sesiones" class="config-panel">
        <div class="card">
          <h3> Sesiones activas (<?= count($sesiones) ?>)</h3>
          <?php foreach ($sesiones as $s): ?>
          <div class="sesion-item <?= $s['es_actual'] ? 'actual' : '' ?>">
            <div>
              <?php if ($s['es_actual']): ?>
                <span class="badge">Sesión actual</span>
              <?php endif; ?>
              <div class="sesion-meta">
                IP: <?= htmlspecialchars($s['ip'] ?? '—') ?><br>
                <?= htmlspecialchars(substr($s['user_agent'] ?? '—', 0, 60)) ?>...<br>
                Creada: <?= $s['creado_en'] ?> | Última actividad: <?= $s['ultima_actividad'] ?>
              </div>
            </div>
            <form method="POST" style="margin:0">
              <input type="hidden" name="accion" value="cerrar_sesion">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
              <input type="hidden" name="session_uuid" value="<?= htmlspecialchars($s['session_uuid']) ?>">
              <button type="submit" class="btn-danger">
                <?= $s['es_actual'] ? ' Cerrar mi sesión' : ' Cerrar' ?>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Preguntas secretas -->
      <div id="panel-preguntas" class="config-panel">
        <div class="card">
          <h3> Preguntas secretas de recuperación</h3>
          <?php if (!empty($preguntas)): ?>
            <p> Tienes <?= count($preguntas) ?> pregunta(s) configurada(s).</p>
          <?php else: ?>
            <p> No tienes preguntas secretas. Configúralas para recuperar tu cuenta.</p>
          <?php endif; ?>
          <form method="POST">
            <input type="hidden" name="accion" value="guardar_preguntas">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div style="margin-bottom:12px;">
              <label>Pregunta <?= $i+1 ?></label>
              <input type="text" name="pregunta[]"
                     value="<?= htmlspecialchars($preguntas[$i]['pregunta'] ?? '') ?>"
                     placeholder="Escribe una pregunta secreta">
              <label>Respuesta</label>
              <input type="password" name="respuesta[]" placeholder="Respuesta (se guardará cifrada)">
            </div>
            <?php endfor; ?>
            <button type="submit"> Guardar preguntas</button>
          </form>
        </div>
      </div>

      <!-- Preferencias -->
      <div id="panel-preferencias" class="config-panel">
        <div class="card">
          <h3> Preferencias de la aplicación</h3>
          <form method="POST">
            <input type="hidden" name="accion" value="preferencias">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <label>Tema</label>
            <select name="tema">
              <option value="claro" <?= $perfil['tema']==='claro' ? 'selected':'' ?>> Claro</option>
              <option value="oscuro" <?= $perfil['tema']==='oscuro' ? 'selected':'' ?>> Oscuro</option>
            </select><br><br>
            <label>Idioma</label>
            <select name="idioma">
              <option value="es" <?= ($perfil['idioma']??'es')==='es' ? 'selected':'' ?>> Español</option>
              <option value="en" <?= ($perfil['idioma']??'es')==='en' ? 'selected':'' ?>> English</option>
              <option value="fr" <?= ($perfil['idioma']??'es')==='fr' ? 'selected':'' ?>> Français</option>
            </select><br><br>
            <button type="submit"> Guardar preferencias</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
function showPanel(id, el) {
    document.querySelectorAll('.config-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.config-nav a').forEach(a => a.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    el.classList.add('active');
    return false;
}
</script>
</body>
</html>