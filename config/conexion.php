<?php
$host = "localhost";
$db   = "bussimindness";
$user = "root";
$pass = ""; // cambia si tienes contraseña

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión");
}
?>
