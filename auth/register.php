<?php
// ============================================================
//  register.php — Registro completo (backend + HTML)
// ============================================================

session_start();
include(__DIR__ . "/../config/conexion.php");

// Si ya está logueado, redirigir
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$mensaje      = "";
$tipo_mensaje = "";

// Procesar POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre  = trim($_POST['nombre']           ?? '');
    $email   = trim($_POST['email']            ?? '');
    $pass    =      $_POST['password']         ?? '';
    $confirm =      $_POST['confirm_password'] ?? '';

    // Validaciones
    if (!$nombre || !$email || !$pass || !$confirm) {
        $mensaje      = "❌ Completa todos los campos.";
        $tipo_mensaje = "error";

    } elseif (strlen($nombre) < 2 || strlen($nombre) > 100) {
        $mensaje      = "❌ El nombre debe tener entre 2 y 100 caracteres.";
        $tipo_mensaje = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje      = "❌ El formato del email no es válido.";
        $tipo_mensaje = "error";

    } elseif ($pass !== $confirm) {
        $mensaje      = "❌ Las contraseñas no coinciden.";
        $tipo_mensaje = "error";

    } elseif (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        $mensaje      = "❌ La contraseña debe tener mínimo 8 caracteres, una letra y un número.";
        $tipo_mensaje = "error";

    } else {
        // Verificar email duplicado
        $check = $conn->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje      = "❌ El correo ya está registrado.";
            $tipo_mensaje = "error";
        } else {
            // Hashear contraseña y registrar
            $hash   = password_hash($pass, PASSWORD_DEFAULT);
            $rol    = "usuario";
            $estado = "activo";

            $stmt = $conn->prepare(
                "INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssss", $nombre, $email, $hash, $rol, $estado);

            if ($stmt->execute()) {
                // Login automático
                $_SESSION['user_id']     = $stmt->insert_id;
                $_SESSION['user_nombre'] = $nombre;
                $_SESSION['user_rol']    = $rol;
                session_regenerate_id(true);
                header("Location: ../index.php?bienvenido=1");
                exit();
            } else {
                $mensaje      = "❌ Error en el servidor. Intenta más tarde.";
                $tipo_mensaje = "error";
            }

            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style3.css">
</head>
<body>

<header class="header">
  <h1>Bussimindness</h1>
</header>

<main>
  <div class="register-container">
    <h2>Crear cuenta</h2>

    <?php if (!empty($mensaje)): ?>
      <div class="error-box alerta-<?= $tipo_mensaje ?>">
        <?= htmlspecialchars($mensaje) ?>
      </div>
    <?php endif; ?>

    <form action="register.php" method="POST">

      <label>Nombre</label>
      <input type="text" name="nombre"
             value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
             required>

      <label>Email</label>
      <input type="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             required>

      <label>Contraseña</label>
      <input type="password" name="password" required>
      <small>Mínimo 8 caracteres, al menos 1 letra y 1 número.</small>

      <label>Confirmar contraseña</label>
      <input type="password" name="confirm_password" required>

      <button type="submit">Registrarse</button>
    </form>

    <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
  </div>
</main>

<footer>
  <p>© 2026 Bussimindness</p>
</footer>

</body>
</html>