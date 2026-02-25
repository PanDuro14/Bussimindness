<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.html");
    exit();
}

$user_id      = $_SESSION['user_id'];
$nombre       = trim($_POST['nombre']        ?? '');
$descripcion  = trim($_POST['descripcion']   ?? '');
$precio       = floatval($_POST['precio']    ?? 0);
$tipo         = trim($_POST['tipo']          ?? '');
$categoria_id = intval($_POST['categoria_id'] ?? 0);
$ubicacion    = trim($_POST['ubicacion']     ?? '');
$condicion    = trim($_POST['condicion']     ?? '');
$estado       = "activo";

// Validar obligatorios
$errores = [];
if (empty($nombre))      $errores[] = "nombre";
if (empty($tipo))        $errores[] = "tipo";
if ($categoria_id === 0) $errores[] = "categoría";

if (!empty($errores)) {
    die("Error: Falta completar: " . implode(", ", $errores));
}

// Procesar imagen
if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    die("Error al subir imagen. Código: " . ($_FILES['imagen']['error'] ?? 'desconocido'));
}

$tipo_mime  = mime_content_type($_FILES['imagen']['tmp_name']);
$permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($tipo_mime, $permitidos)) {
    die("Error: Solo se permiten imágenes JPG, PNG, WEBP o GIF.");
}

if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
    die("Error: La imagen es demasiado grande (máx 5MB).");
}

$carpeta = __DIR__ . "/../uploads/ofertas/";
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$ext            = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
$nombre_archivo = uniqid('oferta_', true) . '.' . strtolower($ext);
$destino        = $carpeta . $nombre_archivo;

if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
    die("Error: No se pudo guardar la imagen en el servidor.");
}

$ruta_imagen = "uploads/ofertas/" . $nombre_archivo;

// Insertar en BD
$stmt = $conn->prepare("
    INSERT INTO ofertas
        (idUsuario, categoria_id, nombre, tipo, descripcion,
         precio, ubicacion, condicion, imagen, estado)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iisssdssss",
    $user_id,
    $categoria_id,
    $nombre,
    $tipo,
    $descripcion,
    $precio,
    $ubicacion,
    $condicion,
    $ruta_imagen,
    $estado
);

if ($stmt->execute()) {
    header("Location: ../perfil/perfil.php?ok=1");
    exit();
} else {
    echo "Error en la base de datos: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>