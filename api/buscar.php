<?php
header('Content-Type: application/json; charset=utf-8');
include(__DIR__ . "/../config/conexion.php");

$q         = trim($_GET['q'] ?? '');
$categoria = intval($_GET['categoria'] ?? 0);
$limit     = 20;

$where  = ["o.estado = 'activo'"];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = "(o.nombre LIKE ? OR o.descripcion LIKE ?)";
    $like     = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

if ($categoria > 0) {
    $where[]  = "o.categoria_id = ?";
    $params[] = $categoria;
    $types   .= 'i';
}

$whereSQL = implode(' AND ', $where);

$sql = "
    SELECT o.id, o.nombre, o.tipo, o.precio, o.imagen, o.descripcion, o.ubicacion,
           u.nombre AS vendedor,
           c.nombre AS categoria_nombre
    FROM ofertas o
    LEFT JOIN usuarios u   ON o.idUsuario    = u.id
    LEFT JOIN categorias c ON o.categoria_id = c.id
    WHERE $whereSQL
    ORDER BY o.id DESC
    LIMIT $limit
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$resultados = [];
while ($row = $result->fetch_assoc()) {
    // imagen es ruta de archivo, no BLOB
    $img_url = null;
    if (!empty($row['imagen'])) {
        $img_url = $row['imagen']; // ej: uploads/ofertas/oferta_xxx.jpg
    }

    $resultados[] = [
        'id'          => (int)$row['id'],
        'nombre'      => $row['nombre'],
        'tipo'        => $row['tipo'],
        'precio'      => $row['precio'],
        'descripcion' => mb_substr($row['descripcion'] ?? '', 0, 120),
        'vendedor'    => $row['vendedor'],
        'categoria'   => $row['categoria_nombre'],
        'img_url'     => $img_url,
    ];
}

$stmt->close();
$conn->close();

echo json_encode([
    'ok'         => true,
    'total'      => count($resultados),
    'resultados' => $resultados,
    'query'      => $q,
]);