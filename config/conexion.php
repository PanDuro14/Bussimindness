<?php
// ============================================================
//  config/conexion.php
//  Funciona en XAMPP (localhost) Y en Railway automáticamente
// ============================================================

// Railway inyecta estas variables de entorno automáticamente
// cuando conectas un plugin de MySQL/PostgreSQL
$host = $_ENV['MYSQLHOST']     ?? getenv('MYSQLHOST')     ?? 'localhost';
$db   = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'bussimindness';
$user = $_ENV['MYSQLUSER']     ?? getenv('MYSQLUSER')     ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? '';
$port = $_ENV['MYSQLPORT']     ?? getenv('MYSQLPORT')     ?? '3306';

// ── MySQLi (código original) ───────────────────────────────
$conn = new mysqli($host, $user, $pass, $db, (int)$port);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ── PDO (nuevas funciones de auth/JWT) ────────────────────
try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Error PDO: " . $e->getMessage());
}