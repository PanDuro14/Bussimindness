<?php

session_start();
include(__dir__ ."/../config/conexion.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

if (isset($_GET['id'])) {

    $idp = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM ofertas WHERE id=?");
    $stmt->bind_param("i", $idp);

    if ($stmt->execute()) {
        echo "Producto eliminado correctamente";
    } else {
        echo "Error al eliminar";
    }

    $stmt->close();
    $conn->close();

    header("Location: perfil.php");
    exit();

} else {
    echo "ID no válido";
}
?>
