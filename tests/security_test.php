<?php
// ============================================================
//  tests/security_tests.php  —  Pruebas de seguridad
//  Ejecutar desde CLI:  php tests/security_tests.php
//  O desde browser:     /tests/security_tests.php
// ============================================================
$cli = php_sapi_name() === 'cli';

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../lib/JWT.php';
require_once __DIR__ . '/../lib/Security.php';
require_once __DIR__ . '/../lib/RBAC.php';

$ok_count  = 0;
$fail_count = 0;
$resultados = [];

function test(string $nombre, bool $resultado, string $detalle = ''): void {
    global $ok_count, $fail_count, $resultados, $cli;
    if ($resultado) {
        $ok_count++;
        $status = $cli ? "\033[32m PASS\033[0m" : '<span style="color:green"> PASS</span>';
    } else {
        $fail_count++;
        $status = $cli ? "\033[31m FAIL\033[0m" : '<span style="color:red"> FAIL</span>';
    }
    $linea = "$status  $nombre" . ($detalle ? " — $detalle" : '');
    $resultados[] = ['ok'=>$resultado,'nombre'=>$nombre,'detalle'=>$detalle];
    if ($cli) echo "$linea\n";
}

function seccion(string $titulo): void {
    global $cli;
    if ($cli) echo "\n\033[1;34m══ $titulo ══\033[0m\n";
}

// ════════════════════════════════════════════════════════════
seccion('1. JWT — Generación y verificación');
// ════════════════════════════════════════════════════════════

$token   = JWT::generate(['sub'=>1,'email'=>'test@test.com','role'=>'usuario','session_uuid'=>'test-uuid-001']);
$payload = JWT::verify($token);
test('JWT generado y verificado correctamente', $payload !== false && $payload['sub'] === 1);

// Tamper: cambiar payload sin actualizar firma
$partes   = explode('.', $token);
$partes[1] = base64_encode(json_encode(['sub'=>999,'role'=>'admin','exp'=>time()+999,'iat'=>time(),'jti'=>'fake']));
test('Token manipulado rechazado', JWT::verify(implode('.', $partes)) === false);

// Firma con secret incorrecto
test('Token con firma errónea rechazado',
     JWT::verify($partes[0] . '.' . $partes[1] . '.firma_falsa_xyz') === false);

// ════════════════════════════════════════════════════════════
seccion('2. Expiración de tokens');
// ════════════════════════════════════════════════════════════

$expirado = JWT::generate(['sub'=>1,'role'=>'usuario','session_uuid'=>'x'], -1);
sleep(1);
test('Token expirado rechazado', JWT::verify($expirado) === false);

$vigente = JWT::generate(['sub'=>1,'role'=>'usuario','session_uuid'=>'x'], 3600);
test('Token vigente aceptado', JWT::verify($vigente) !== false);

// ════════════════════════════════════════════════════════════
seccion('3. Blacklist — Revocación de tokens');
// ════════════════════════════════════════════════════════════

$tokenRev = JWT::generate(['sub'=>1,'role'=>'usuario','session_uuid'=>'rev-test-001']);
$jti      = JWT::getJti($tokenRev);
test('JTI extraído correctamente', $jti !== null && strlen($jti) === 32);

JWT::revoke($tokenRev, $pdo);
test('Token revocado detectado en blacklist', $jti && JWT::isRevoked($jti, $pdo));

// Token no revocado no debe estar en blacklist
$tokenFresh = JWT::generate(['sub'=>1,'role'=>'usuario','session_uuid'=>'fresh-001']);
$jtiF = JWT::getJti($tokenFresh);
test('Token no revocado no está en blacklist', $jtiF && !JWT::isRevoked($jtiF, $pdo));

// ════════════════════════════════════════════════════════════
seccion('4. Contraseñas — Hash y validación');
// ════════════════════════════════════════════════════════════

$plain = 'MiPassword123!';
$hash  = Security::hashPassword($plain);
test('Hash de contraseña generado', strlen($hash) > 20);
test('Contraseña correcta verificada', password_verify($plain, $hash));
test('Contraseña incorrecta rechazada', !password_verify('WrongPass', $hash));
test('Hash diferente en cada llamada', $hash !== Security::hashPassword($plain));

// ════════════════════════════════════════════════════════════
seccion('5. Validación de contraseña segura');
// ════════════════════════════════════════════════════════════

$debiles = ['123456','password','abcdefgh','ABCDEFGH1','abcdefgh1','short1!'];
foreach ($debiles as $p) {
    test("Contraseña débil rechazada: '$p'", !Security::isStrongPassword($p));
}
$fuertes = ['MiPass123!','Secure#2024','Admin1!xY'];
foreach ($fuertes as $p) {
    test("Contraseña fuerte aceptada: '$p'", Security::isStrongPassword($p));
}

// ════════════════════════════════════════════════════════════
seccion('6. Sanitización — Prevención XSS y SQL Injection');
// ════════════════════════════════════════════════════════════

$ataques = [
    '<script>alert(1)</script>'           => 'XSS script tag',
    '"; DROP TABLE usuarios; --'          => 'SQL Injection',
    '<img src=x onerror=alert(1)>'        => 'XSS img tag',
    'javascript:alert(1)'                  => 'XSS javascript protocol',
];
foreach ($ataques as $input => $nombre) {
    $clean = Security::sanitize($input);
    test("$nombre sanitizado", strpos($clean, '<script>') === false && strpos($clean, '<img') === false);
}

// ════════════════════════════════════════════════════════════
seccion('7. Detección de Fuerza Bruta');
// ════════════════════════════════════════════════════════════

$testIp = '10.99.99.' . rand(1,254);

// IP limpia no debe estar bloqueada
test('IP limpia no bloqueada', !Security::isBruteForce($testIp, $pdo));

// Simular 11 intentos fallidos
for ($i = 0; $i < 11; $i++) {
    $pdo->prepare(
        "INSERT INTO logs_seguridad (usuario_id, evento, ip, creado_en) VALUES (NULL,'login_fallido',?,NOW())"
    )->execute([$testIp]);
}
test('Fuerza bruta detectada tras 10+ intentos', Security::isBruteForce($testIp, $pdo));

// Limpiar
$pdo->prepare("DELETE FROM logs_seguridad WHERE ip=?")->execute([$testIp]);

// ════════════════════════════════════════════════════════════
seccion('8. CSRF — Tokens de sesión');
// ════════════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) session_start();
$csrf = Security::csrfGenerate();
test('CSRF token generado', strlen($csrf) > 20);
test('CSRF token válido aceptado', Security::csrfVerify($csrf));
test('CSRF token falso rechazado', !Security::csrfVerify('token_falso_' . rand()));
test('CSRF token vacío rechazado', !Security::csrfVerify(''));

// ════════════════════════════════════════════════════════════
seccion('9. Refresh Token');
// ════════════════════════════════════════════════════════════

$uuid    = bin2hex(random_bytes(16));
$refresh = JWT::generateRefresh(42, $uuid);
$rData   = JWT::verify($refresh, true);
test('Refresh token generado y verificado', $rData !== false);
test('Refresh token contiene user_id correcto', ($rData['sub'] ?? 0) === 42);
test('Refresh token contiene session_uuid', ($rData['session_uuid'] ?? '') === $uuid);

// Refresh token no debe pasar como access token
test('Refresh token rechazado como access token', JWT::verify($refresh, false) === false);

// ════════════════════════════════════════════════════════════
seccion('10. SSO — Tokens de un solo uso');
// ════════════════════════════════════════════════════════════

$rawSso   = Security::generateToken(32);
$ssoHash  = hash('sha256', $rawSso);
$expira   = date('Y-m-d H:i:s', time() + 30);
$pdo->prepare("INSERT INTO sso_tokens (usuario_id,token_hash,expira_en) VALUES (1,?,?)")
    ->execute([$ssoHash, $expira]);

$stmt = $pdo->prepare("SELECT id FROM sso_tokens WHERE token_hash=? AND usado=0 AND expira_en>NOW()");
$stmt->execute([$ssoHash]);
test('Token SSO válido encontrado', (bool)$stmt->fetch());

// Marcar como usado
$pdo->prepare("UPDATE sso_tokens SET usado=1 WHERE token_hash=?")->execute([$ssoHash]);
$stmt->execute([$ssoHash]);
test('Token SSO de un solo uso rechazado tras primer uso', !$stmt->fetch());

// Token SSO expirado
$rawOld  = Security::generateToken(32);
$oldHash = hash('sha256', $rawOld);
$pdo->prepare("INSERT INTO sso_tokens (usuario_id,token_hash,expira_en) VALUES (1,?,DATE_SUB(NOW(),INTERVAL 1 MINUTE))")
    ->execute([$oldHash]);
$stmtOld = $pdo->prepare("SELECT id FROM sso_tokens WHERE token_hash=? AND usado=0 AND expira_en>NOW()");
$stmtOld->execute([$oldHash]);
test('Token SSO expirado rechazado', !$stmtOld->fetch());

// ════════════════════════════════════════════════════════════
seccion('11. RBAC — Control de acceso por rol');
// ════════════════════════════════════════════════════════════

test('Admin puede ver_usuarios', RBAC::can('admin', 'ver_usuarios'));
test('Admin puede gestionar_roles', RBAC::can('admin', 'gestionar_roles'));
test('Editor puede editar_productos', RBAC::can('editor', 'editar_productos'));
test('Editor NO puede gestionar_roles', !RBAC::can('editor', 'gestionar_roles'));
test('Usuario solo puede ver_productos', RBAC::can('usuario', 'ver_productos'));
test('Usuario NO puede editar_productos', !RBAC::can('usuario', 'editar_productos'));
test('Rol inexistente no tiene permisos', !RBAC::can('hacker', 'ver_usuarios'));

// ════════════════════════════════════════════════════════════
// RESUMEN
// ════════════════════════════════════════════════════════════
$total = $ok_count + $fail_count;

if ($cli) {
    echo "\n\033[1m══════════════════════════════════════\033[0m\n";
    $color = $fail_count === 0 ? "\033[32m" : "\033[31m";
    echo "{$color}Resultado: $ok_count/$total tests pasaron";
    if ($fail_count > 0) echo " ($fail_count fallaron)";
    echo "\033[0m\n\033[1m══════════════════════════════════════\033[0m\n";
    exit($fail_count > 0 ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pruebas de Seguridad | Bussimindness</title>
  <style>
    body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 24px; }
    h1   { color: #4EC9B0; }
    h2   { color: #569CD6; border-bottom: 1px solid #333; padding-bottom: 6px; }
    .ok  { color: #6A9955; }
    .fail{ color: #F44747; }
    .resumen { background:#252526; border-radius:8px; padding:16px; margin-top:24px; }
    .resumen.all-ok   { border:2px solid #6A9955; }
    .resumen.has-fail { border:2px solid #F44747; }
  </style>
</head>
<body>
<h1> Pruebas de Seguridad — Bussimindness</h1>
<?php
$seccion_actual = '';
foreach ($resultados as $r) {
    $icon  = $r['ok'] ? '<span class="ok"> PASS</span>' : '<span class="fail"> FAIL</span>';
    echo "<p>$icon &nbsp; " . htmlspecialchars($r['nombre']);
    if ($r['detalle']) echo " <em style='color:#888;'>— " . htmlspecialchars($r['detalle']) . "</em>";
    echo "</p>\n";
}
$cls = $fail_count === 0 ? 'all-ok' : 'has-fail';
echo "<div class='resumen $cls'>";
echo "<strong>Resultado: $ok_count / $total tests pasaron";
if ($fail_count > 0) echo " ($fail_count fallaron)";
echo "</strong></div>";
?>
</body>
</html>