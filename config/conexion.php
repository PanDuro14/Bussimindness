<?php
// ============================================================
//  config/conexion.php
// ============================================================

// Railway usa getenv(), no $_ENV directamente
$host = getenv('MYSQLHOST')     ?: 'localhost';
$db   = getenv('MYSQLDATABASE') ?: 'bussimindness';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

// ── MySQLi ────────────────────────────────────────────────
$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    die("Error de conexión MySQLi: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ── PDO ───────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
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