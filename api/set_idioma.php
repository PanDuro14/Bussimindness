<?php
// ============================================================
//  api/usuario/set_idioma.php
//  Guarda el idioma preferido del usuario en BD y sesión
//  POST { idioma: "en" | "es" }
// ============================================================
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/conexion.php';

$input  = json_decode(file_get_contents('php://input'), true);
$idioma = in_array($input['idioma'] ?? '', ['es','en']) ? $input['idioma'] : 'es';

// Guardar en sesión PHP (para data-idioma en el <body>)
$_SESSION['idioma'] = $idioma;

// Si el usuario está logueado, guardar en BD también
if (!empty($_SESSION['user_id'])) {
    $pdo->prepare("UPDATE usuarios SET idioma=? WHERE id=?")
        ->execute([$idioma, $_SESSION['user_id']]);
}

echo json_encode(['ok' => true, 'idioma' => $idioma]);