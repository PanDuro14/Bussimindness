<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.html");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: ../perfil/perfil.php");
    exit();
}

$idp     = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Verificar que la oferta pertenece al usuario
$stmt = $conn->prepare("SELECT id, imagen FROM ofertas WHERE id = ? AND idUsuario = ? LIMIT 1");
$stmt->bind_param("ii", $idp, $user_id);
$stmt->execute();
$oferta = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$oferta) {
    die("No tienes permiso para editar esta publicación.");
}

// Recoger campos
$nombre       = trim($_POST['nombre']       ?? '');
$descripcion  = trim($_POST['descripcion']  ?? '');
$precio       = floatval($_POST['precio']   ?? 0);
$tipo         = trim($_POST['tipo']         ?? '');
$categoria_id = intval($_POST['categoria_id'] ?? 0);
$ubicacion    = trim($_POST['ubicacion']    ?? '');
$condicion    = trim($_POST['condicion']    ?? '');
$estado       = trim($_POST['estado']       ?? 'activo');

if (empty($nombre) || empty($tipo)) {
    die("Error: Nombre y tipo son obligatorios.");
}

// ── Procesar nueva imagen (si se subió) ───────────────────
$ruta_imagen = $oferta['imagen']; // mantener la actual por defecto

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $tipo_mime  = mime_content_type($_FILES['imagen']['tmp_name']);
    $permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($tipo_mime, $permitidos)) {
        die("Error: Solo se permiten imágenes JPG, PNG, WEBP o GIF.");
    }
    if ($_FILES['imagen']['size'] > 5 * 1024 * 1024) {
        die("Error: La imagen es demasiado grande (máx 5MB).");
    }

    $carpeta = __DIR__ . "/../uploads/ofertas/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    $ext            = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $nombre_archivo = uniqid('oferta_', true) . '.' . strtolower($ext);
    $destino        = $carpeta . $nombre_archivo;

    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
        // Eliminar imagen anterior si existe
        if (!empty($oferta['imagen']) && file_exists(__DIR__ . '/../' . $oferta['imagen'])) {
            unlink(__DIR__ . '/../' . $oferta['imagen']);
        }
        $ruta_imagen = "uploads/ofertas/" . $nombre_archivo;
    }
}

// ── Actualizar en BD ──────────────────────────────────────
$cat_param = $categoria_id > 0 ? $categoria_id : null;

$stmt2 = $conn->prepare("
    UPDATE ofertas SET
        nombre       = ?,
        descripcion  = ?,
        precio       = ?,
        tipo         = ?,
        categoria_id = ?,
        ubicacion    = ?,
        condicion    = ?,
        estado       = ?,
        imagen       = ?
    WHERE id = ? AND idUsuario = ?
");

$stmt2->bind_param(
    "ssdsissssii",
    $nombre,
    $descripcion,
    $precio,
    $tipo,
    $cat_param,
    $ubicacion,
    $condicion,
    $estado,
    $ruta_imagen,
    $idp,
    $user_id
);

if ($stmt2->execute()) {
    header("Location: ../perfil/perfil.php?editado=1");
    exit();
} else {
    echo "Error al actualizar: " . $stmt2->error;
}

$stmt2->close();
$conn->close();
?>