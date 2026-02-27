<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

$mensaje = "";

// Mensaje bonito cuando viene del logout
if (isset($_GET['logout'])) {
    $mensaje = "👋 Tu sesión se cerró con éxito, ¿te vas tan pronto?";
}

// Procesar login si viene por POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $stmt = $conn->prepare("SELECT id, password FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // debes hacerlo como localhost says 
            if ($pass === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                header("Location: ../perfil/perfil.php");
                exit();
            } else {
                $mensaje = "❌ Contraseña incorrecta";
            }
        } else {
            $mensaje = "❌ Usuario no encontrado";
        }
    } else {
        $mensaje = "❌ Completa todos los campos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Iniciar sesión | Bussimindness</title>
  <link rel="stylesheet" href="../assets/css/style2.css">

  <!-- reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<header class="header">
  <h1>Bussimindness</h1>
</header>

<main>
  <h2>Iniciar sesión</h2>

  <?php if (!empty($mensaje)): ?>
    <div style="
        background: #e8f5e9;
        color: #2e7d32;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        margin-bottom: 12px;
        font-weight: bold;
    ">
        <?php echo $mensaje; ?>
    </div>
  <?php endif; ?>

  <form action="login.php" method="POST">
    <label>Email</label><br>
    <input type="email" name="email" required><br><br>

    <label>Contraseña</label><br>
    <input type="password" name="password" required><br><br>

    <!-- CAPTCHA -->
    <div class="g-recaptcha"
         data-sitekey="6Lev-mMsAAAAAA2SevQLqQ0SZuhMRfciIXymvA3f">
    </div>
    <br>

    <button type="submit">Ingresar</button>
  </form>

  <p>¿No tienes cuenta?
    <a href="register.html">Regístrate aquí</a>
  </p>
</main>

<footer>
  <p>© 2026 Bussimindness</p>
</footer>

</body>
</html>