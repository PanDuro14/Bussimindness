<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['id'])) {
    header("Location: ../index.php");
    exit();
}

$idp = intval($_GET['id']);

// Traer oferta con datos del vendedor y categoría
$stmt = $conn->prepare("
    SELECT o.*,
           u.id        AS vendedor_id,
           u.nombre    AS vendedor_nombre,
           u.foto      AS vendedor_foto,
           u.ciudad    AS vendedor_ciudad,
           u.fecha_registro AS vendedor_desde,
           c.nombre    AS categoria_nombre,
           c.icono     AS categoria_icono
    FROM ofertas o
    LEFT JOIN usuarios   u ON o.idUsuario    = u.id
    LEFT JOIN categorias c ON o.categoria_id = c.id
    WHERE o.id = ? AND o.estado != 'eliminado'
    LIMIT 1
");
$stmt->bind_param("i", $idp);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) {
    http_response_code(404);
    die("<h2 style='font-family:sans-serif;padding:40px'>Publicación no encontrada.</h2>");
}

// Incrementar vistas
$conn->query("UPDATE ofertas SET vistas = vistas + 1 WHERE id = $idp");

// Más publicaciones del mismo vendedor (excepto esta)
$mas = [];
$stmt2 = $conn->prepare("
    SELECT id, nombre, precio, imagen, tipo
    FROM ofertas
    WHERE idUsuario = ? AND id != ? AND estado = 'activo'
    ORDER BY id DESC LIMIT 4
");
$stmt2->bind_param("ii", $prod['idUsuario'], $idp);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($r = $res2->fetch_assoc()) $mas[] = $r;
$stmt2->close();
$conn->close();

// Procesar imagen principal
$img_src = null;
if (!empty($prod['imagen']) && file_exists(__DIR__ . '/../' . $prod['imagen'])) {
    $img_src = '../' . $prod['imagen'];
}

$logueado    = isset($_SESSION['user_id']);
$es_mio      = $logueado && $_SESSION['user_id'] == $prod['idUsuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($prod['nombre']) ?> | Bussimindness</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy:    #0b2d50;
            --orange:  #f58220;
            --orange2: #e5711a;
            --bg:      #f6f8fb;
            --white:   #ffffff;
            --border:  #e2e8f0;
            --text:    #2d3748;
            --muted:   #718096;
            --radius:  12px;
            --shadow:  0 4px 20px rgba(0,0,0,.08);
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; color: inherit; }

        /* HEADER */
        .header {
            background: var(--navy); height: 68px;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 50px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .logo { font-weight: 700; font-size: 1.2rem; color: white; }
        .logo span { color: var(--orange); }
        .header-links { display: flex; gap: 16px; align-items: center; }
        .header-links a { color: rgba(255,255,255,.75); font-size: .875rem; }
        .header-links a:hover { color: white; }

        /* BREADCRUMB */
        .breadcrumb {
            padding: 12px 50px;
            font-size: .8rem; color: var(--muted);
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }
        .breadcrumb a { color: var(--navy); }
        .breadcrumb span { margin: 0 6px; }

        /* LAYOUT */
        .container {
            max-width: 1100px; margin: 32px auto;
            padding: 0 30px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 28px;
        }

        /* COLUMNA IZQUIERDA */
        .col-main {}

        /* IMAGEN */
        .img-wrap {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        .img-wrap img {
            width: 100%; max-height: 460px;
            object-fit: contain; display: block;
            background: #f8fafc;
        }
        .img-placeholder {
            width: 100%; height: 340px;
            background: linear-gradient(135deg, #e8edf5, #d1dae8);
            display: flex; align-items: center; justify-content: center;
            font-size: 5rem;
        }

        /* INFO CARD */
        .info-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .info-badges { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
        .badge {
            padding: 3px 12px; border-radius: 100px;
            font-size: .72rem; font-weight: 600;
            letter-spacing: .4px; text-transform: uppercase;
        }
        .badge-tipo-producto { background: #e8f4fd; color: var(--navy); }
        .badge-tipo-servicio { background: #fff3e6; color: #c96a00; }
        .badge-cat { background: #f1f5f9; color: var(--muted); }
        .badge-condicion { background: #f0fdf4; color: #166534; }

        .prod-nombre {
            font-size: 1.6rem; font-weight: 700;
            color: var(--navy); line-height: 1.2;
            margin-bottom: 12px;
        }
        .prod-precio {
            font-size: 2rem; font-weight: 800;
            color: var(--orange); margin-bottom: 20px;
        }
        .prod-precio small {
            font-size: .9rem; color: var(--muted); font-weight: 400;
        }

        .detalle-row {
            display: flex; gap: 8px; align-items: center;
            font-size: .875rem; color: var(--muted);
            margin-bottom: 8px;
        }
        .detalle-row strong { color: var(--text); }

        .divider {
            border: none; border-top: 1px solid var(--border);
            margin: 20px 0;
        }

        .descripcion-titulo {
            font-size: .9rem; font-weight: 600;
            color: var(--navy); margin-bottom: 8px;
        }
        .descripcion-texto {
            font-size: .875rem; color: var(--text);
            line-height: 1.7; white-space: pre-wrap;
        }

        /* ACCIONES (si es mi publicación) */
        .mis-acciones {
            display: flex; gap: 10px; margin-top: 20px;
        }
        .btn-editar {
            flex: 1; padding: 11px; border-radius: 8px;
            background: #f0fdf4; color: #166534;
            border: 1.5px solid #bbf7d0;
            font-family: 'Poppins', sans-serif;
            font-weight: 600; font-size: .875rem;
            cursor: pointer; text-align: center;
            transition: all .2s;
        }
        .btn-editar:hover { background: #22c55e; color: white; border-color: #22c55e; }
        .btn-eliminar {
            flex: 1; padding: 11px; border-radius: 8px;
            background: #fff1f2; color: #be123c;
            border: 1.5px solid #fecdd3;
            font-family: 'Poppins', sans-serif;
            font-weight: 600; font-size: .875rem;
            cursor: pointer; text-align: center;
            transition: all .2s;
        }
        .btn-eliminar:hover { background: #ef4444; color: white; border-color: #ef4444; }

        /* COLUMNA DERECHA */
        .col-side {}

        /* VENDEDOR CARD */
        .vendedor-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        .vendedor-titulo {
            font-size: .8rem; font-weight: 600;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .5px; margin-bottom: 16px;
        }
        .vendedor-info {
            display: flex; gap: 14px; align-items: center;
            margin-bottom: 16px;
        }
        .vendedor-avatar {
            width: 52px; height: 52px; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #1a4a7a);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: white; font-weight: 700;
            flex-shrink: 0; overflow: hidden;
        }
        .vendedor-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .vendedor-nombre { font-weight: 700; color: var(--navy); margin-bottom: 2px; }
        .vendedor-desde { font-size: .75rem; color: var(--muted); }
        .vendedor-ciudad { font-size: .78rem; color: var(--muted); margin-top: 2px; }

        .btn-contactar {
            width: 100%; padding: 12px;
            background: var(--orange); color: white;
            border: none; border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: .9rem; font-weight: 700;
            cursor: pointer; transition: background .2s;
            margin-bottom: 10px; display: block;
            text-align: center;
        }
        .btn-contactar:hover { background: var(--orange2); }
        .btn-ver-perfil {
            width: 100%; padding: 11px;
            background: transparent; color: var(--navy);
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: .875rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
            display: block; text-align: center;
        }
        .btn-ver-perfil:hover { border-color: var(--navy); background: #f8fafc; }

        /* SEGURIDAD */
        .seguridad-card {
            background: #fffbeb;
            border: 1.5px solid #fde68a;
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            font-size: .8rem; color: #92400e;
            line-height: 1.5;
        }
        .seguridad-card strong { display: block; margin-bottom: 4px; }

        /* MÁS DEL VENDEDOR */
        .mas-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .mas-titulo {
            font-size: .9rem; font-weight: 700;
            color: var(--navy); margin-bottom: 14px;
        }
        .mas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .mas-item {
            border-radius: 8px; overflow: hidden;
            border: 1px solid var(--border);
            transition: transform .2s;
        }
        .mas-item:hover { transform: translateY(-2px); }
        .mas-item img {
            width: 100%; height: 80px; object-fit: cover; display: block;
        }
        .mas-img-placeholder {
            width: 100%; height: 80px;
            background: linear-gradient(135deg, #e8edf5, #d1dae8);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
        }
        .mas-item-body { padding: 7px 9px; }
        .mas-item-nombre {
            font-size: .75rem; font-weight: 600; color: var(--navy);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .mas-item-precio { font-size: .78rem; color: var(--orange); font-weight: 700; }

        /* RESPONSIVE */
        @media (max-width: 800px) {
            .header { padding: 0 20px; }
            .breadcrumb { padding: 10px 20px; }
            .container { grid-template-columns: 1fr; padding: 0 16px; }
            .col-side { order: -1; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="logo">Bussim<span>i</span>ndness</div>
    <div class="header-links">
        <a href="../index.php">← Inicio</a>
        <?php if ($logueado): ?>
            <a href="../perfil/perfil.php">Mi perfil</a>
        <?php else: ?>
            <a href="../auth/login.html">Ingresar</a>
        <?php endif; ?>
    </div>
</header>

<!-- BREADCRUMB -->
<nav class="breadcrumb">
    <a href="../index.php">Inicio</a>
    <span>›</span>
    <?php if ($prod['categoria_nombre']): ?>
        <a href="../index.php?categoria=<?= $prod['categoria_id'] ?>">
            <?= htmlspecialchars($prod['categoria_icono'] . ' ' . $prod['categoria_nombre']) ?>
        </a>
        <span>›</span>
    <?php endif; ?>
    <strong><?= htmlspecialchars($prod['nombre']) ?></strong>
</nav>

<!-- CONTENIDO -->
<div class="container">

    <!-- ══ COLUMNA PRINCIPAL ══ -->
    <div class="col-main">

        <!-- IMAGEN -->
        <div class="img-wrap">
            <?php if ($img_src): ?>
                <img src="<?= htmlspecialchars($img_src) ?>"
                     alt="<?= htmlspecialchars($prod['nombre']) ?>">
            <?php else: ?>
                <div class="img-placeholder">
                    <?= $prod['categoria_icono'] ?? '📦' ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- INFO -->
        <div class="info-card">
            <div class="info-badges">
                <span class="badge badge-tipo-<?= $prod['tipo'] ?>">
                    <?= $prod['tipo'] === 'producto' ? '📦 Producto' : '🛠️ Servicio' ?>
                </span>
                <?php if ($prod['categoria_nombre']): ?>
                    <span class="badge badge-cat">
                        <?= htmlspecialchars($prod['categoria_icono'] . ' ' . $prod['categoria_nombre']) ?>
                    </span>
                <?php endif; ?>
                <?php if ($prod['condicion']): ?>
                    <span class="badge badge-condicion">
                        <?= match($prod['condicion']) {
                            'nuevo'              => '✨ Nuevo',
                            'usado_buen_estado'  => '👍 Buen estado',
                            'usado_regular'      => '⚠️ Estado regular',
                            default              => $prod['condicion']
                        } ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="prod-nombre"><?= htmlspecialchars($prod['nombre']) ?></h1>

            <div class="prod-precio">
                $<?= number_format($prod['precio'], 2) ?>
                <small>MXN</small>
            </div>

            <?php if ($prod['ubicacion']): ?>
                <div class="detalle-row">
                    📍 <strong><?= htmlspecialchars($prod['ubicacion']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="detalle-row">
                👁️ <?= number_format($prod['vistas']) ?> vista<?= $prod['vistas'] != 1 ? 's' : '' ?>
                &nbsp;·&nbsp;
                📅 Publicado <?= date('d/m/Y', strtotime($prod['fecha_publicacion'])) ?>
            </div>

            <hr class="divider">

            <div class="descripcion-titulo">Descripción</div>
            <div class="descripcion-texto">
                <?= htmlspecialchars($prod['descripcion'] ?? 'Sin descripción.') ?>
            </div>

            <?php if ($es_mio): ?>
                <hr class="divider">
                <div class="mis-acciones">
                    <a href="editar_producto.php?id=<?= $prod['id'] ?>" class="btn-editar">✏️ Editar</a>
                    <a href="eliminar_producto.php?id=<?= $prod['id'] ?>"
                       class="btn-eliminar"
                       onclick="return confirm('¿Eliminar esta publicación?')">🗑️ Eliminar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ══ COLUMNA LATERAL ══ -->
    <div class="col-side">

        <!-- VENDEDOR -->
        <div class="vendedor-card">
            <div class="vendedor-titulo">Vendedor</div>
            <div class="vendedor-info">
                <div class="vendedor-avatar">
                    <?php if (!empty($prod['vendedor_foto'])): ?>
                        <img src="../<?= htmlspecialchars($prod['vendedor_foto']) ?>" alt="Foto">
                    <?php else: ?>
                        <?= strtoupper(substr($prod['vendedor_nombre'] ?? 'U', 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="vendedor-nombre">
                        <?= htmlspecialchars($prod['vendedor_nombre'] ?? 'Usuario') ?>
                    </div>
                    <?php if ($prod['vendedor_ciudad']): ?>
                        <div class="vendedor-ciudad">
                            📍 <?= htmlspecialchars($prod['vendedor_ciudad']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="vendedor-desde">
                        Miembro desde <?= date('Y', strtotime($prod['vendedor_desde'])) ?>
                    </div>
                </div>
            </div>

            <?php if (!$es_mio): ?>
                <?php if ($logueado): ?>
                    <a href="../mensajes/chat.php?vendedor=<?= $prod['vendedor_id'] ?>&oferta=<?= $prod['id'] ?>"
                       class="btn-contactar">
                        💬 Contactar vendedor
                    </a>
                <?php else: ?>
                    <a href="../auth/login.html" class="btn-contactar">
                        💬 Inicia sesión para contactar
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <a href="../perfil/vendedor.php?id=<?= $prod['vendedor_id'] ?>"
               class="btn-ver-perfil">
                Ver perfil del vendedor →
            </a>
        </div>

        <!-- SEGURIDAD -->
        <div class="seguridad-card">
            <strong>⚠️ Ten cuidado al reunirte</strong>
            Prioriza tu seguridad. Elige lugares públicos bien iluminados y evita sitios aislados.
        </div>

        <!-- MÁS DEL VENDEDOR -->
        <?php if (!empty($mas)): ?>
        <div class="mas-card">
            <div class="mas-titulo">
                Más de <?= htmlspecialchars($prod['vendedor_nombre'] ?? 'este vendedor') ?>
            </div>
            <div class="mas-grid">
                <?php foreach ($mas as $m): ?>
                    <?php
                    $m_img = null;
                    if (!empty($m['imagen']) && file_exists(__DIR__ . '/../' . $m['imagen'])) {
                        $m_img = '../' . $m['imagen'];
                    }
                    ?>
                    <a href="verproducto.php?id=<?= $m['id'] ?>" class="mas-item">
                        <?php if ($m_img): ?>
                            <img src="<?= htmlspecialchars($m_img) ?>"
                                 alt="<?= htmlspecialchars($m['nombre']) ?>">
                        <?php else: ?>
                            <div class="mas-img-placeholder">📦</div>
                        <?php endif; ?>
                        <div class="mas-item-body">
                            <div class="mas-item-nombre"><?= htmlspecialchars($m['nombre']) ?></div>
                            <?php if ($m['precio']): ?>
                                <div class="mas-item-precio">$<?= number_format($m['precio'], 2) ?></div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>