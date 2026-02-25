<?php
session_start();
include(__DIR__ . "/config/conexion.php");

$categorias = [];
$res = $conn->query("SELECT id, nombre, icono FROM categorias WHERE estado = 'activo' ORDER BY nombre");
while ($row = $res->fetch_assoc()) $categorias[] = $row;

$carrusel = [];
$res2 = $conn->query("
    SELECT o.id, o.nombre, o.tipo, o.precio, o.imagen, o.ubicacion,
           u.nombre AS vendedor, c.nombre AS cat_nombre, c.icono AS cat_icono
    FROM ofertas o
    LEFT JOIN usuarios u   ON o.idUsuario    = u.id
    LEFT JOIN categorias c ON o.categoria_id = c.id
    WHERE o.estado = 'activo'
    ORDER BY RAND()
    LIMIT 12
");
while ($row = $res2->fetch_assoc()) {
    $row['img_src'] = (!empty($row['imagen']) && file_exists(__DIR__ . '/' . $row['imagen']))
        ? $row['imagen'] : null;
    unset($row['imagen']);
    $carrusel[] = $row;
}

$destacados = [];
$res3 = $conn->query("
    SELECT o.id, o.nombre, o.tipo, o.precio, o.imagen, o.ubicacion,
           u.nombre AS vendedor, c.nombre AS cat_nombre
    FROM ofertas o
    LEFT JOIN usuarios u   ON o.idUsuario    = u.id
    LEFT JOIN categorias c ON o.categoria_id = c.id
    WHERE o.estado = 'activo'
    ORDER BY o.fecha_publicacion DESC
    LIMIT 8
");
while ($row = $res3->fetch_assoc()) {
    $row['img_src'] = (!empty($row['imagen']) && file_exists(__DIR__ . '/' . $row['imagen']))
        ? $row['imagen'] : null;
    unset($row['imagen']);
    $destacados[] = $row;
}

$conn->close();
$logueado = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bussimindness — Marketplace Local</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
:root {
    --navy:      #0b2d50;
    --navy-dark: #071e37;
    --navy-mid:  #0e3a66;
    --orange:    #f58220;
    --orange2:   #e5711a;
    --bg:        #f6f8fb;
    --white:     #ffffff;
    --border:    #e2e8f0;
    --text:      #2d3748;
    --muted:     #718096;
    --radius:    12px;
    --shadow-sm: 0 2px 8px rgba(0,0,0,.06);
    --shadow:    0 4px 20px rgba(0,0,0,.10);
    --shadow-lg: 0 8px 40px rgba(0,0,0,.14);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); }
a { text-decoration: none; color: inherit; }
img { display: block; }

/* HEADER */
.header {
    position: sticky; top: 0; z-index: 200;
    display: flex; justify-content: space-between; align-items: center;
    padding: 0 50px; height: 68px;
    background: var(--navy);
    box-shadow: 0 2px 16px rgba(0,0,0,.25);
}
.logo { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.25rem; color: var(--white); }
.logo span { color: var(--orange); }
.nav { display: flex; gap: 4px; }
.nav a { padding: 8px 14px; color: rgba(255,255,255,.75); font-size: .875rem; font-weight: 500; border-radius: 8px; transition: background .2s, color .2s; }
.nav a:hover { background: rgba(255,255,255,.1); color: var(--white); }
.auth-buttons { display: flex; gap: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: 8px; border: none; font-family: 'Poppins', sans-serif; font-size: .875rem; font-weight: 600; cursor: pointer; transition: all .2s; text-decoration: none; }
.btn-login  { background: rgba(255,255,255,.12); color: var(--white); }
.btn-login:hover { background: rgba(255,255,255,.22); }
.btn-register { background: var(--orange); color: var(--white); }
.btn-register:hover { background: var(--orange2); }
.btn-publish { background: var(--orange); color: var(--white); font-size: .8rem; padding: 7px 16px; }
.btn-publish:hover { background: var(--orange2); }

/* HERO */
.hero {
    background: linear-gradient(150deg, var(--navy) 0%, var(--navy-mid) 60%, #1a4a7a 100%);
    padding: 70px 50px 60px; text-align: center; position: relative; overflow: hidden;
}
.hero::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.hero-content { position: relative; z-index: 1; }
.hero-badge { display: inline-block; padding: 5px 16px; background: rgba(245,130,32,.2); border: 1px solid rgba(245,130,32,.4); border-radius: 100px; color: #ffa44d; font-size: .78rem; font-weight: 600; letter-spacing: .5px; margin-bottom: 20px; text-transform: uppercase; }
.hero h1 { font-size: clamp(1.8rem, 4vw, 3rem); font-weight: 700; color: var(--white); line-height: 1.15; max-width: 640px; margin: 0 auto 12px; }
.hero h1 em { font-style: normal; color: var(--orange); }
.hero > .hero-content > p { color: rgba(255,255,255,.65); font-size: 1rem; margin-bottom: 44px; }

/* BUSCADOR */
.search-wrap { max-width: 680px; margin: 0 auto; background: var(--white); border-radius: 14px; padding: 14px; box-shadow: 0 8px 40px rgba(0,0,0,.3); }
.search-inner { display: grid; grid-template-columns: 1fr auto auto; gap: 10px; }
.search-field { position: relative; }
.search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1rem; pointer-events: none; }
.search-wrap input { width: 100%; padding: 12px 14px 12px 40px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: .9rem; color: var(--text); outline: none; transition: border-color .2s; }
.search-wrap input:focus { border-color: var(--navy); }
.search-wrap select { padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Poppins', sans-serif; font-size: .875rem; color: var(--text); background: var(--white); cursor: pointer; outline: none; min-width: 155px; }
.search-wrap select:focus { border-color: var(--navy); }
.btn-search { background: var(--navy); color: var(--white); border: none; border-radius: 8px; padding: 12px 28px; font-family: 'Poppins', sans-serif; font-size: .9rem; font-weight: 600; cursor: pointer; transition: background .2s; white-space: nowrap; }
.btn-search:hover { background: var(--navy-dark); }
.search-hints { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; justify-content: center; }
.search-hint { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); color: rgba(255,255,255,.8); padding: 4px 12px; border-radius: 100px; font-size: .78rem; cursor: pointer; transition: background .2s; }
.search-hint:hover { background: rgba(255,255,255,.2); }

/* RESULTADOS */
.results-section { display: none; padding: 40px 50px; max-width: 1300px; margin: 0 auto; }
.results-section.visible { display: block; }
.section-title { font-size: 1.3rem; font-weight: 700; color: var(--navy); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.section-title span { color: var(--orange); }
.results-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }

/* ══════════════════════════════════════════════════════
   CARRUSEL — lógica de 3 cards centradas con efecto escala
══════════════════════════════════════════════════════ */
.carousel-section { padding: 50px 50px 40px; max-width: 1300px; margin: 0 auto; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
.carousel-controls { display: flex; gap: 8px; }
.ctrl-btn { width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--border); background: var(--white); color: var(--navy); cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all .2s; box-shadow: var(--shadow-sm); }
.ctrl-btn:hover { border-color: var(--navy); background: var(--navy); color: var(--white); }
.ctrl-btn:disabled { opacity: .3; cursor: default; }

/* Stage: contenedor visible centrado */
.carousel-stage {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    padding: 20px 0 24px;
    min-height: 320px;
    position: relative;
}

/* Cada slot tiene ancho fijo; la card dentro escala */
.carousel-slot {
    flex: 0 0 auto;
    transition: all .4s cubic-bezier(.25,.46,.45,.94);
}
.carousel-slot.side {
    width: 220px;
    opacity: .7;
    transform: scale(0.88);
}
.carousel-slot.center {
    width: 280px;
    opacity: 1;
    transform: scale(1);
    z-index: 2;
}

.oferta-card {
    background: var(--white); border-radius: var(--radius); overflow: hidden;
    border: 1.5px solid var(--border);
    transition: box-shadow .2s, border-color .2s;
    cursor: pointer; display: block; text-decoration: none; color: inherit;
    width: 100%;
}
.carousel-slot.center .oferta-card { border-color: var(--orange); box-shadow: var(--shadow-lg); }
.carousel-slot.side .oferta-card:hover { border-color: var(--orange); }

.oferta-img { width: 100%; height: 160px; object-fit: cover; }
.carousel-slot.center .oferta-img { height: 200px; }

.oferta-placeholder { width: 100%; height: 160px; background: linear-gradient(135deg, #e8edf5, #d1dae8); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; }
.carousel-slot.center .oferta-placeholder { height: 200px; }

.oferta-body { padding: 12px 14px 14px; }
.oferta-badge { display: inline-block; padding: 2px 8px; border-radius: 100px; font-size: .68rem; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 5px; }
.badge-producto { background: #e8f4fd; color: #0b2d50; }
.badge-servicio { background: #fff3e6; color: #c96a00; }
.oferta-nombre { font-size: .9rem; font-weight: 600; color: var(--navy); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.oferta-precio { font-size: 1.1rem; font-weight: 700; color: var(--orange); margin-bottom: 5px; }
.oferta-meta { font-size: .75rem; color: var(--muted); display: flex; align-items: center; gap: 5px; }

/* Dots */
.carousel-dots { display: flex; justify-content: center; gap: 6px; margin-top: 4px; }
.dot { width: 7px; height: 7px; border-radius: 4px; background: var(--border); transition: all .3s; cursor: pointer; }
.dot.active { background: var(--orange); width: 22px; }

/* CATEGORÍAS */
.categories-section { padding: 0 50px 50px; max-width: 1300px; margin: 0 auto; }
.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
.cat-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 20px 10px; text-align: center; cursor: pointer; transition: all .2s; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.cat-card:hover { border-color: var(--orange); background: #fff8f2; transform: translateY(-3px); box-shadow: var(--shadow-sm); }
.cat-icon { font-size: 1.8rem; }
.cat-name { font-size: .78rem; font-weight: 600; color: var(--navy); }

/* DESTACADOS */
.destacados-section { padding: 0 50px 60px; max-width: 1300px; margin: 0 auto; }
.destacados-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; }
/* cards en grid no tienen efecto de scale */
.destacados-grid .oferta-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: var(--orange); }

/* CTA */
.cta-banner { background: linear-gradient(135deg, var(--navy) 0%, #1a4a7a 100%); border-radius: 18px; padding: 50px 60px; display: flex; justify-content: space-between; align-items: center; gap: 30px; max-width: 1200px; margin: 0 auto 60px; position: relative; overflow: hidden; }
.cta-banner::after { content: '🚀'; position: absolute; right: 200px; top: 50%; transform: translateY(-50%); font-size: 5rem; opacity: .08; }
.cta-text h2 { font-size: 1.6rem; font-weight: 700; color: var(--white); margin-bottom: 8px; }
.cta-text p { color: rgba(255,255,255,.65); font-size: .9rem; }
.btn-cta { background: var(--orange); color: var(--white); padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: .95rem; white-space: nowrap; transition: background .2s; border: none; cursor: pointer; font-family: 'Poppins', sans-serif; text-decoration: none; }
.btn-cta:hover { background: var(--orange2); }

.footer { background: var(--navy); color: rgba(255,255,255,.5); text-align: center; padding: 24px; font-size: .83rem; }

/* UTILS */
.spinner { display: inline-block; width: 20px; height: 20px; border: 2.5px solid var(--border); border-top-color: var(--navy); border-radius: 50%; animation: spin .65s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.empty-state { grid-column: 1 / -1; text-align: center; padding: 50px; color: var(--muted); }
.empty-state .icon { font-size: 3rem; margin-bottom: 10px; }

@media (max-width: 900px) {
    .header { padding: 0 20px; }
    .nav { display: none; }
    .hero { padding: 50px 20px 40px; }
    .search-inner { grid-template-columns: 1fr; }
    .search-wrap select { min-width: unset; }
    .carousel-section, .categories-section, .destacados-section, .results-section { padding-left: 20px; padding-right: 20px; }
    .cta-banner { padding: 36px 30px; flex-direction: column; text-align: center; }
    .cta-banner::after { display: none; }
    .carousel-slot.side { width: 160px; }
    .carousel-slot.center { width: 220px; }
}
    </style>
</head>
<body>

<header class="header">
    <div class="logo">
        <?php if (file_exists('logo.png')): ?>
            <img src="logo.png" alt="Bussimindness" style="height:36px">
        <?php else: ?>
            Bussim<span>i</span>ndness
        <?php endif; ?>
    </div>
    <nav class="nav">
        <a href="index.php">Inicio</a>
        <a href="#categorias">Categorías</a>
        <a href="#">Nosotros</a>
        <a href="#">Contacto</a>
    </nav>
    <div class="auth-buttons">
        <?php if ($logueado): ?>
            <a href="perfil/perfil.php" class="btn btn-login">Mi perfil</a>
            <a href="productos/producto.php" class="btn btn-publish">+ Publicar</a>
        <?php else: ?>
            <a href="auth/login.html" class="btn btn-login">Ingresar</a>
            <a href="auth/register.html" class="btn btn-register">Registro</a>
        <?php endif; ?>
    </div>
</header>

<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">🔥 Marketplace local · Aguascalientes</div>
        <h1>Conecta tu negocio con<br><em>miles de clientes</em></h1>
        <p>Compra, vende y descubre productos y servicios locales cerca de ti</p>
        <div class="search-wrap">
            <div class="search-inner">
                <div class="search-field">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="textoBusqueda" placeholder="¿Qué estás buscando?" autocomplete="off">
                </div>
                <select id="categoriaBusqueda">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-search" onclick="buscar()">Buscar</button>
            </div>
        </div>
        <div class="search-hints">
            <span class="search-hint" onclick="busquedaRapida('celular')">📱 Celulares</span>
            <span class="search-hint" onclick="busquedaRapida('ropa')">👗 Ropa</span>
            <span class="search-hint" onclick="busquedaRapida('laptop')">💻 Laptops</span>
            <span class="search-hint" onclick="busquedaRapida('muebles')">🪑 Muebles</span>
            <span class="search-hint" onclick="busquedaRapida('mascota')">🐾 Mascotas</span>
        </div>
    </div>
</section>

<!-- RESULTADOS -->
<section class="results-section" id="resultadosSection">
    <div style="max-width:1300px;margin:0 auto;padding:0 50px">
        <h2 class="section-title" id="resultadosTitulo">Resultados <span id="resultadosCount"></span></h2>
        <div class="results-grid" id="resultadosGrid"></div>
    </div>
</section>

<!-- CARRUSEL -->
<section class="carousel-section">
    <div class="section-header">
        <h2 class="section-title">Publicaciones <span>recientes</span></h2>
        <div class="carousel-controls">
            <button class="ctrl-btn" id="prevBtn">&#8592;</button>
            <button class="ctrl-btn" id="nextBtn">&#8594;</button>
        </div>
    </div>
    <?php if (!empty($carrusel)): ?>
        <!-- Las cards se renderizan por JS para el efecto de escala -->
        <div id="carouselStage" class="carousel-stage"></div>
        <div class="carousel-dots" id="carouselDots"></div>
        <script>
        // Datos desde PHP → JS
        const CARDS_DATA = <?= json_encode(array_map(fn($item) => [
            'id'       => $item['id'],
            'nombre'   => $item['nombre'],
            'tipo'     => $item['tipo'],
            'precio'   => $item['precio'],
            'vendedor' => $item['vendedor'] ?? 'Anónimo',
            'ubicacion'=> $item['ubicacion'] ?? '',
            'img_src'  => $item['img_src'],
            'cat_icono'=> $item['cat_icono'] ?? '📦',
        ], $carrusel)) ?>;
        </script>
    <?php else: ?>
        <div class="empty-state" style="text-align:center;padding:50px;color:var(--muted)">
            <div style="font-size:3rem;margin-bottom:10px">📭</div>
            <p>Aún no hay publicaciones. ¡Sé el primero en publicar!</p>
        </div>
    <?php endif; ?>
</section>

<!-- CATEGORÍAS -->
<section class="categories-section" id="categorias">
    <div class="section-header">
        <h2 class="section-title">Explorar por <span>categoría</span></h2>
    </div>
    <?php if (!empty($categorias)): ?>
        <div class="cat-grid">
            <?php foreach ($categorias as $cat): ?>
                <div class="cat-card" onclick="filtrarCategoria(<?= $cat['id'] ?>, '<?= addslashes($cat['nombre']) ?>')">
                    <div class="cat-icon"><?= htmlspecialchars($cat['icono'] ?? '📦') ?></div>
                    <div class="cat-name"><?= htmlspecialchars($cat['nombre']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:var(--muted)">No hay categorías configuradas.</p>
    <?php endif; ?>
</section>

<!-- DESTACADOS -->
<?php if (!empty($destacados)): ?>
<section class="destacados-section">
    <div class="section-header">
        <h2 class="section-title">Lo más <span>nuevo</span></h2>
        <?php if ($logueado): ?>
            <a href="productos/producto.php" class="btn btn-publish">+ Publicar algo</a>
        <?php endif; ?>
    </div>
    <div class="destacados-grid">
        <?php foreach ($destacados as $item): ?>
            <a href="productos/verproducto.php?id=<?= $item['id'] ?>" class="oferta-card">
                <?php if ($item['img_src']): ?>
                    <img class="oferta-img" src="<?= htmlspecialchars($item['img_src']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>" loading="lazy">
                <?php else: ?>
                    <div class="oferta-placeholder">📦</div>
                <?php endif; ?>
                <div class="oferta-body">
                    <span class="oferta-badge badge-<?= $item['tipo'] ?>"><?= ucfirst($item['tipo']) ?></span>
                    <?php if ($item['cat_nombre']): ?>
                        <div style="font-size:.7rem;color:var(--muted);margin-bottom:3px"><?= htmlspecialchars($item['cat_nombre']) ?></div>
                    <?php endif; ?>
                    <div class="oferta-nombre"><?= htmlspecialchars($item['nombre']) ?></div>
                    <?php if ($item['precio']): ?>
                        <div class="oferta-precio">$<?= number_format($item['precio'], 2) ?></div>
                    <?php endif; ?>
                    <div class="oferta-meta"><span>👤</span> <?= htmlspecialchars($item['vendedor'] ?? 'Anónimo') ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!$logueado): ?>
<div style="max-width:1300px;margin:0 auto;padding:0 50px 60px">
    <div class="cta-banner">
        <div class="cta-text">
            <h2>¿Tienes algo que vender?</h2>
            <p>Crea tu cuenta gratis y publica en menos de 2 minutos</p>
        </div>
        <a href="auth/register.html" class="btn-cta">Empieza gratis →</a>
    </div>
</div>
<?php endif; ?>

<footer class="footer">
    <p>© 2026 Bussimindness — Todos los derechos reservados</p>
</footer>

<script>
/* ══════════════════════════════════════════════════════
   CARRUSEL — 3 cards centradas con escala
   Lógica: siempre muestra máx 3 slots (izq, centro, der)
   El índice activo = card del centro
══════════════════════════════════════════════════════ */
(function() {
    if (typeof CARDS_DATA === 'undefined' || CARDS_DATA.length === 0) return;

    const stage    = document.getElementById('carouselStage');
    const dotsWrap = document.getElementById('carouselDots');
    const prevBtn  = document.getElementById('prevBtn');
    const nextBtn  = document.getElementById('nextBtn');
    const total    = CARDS_DATA.length;
    let   current  = 0;
    let   autoTimer;

    function cardHTML(item) {
        const img = item.img_src
            ? `<img class="oferta-img" src="${item.img_src}" alt="${item.nombre}" loading="lazy">`
            : `<div class="oferta-placeholder">${item.cat_icono}</div>`;
        const precio = item.precio
            ? `<div class="oferta-precio">$${parseFloat(item.precio).toLocaleString('es-MX',{minimumFractionDigits:2})}</div>`
            : '';
        const ubicacion = item.ubicacion ? ` · 📍 ${item.ubicacion}` : '';
        return `
            <a href="productos/verproducto.php?id=${item.id}" class="oferta-card">
                ${img}
                <div class="oferta-body">
                    <span class="oferta-badge badge-${item.tipo}">${item.tipo}</span>
                    <div class="oferta-nombre">${item.nombre}</div>
                    ${precio}
                    <div class="oferta-meta"><span>👤</span>${item.vendedor}${ubicacion}</div>
                </div>
            </a>`;
    }

    function render() {
        stage.innerHTML = '';

        if (total === 1) {
            // Solo 1: centrado sin lados
            const slot = document.createElement('div');
            slot.className = 'carousel-slot center';
            slot.innerHTML = cardHTML(CARDS_DATA[0]);
            stage.appendChild(slot);

        } else if (total === 2) {
            // Solo 2: izq y centro (o centro y der)
            const positions = [
                { idx: (current - 1 + total) % total, cls: 'side' },
                { idx: current,                         cls: 'center' },
            ];
            positions.forEach(p => {
                const slot = document.createElement('div');
                slot.className = `carousel-slot ${p.cls}`;
                slot.innerHTML = cardHTML(CARDS_DATA[p.idx]);
                stage.appendChild(slot);
            });

        } else {
            // 3 o más: mostrar izq, centro, der
            const positions = [
                { idx: (current - 1 + total) % total, cls: 'side' },
                { idx: current,                         cls: 'center' },
                { idx: (current + 1) % total,           cls: 'side' },
            ];
            positions.forEach(p => {
                const slot = document.createElement('div');
                slot.className = `carousel-slot ${p.cls}`;
                slot.innerHTML = cardHTML(CARDS_DATA[p.idx]);
                stage.appendChild(slot);
            });
        }

        // Actualizar dots
        document.querySelectorAll('.dot').forEach((d, i) => d.classList.toggle('active', i === current));

        // Botones
        if (total <= 1) {
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        } else {
            prevBtn.disabled = false;
            nextBtn.disabled = false;
        }
    }

    function goTo(i) {
        current = ((i % total) + total) % total;
        render();
    }

    // Dots
    for (let i = 0; i < total; i++) {
        const d = document.createElement('span');
        d.className = 'dot' + (i === 0 ? ' active' : '');
        d.onclick = () => { stopAuto(); goTo(i); startAuto(); };
        dotsWrap.appendChild(d);
    }

    prevBtn.addEventListener('click', () => { stopAuto(); goTo(current - 1); startAuto(); });
    nextBtn.addEventListener('click', () => { stopAuto(); goTo(current + 1); startAuto(); });

    function startAuto() { autoTimer = setInterval(() => goTo(current + 1), 4500); }
    function stopAuto()  { clearInterval(autoTimer); }

    stage.addEventListener('mouseenter', stopAuto);
    stage.addEventListener('mouseleave', startAuto);

    render();
    startAuto();
})();

/* ══════════════════════════════════════════════════════
   BÚSQUEDA AJAX
══════════════════════════════════════════════════════ */
let debounceTimer;

document.getElementById('textoBusqueda')
    .addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(buscar, 380);
    });

document.getElementById('categoriaBusqueda')
    .addEventListener('change', buscar);

function busquedaRapida(term) {
    document.getElementById('textoBusqueda').value = term;
    buscar();
}

function filtrarCategoria(id) {
    document.getElementById('categoriaBusqueda').value = id;
    document.getElementById('textoBusqueda').value = '';
    buscar();
    document.getElementById('resultadosSection').scrollIntoView({ behavior: 'smooth' });
}

function buscar() {
    const q     = document.getElementById('textoBusqueda').value.trim();
    const catId = document.getElementById('categoriaBusqueda').value;

    const section = document.getElementById('resultadosSection');
    const grid    = document.getElementById('resultadosGrid');
    const count   = document.getElementById('resultadosCount');

    if (!q && !catId) {
        section.classList.remove('visible');
        return;
    }

    section.classList.add('visible');
    grid.innerHTML = '<div style="padding:30px;text-align:center"><div class="spinner"></div></div>';
    count.textContent = '';

    const params = new URLSearchParams();
    if (q)     params.set('q', q);
    if (catId) params.set('categoria', catId);

    fetch('api/buscar.php?' + params.toString())
        .then(r => r.json())
        .then(data => {
            count.innerHTML = `<span>(${data.total})</span>`;

            if (!data.resultados || data.resultados.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <div class="icon">🔍</div>
                        <p>No encontramos resultados para "<strong>${q || 'esta búsqueda'}</strong>"</p>
                    </div>`;
                return;
            }

            grid.innerHTML = data.resultados.map(item => {
                // img_url es ruta relativa: uploads/ofertas/xxx.jpg
                const img = item.img_url
                    ? `<img class="oferta-img" src="${item.img_url}" alt="${item.nombre}" loading="lazy">`
                    : `<div class="oferta-placeholder">📦</div>`;

                const precio = item.precio
                    ? `<div class="oferta-precio">$${parseFloat(item.precio).toLocaleString('es-MX',{minimumFractionDigits:2})}</div>`
                    : '';

                return `
                    <a href="productos/verproducto.php?id=${item.id}" class="oferta-card">
                        ${img}
                        <div class="oferta-body">
                            <span class="oferta-badge badge-${item.tipo}">${item.tipo}</span>
                            <div class="oferta-nombre">${item.nombre}</div>
                            ${precio}
                            <div class="oferta-meta">👤 ${item.vendedor || 'Anónimo'}</div>
                        </div>
                    </a>`;
            }).join('');

            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        })
        .catch(err => {
            console.error(err);
            grid.innerHTML = '<p style="color:var(--muted);padding:20px">Error al buscar. Intenta de nuevo.</p>';
        });
}

// Búsqueda por URL param ?categoria=X
const usp = new URLSearchParams(window.location.search);
if (usp.get('categoria')) {
    document.getElementById('categoriaBusqueda').value = usp.get('categoria');
    buscar();
}
</script>
</body>
</html>