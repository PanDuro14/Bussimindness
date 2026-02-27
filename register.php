<?php

include(__DIR__ . "/../config/conexion.php");
ini_set('display_errors',1);
error_reporting(E_ALL);



$nombre  = $_POST['nombre'];
$email   = $_POST['email'];
$pass    = $_POST['password'];
$confirm = $_POST['confirm_password'];

// ❌ Contraseñas no coinciden
if ($pass !== $confirm) {
    header("Location: register.html?error=pass");
    exit();
}

// ❌ Email ya existe
$check = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    header("Location: register.html?error=email");
    exit();
}

// Valores por defecto
$rol = "usuario";
$estado = "activo";

$stmt = $conn->prepare(
    "INSERT INTO usuarios (nombre, email, password, rol, estado)
     VALUES (?, ?, ?, ?, ?)"
);

$stmt->bind_param("sssss", $nombre, $email, $pass, $rol, $estado);

if ($stmt->execute()) {
    header("Location: login.html");
    exit();
} else {
    header("Location: register.html?error=server");
    exit();
}
?>
