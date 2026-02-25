<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

$email = $_POST['email'];
$pass  = $_POST['password'];

$stmt = $conn->prepare(
    "SELECT id, password FROM usuarios WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if ($pass === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: ../perfil/perfil.php");
    } else {
        die("Contraseña incorrecta");
    }
} else {
    die("Usuario no encontrado");
}
?>
