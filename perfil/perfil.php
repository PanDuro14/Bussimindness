<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.html");
    exit();
}

$user_id = $_SESSION['user_id'];

// Datos del usuario
$stmt = $conn->prepare("SELECT nombre, email, foto, bio, ciudad FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Publicaciones del usuario
$productos = [];
$stmt2 = $conn->prepare("
    SELECT id, nombre, tipo, estado, precio, imagen
    FROM ofertas
    WHERE idUsuario = ? AND estado != 'eliminado'
    ORDER BY id DESC
");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result = $stmt2->get_result();
while ($row = $result->fetch_assoc()) {
    if (!empty($row['imagen']) && file_exists(__DIR__ . '/../' . $row['imagen'])) {
        $row['img_src'] = '../' . $row['imagen'];
    } else {
        $row['img_src'] = null;
    }
    $productos[] = $row;
}
$stmt2->close();
$conn->close();

$total = count($productos);
$activos   = count(array_filter($productos, fn($p) => $p['estado'] === 'activo'));
$vendidos  = count(array_filter($productos, fn($p) => $p['estado'] === 'vendido'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi perfil | Bussimindness</title>
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

        /* ── HEADER ── */
        .header {
            background: var(--navy);
            padding: 0 50px; height: 68px;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .logo { font-weight: 700; font-size: 1.2rem; color: white; }
        .logo span { color: var(--orange); }
        .nav { display: flex; gap: 4px; }
        .nav a {
            color: rgba(255,255,255,.75); padding: 8px 14px;
            border-radius: 8px; font-size: .875rem; font-weight: 500;
            transition: background .2s, color .2s;
        }
        .nav a:hover { background: rgba(255,255,255,.1); color: white; }
        .btn-publish {
            background: var(--orange); color: white;
            padding: 8px 18px; border-radius: 8px;
            font-weight: 600; font-size: .875rem;
            transition: background .2s;
        }
        .btn-publish:hover { background: var(--orange2); }

        /* ── LAYOUT ── */
        .container { max-width: 1100px; margin: 40px auto; padding: 0 30px; }

        /* ── PERFIL CARD ── */
        .perfil-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 32px 36px;
            display: flex; align-items: center; gap: 28px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
        }
        .avatar {
            width: 90px; height: 90px; border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #1a4a7a);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.2rem; color: white; font-weight: 700;
            flex-shrink: 0; overflow: hidden;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .perfil-info { flex: 1; }
        .perfil-info h2 { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
        .perfil-info .email { color: var(--muted); font-size: .875rem; margin-bottom: 10px; }
        .perfil-info .ciudad { font-size: .82rem; color: var(--muted); }
        .perfil-stats { display: flex; gap: 24px; }
        .stat { text-align: center; }
        .stat .num { font-size: 1.5rem; font-weight: 700; color: var(--navy); }
        .stat .label { font-size: .72rem; color: var(--muted); font-weight: 500; }

        /* ── SECCIÓN PUBLICACIONES ── */
        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px;
        }
        .section-title { font-size: 1.2rem; font-weight: 700; color: var(--navy); }
        .section-title span { color: var(--orange); }

        /* ── FILTROS ── */
        .filtros { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .filtro-btn {
            padding: 6px 16px; border-radius: 100px;
            border: 1.5px solid var(--border);
            background: var(--white); color: var(--muted);
            font-family: 'Poppins', sans-serif; font-size: .8rem;
            font-weight: 500; cursor: pointer; transition: all .2s;
        }
        .filtro-btn.active, .filtro-btn:hover {
            border-color: var(--navy); background: var(--navy); color: white;
        }

        /* ── GRID ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }

        /* ── CARD ── */
        .pub-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            overflow: hidden;
            transition: transform .2s, box-shadow .2s, border-color .2s;
        }
        .pub-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,.12);
            border-color: var(--orange);
        }
        .pub-img {
            width: 100%; height: 150px; object-fit: cover; display: block;
        }
        .pub-placeholder {
            width: 100%; height: 150px;
            background: linear-gradient(135deg, #e8edf5, #d1dae8);
            display: flex; align-items: center; justify-content: center;
            font-size: 2.5rem;
        }
        .pub-body { padding: 12px 14px; }
        .pub-badge {
            display: inline-block; padding: 2px 8px; border-radius: 100px;
            font-size: .65rem; font-weight: 600; letter-spacing: .4px;
            text-transform: uppercase; margin-bottom: 5px;
        }
        .badge-producto { background: #e8f4fd; color: var(--navy); }
        .badge-servicio { background: #fff3e6; color: #c96a00; }
        .estado-dot {
            display: inline-block; width: 7px; height: 7px;
            border-radius: 50%; margin-right: 4px;
        }
        .dot-activo  { background: #22c55e; }
        .dot-vendido { background: #94a3b8; }
        .dot-pausado { background: #f59e0b; }
        .pub-nombre {
            font-size: .9rem; font-weight: 600; color: var(--navy);
            margin-bottom: 3px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pub-precio { font-size: 1rem; font-weight: 700; color: var(--orange); margin-bottom: 8px; }
        .pub-estado { font-size: .75rem; color: var(--muted); margin-bottom: 10px; }
        .pub-acciones { display: flex; gap: 6px; }
        .btn-accion {
            flex: 1; padding: 7px 0; border-radius: 7px; border: none;
            font-family: 'Poppins', sans-serif; font-size: .75rem;
            font-weight: 600; cursor: pointer; transition: all .2s;
            text-align: center; display: inline-block;
        }
        .btn-ver   { background: #e8f4fd; color: var(--navy); }
        .btn-editar { background: #f0fdf4; color: #166534; }
        .btn-eliminar { background: #fff1f2; color: #be123c; }
        .btn-ver:hover    { background: var(--navy); color: white; }
        .btn-editar:hover  { background: #22c55e; color: white; }
        .btn-eliminar:hover { background: #ef4444; color: white; }

        /* ── EMPTY ── */
        .empty-state {
            grid-column: 1 / -1; text-align: center; padding: 60px 20px;
            color: var(--muted);
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
        .empty-state p { margin-bottom: 16px; }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            .header { padding: 0 20px; }
            .container { padding: 0 16px; margin-top: 24px; }
            .perfil-card { flex-direction: column; text-align: center; padding: 24px; }
            .perfil-stats { justify-content: center; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="logo">Bussim<span>i</span>ndness</div>
    <nav class="nav">
        <a href="../index.php">Inicio</a>
        <a href="perfil.php">Mi perfil</a>
    </nav>
    <div style="display:flex;gap:10px;align-items:center;">
        <a href="../productos/producto.php" class="btn-publish">+ Publicar</a>
        <a href="../logout.php" style="color:rgba(255,255,255,.6);font-size:.85rem;">Salir</a>
    </div>
</header>

<div class="container">

    <!-- PERFIL -->
    <div class="perfil-card">
        <div class="avatar">
            <?php if (!empty($user['foto'])): ?>
                <img src="../<?= htmlspecialchars($user['foto']) ?>" alt="Foto de perfil">
            <?php else: ?>
                <?= strtoupper(substr($user['nombre'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div class="perfil-info">
            <h2><?= htmlspecialchars($user['nombre'] ?? 'Usuario') ?></h2>
            <div class="email">✉️ <?= htmlspecialchars($user['email'] ?? '') ?></div>
            <?php if (!empty($user['ciudad'])): ?>
                <div class="ciudad">📍 <?= htmlspecialchars($user['ciudad']) ?></div>
            <?php endif; ?>
        </div>
        <div class="perfil-stats">
            <div class="stat">
                <div class="num"><?= $total ?></div>
                <div class="label">Publicaciones</div>
            </div>
            <div class="stat">
                <div class="num"><?= $activos ?></div>
                <div class="label">Activas</div>
            </div>
            <div class="stat">
                <div class="num"><?= $vendidos ?></div>
                <div class="label">Vendidos</div>
            </div>
        </div>
    </div>

    <!-- PUBLICACIONES -->
    <div class="section-header">
        <h2 class="section-title">Mis publicaciones <span>(<?= $total ?>)</span></h2>
        <a href="../productos/producto.php" class="btn-publish">+ Nueva publicación</a>
    </div>

    <!-- FILTROS -->
    <div class="filtros">
        <button class="filtro-btn active" onclick="filtrar('todos', this)">Todos</button>
        <button class="filtro-btn" onclick="filtrar('activo', this)">Activos</button>
        <button class="filtro-btn" onclick="filtrar('vendido', this)">Vendidos</button>
        <button class="filtro-btn" onclick="filtrar('pausado', this)">Pausados</button>
        <button class="filtro-btn" onclick="filtrar('producto', this)">Productos</button>
        <button class="filtro-btn" onclick="filtrar('servicio', this)">Servicios</button>
    </div>

    <!-- GRID -->
    <?php if (!empty($productos)): ?>
        <div class="grid" id="gridPublicaciones">
            <?php foreach ($productos as $prod): ?>
                <div class="pub-card" data-estado="<?= $prod['estado'] ?>" data-tipo="<?= $prod['tipo'] ?>">

                    <?php if ($prod['img_src']): ?>
                        <img class="pub-img"
                             src="<?= htmlspecialchars($prod['img_src']) ?>"
                             alt="<?= htmlspecialchars($prod['nombre']) ?>">
                    <?php else: ?>
                        <div class="pub-placeholder">📦</div>
                    <?php endif; ?>

                    <div class="pub-body">
                        <span class="pub-badge badge-<?= $prod['tipo'] ?>">
                            <?= ucfirst($prod['tipo']) ?>
                        </span>
                        <div class="pub-nombre"><?= htmlspecialchars($prod['nombre']) ?></div>

                        <?php if ($prod['precio']): ?>
                            <div class="pub-precio">$<?= number_format($prod['precio'], 2) ?></div>
                        <?php endif; ?>

                        <div class="pub-estado">
                            <span class="estado-dot dot-<?= $prod['estado'] ?>"></span>
                            <?= ucfirst($prod['estado']) ?>
                        </div>

                        <div class="pub-acciones">
                            <a href="../productos/verproducto.php?id=<?= $prod['id'] ?>" class="btn-accion btn-ver">👁 Ver</a>
                            <a href="../productos/editar_producto.php?id=<?= $prod['id'] ?>" class="btn-accion btn-editar">✏️</a>
                            <a href="../productos/eliminar_producto.php?id=<?= $prod['id'] ?>"
                               class="btn-accion btn-eliminar"
                               onclick="return confirm('¿Eliminar esta publicación?')">🗑️</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="grid">
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>Aún no tienes publicaciones</p>
                <a href="../productos/producto.php" class="btn-publish">Crear primera publicación</a>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
function filtrar(filtro, btn) {
    // Actualizar botón activo
    document.querySelectorAll('.filtro-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Filtrar cards
    document.querySelectorAll('.pub-card').forEach(card => {
        const estado = card.dataset.estado;
        const tipo   = card.dataset.tipo;
        const mostrar =
            filtro === 'todos'    ||
            estado === filtro     ||
            tipo   === filtro;
        card.style.display = mostrar ? 'block' : 'none';
    });
}
</script>

</body>
</html>s