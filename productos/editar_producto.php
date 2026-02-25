<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Traer el producto (verificar que pertenece al usuario)
$stmt = $conn->prepare("
    SELECT o.*, c.nombre AS cat_nombre
    FROM ofertas o
    LEFT JOIN categorias c ON o.categoria_id = c.id
    WHERE o.id = ? AND o.idUsuario = ?
    LIMIT 1
");
$stmt->bind_param("ii", $idp, $user_id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) {
    die("<p style='font-family:sans-serif;padding:40px'>Publicación no encontrada o no tienes permiso.</p>");
}

// Cargar categorías
$categorias = [];
$res = $conn->query("SELECT id, nombre, icono FROM categorias WHERE estado = 'activo' ORDER BY nombre");
while ($row = $res->fetch_assoc()) $categorias[] = $row;

$conn->close();

// Imagen actual
$img_src = null;
if (!empty($prod['imagen']) && file_exists(__DIR__ . '/../' . $prod['imagen'])) {
    $img_src = '../' . $prod['imagen'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar publicación | Bussimindness</title>
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
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background: var(--bg); color: var(--text); }
        a { text-decoration: none; color: inherit; }

        .header {
            background: var(--navy); height: 68px;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 50px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .logo { font-weight: 700; font-size: 1.2rem; color: white; }
        .logo span { color: var(--orange); }
        .header a { color: rgba(255,255,255,.7); font-size: .875rem; }
        .header a:hover { color: white; }

        .container { max-width: 600px; margin: 40px auto; padding: 0 20px 60px; }
        .page-title { font-size: 1.4rem; font-weight: 700; color: var(--navy); margin-bottom: 24px; }

        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }

        .campo { margin-bottom: 18px; }
        .campo label { display: block; font-size: .82rem; font-weight: 600; color: var(--navy); margin-bottom: 6px; }
        .campo label span { color: var(--orange); }
        .campo input,
        .campo textarea,
        .campo select {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: .9rem;
            color: var(--text); background: var(--white);
            outline: none; transition: border-color .2s;
        }
        .campo input:focus,
        .campo textarea:focus,
        .campo select:focus { border-color: var(--navy); }
        .campo textarea { resize: vertical; min-height: 100px; }

        .fila-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 18px; }
        .fila-dos .campo { margin-bottom: 0; }

        /* Imagen actual */
        .img-actual {
            width: 100%; max-height: 220px; object-fit: cover;
            border-radius: 8px; margin-bottom: 10px; display: block;
        }
        .img-placeholder-edit {
            width: 100%; height: 140px;
            background: linear-gradient(135deg, #e8edf5, #d1dae8);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-size: 2.5rem; margin-bottom: 10px;
        }
        .img-label { font-size: .78rem; color: var(--muted); margin-bottom: 8px; }

        .upload-area {
            border: 2px dashed var(--border); border-radius: 10px;
            padding: 20px; text-align: center; cursor: pointer;
            transition: border-color .2s, background .2s; position: relative;
        }
        .upload-area:hover { border-color: var(--navy); background: #f8fafc; }
        .upload-area input[type="file"] {
            position: absolute; inset: 0; opacity: 0;
            cursor: pointer; width: 100%; height: 100%;
        }
        .upload-text { font-size: .82rem; color: var(--muted); }
        .upload-text strong { color: var(--navy); }
        #preview-nueva { display: none; width: 100%; max-height: 180px; object-fit: cover; border-radius: 8px; margin-top: 10px; }

        /* Estado */
        .estado-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .estado-btn {
            padding: 8px; border-radius: 8px; border: 1.5px solid var(--border);
            background: var(--white); font-family: 'Poppins', sans-serif;
            font-size: .78rem; font-weight: 600; cursor: pointer;
            text-align: center; transition: all .2s; color: var(--muted);
        }
        .estado-btn.selected { border-color: var(--navy); background: var(--navy); color: white; }
        .estado-btn:hover:not(.selected) { border-color: var(--navy); color: var(--navy); }

        .divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }

        .acciones { display: flex; gap: 10px; }
        .btn-guardar {
            flex: 1; padding: 13px; background: var(--orange); color: white;
            border: none; border-radius: 8px; font-family: 'Poppins', sans-serif;
            font-size: 1rem; font-weight: 700; cursor: pointer; transition: background .2s;
        }
        .btn-guardar:hover { background: var(--orange2); }
        .btn-cancelar {
            padding: 13px 24px; background: transparent; color: var(--muted);
            border: 1.5px solid var(--border); border-radius: 8px;
            font-family: 'Poppins', sans-serif; font-size: .9rem; font-weight: 600;
            cursor: pointer; transition: all .2s; text-decoration: none;
            display: flex; align-items: center;
        }
        .btn-cancelar:hover { border-color: var(--text); color: var(--text); }
    </style>
</head>
<body>

<header class="header">
    <div class="logo">Bussim<span>i</span>ndness</div>
    <div style="display:flex;gap:20px">
        <a href="../index.php">← Inicio</a>
        <a href="../perfil/perfil.php">Mi perfil</a>
    </div>
</header>

<div class="container">
    <h1 class="page-title">✏️ Editar publicación</h1>

    <div class="form-card">
        <form action="actualizar_producto.php?id=<?= $idp ?>" method="POST" enctype="multipart/form-data">

            <!-- NOMBRE -->
            <div class="campo">
                <label>Nombre <span>*</span></label>
                <input type="text" name="nombre"
                       value="<?= htmlspecialchars($prod['nombre']) ?>"
                       required maxlength="200">
            </div>

            <!-- DESCRIPCIÓN -->
            <div class="campo">
                <label>Descripción</label>
                <textarea name="descripcion"><?= htmlspecialchars($prod['descripcion'] ?? '') ?></textarea>
            </div>

            <!-- TIPO Y CATEGORÍA -->
            <div class="fila-dos">
                <div class="campo">
                    <label>Tipo <span>*</span></label>
                    <select name="tipo" required>
                        <option value="producto" <?= $prod['tipo'] === 'producto' ? 'selected' : '' ?>>📦 Producto</option>
                        <option value="servicio" <?= $prod['tipo'] === 'servicio' ? 'selected' : '' ?>>🛠️ Servicio</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Categoría</label>
                    <select name="categoria_id">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= $prod['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- PRECIO Y UBICACIÓN -->
            <div class="fila-dos">
                <div class="campo">
                    <label>Precio</label>
                    <input type="number" step="0.01" min="0" name="precio"
                           value="<?= htmlspecialchars($prod['precio'] ?? '') ?>"
                           placeholder="0.00">
                </div>
                <div class="campo">
                    <label>Ubicación</label>
                    <input type="text" name="ubicacion"
                           value="<?= htmlspecialchars($prod['ubicacion'] ?? '') ?>"
                           placeholder="Ej: Centro, Aguascalientes">
                </div>
            </div>

            <!-- CONDICIÓN -->
            <div class="campo" id="campo-condicion">
                <label>Condición</label>
                <select name="condicion">
                    <option value="">Selecciona...</option>
                    <option value="nuevo" <?= $prod['condicion'] === 'nuevo' ? 'selected' : '' ?>>✨ Nuevo</option>
                    <option value="usado_buen_estado" <?= $prod['condicion'] === 'usado_buen_estado' ? 'selected' : '' ?>>👍 Usado — buen estado</option>
                    <option value="usado_regular" <?= $prod['condicion'] === 'usado_regular' ? 'selected' : '' ?>>⚠️ Usado — estado regular</option>
                </select>
            </div>

            <!-- ESTADO -->
            <div class="campo">
                <label>Estado de la publicación</label>
                <div class="estado-grid">
                    <?php foreach (['activo' => '✅ Activo', 'pausado' => '⏸️ Pausado', 'vendido' => '✔️ Vendido'] as $val => $label): ?>
                        <button type="button"
                                class="estado-btn <?= $prod['estado'] === $val ? 'selected' : '' ?>"
                                onclick="setEstado('<?= $val ?>', this)">
                            <?= $label ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="estado" id="estadoInput" value="<?= htmlspecialchars($prod['estado']) ?>">
            </div>

            <hr class="divider">

            <!-- IMAGEN ACTUAL -->
            <div class="campo">
                <label>Imagen</label>
                <?php if ($img_src): ?>
                    <p class="img-label">Imagen actual:</p>
                    <img src="<?= htmlspecialchars($img_src) ?>"
                         alt="Imagen actual" class="img-actual" id="imgActual">
                <?php else: ?>
                    <div class="img-placeholder-edit">📦</div>
                <?php endif; ?>

                <p class="img-label">Subir nueva imagen (opcional — reemplaza la actual):</p>
                <div class="upload-area">
                    <input type="file" name="imagen" accept="image/*"
                           onchange="previsualizarNueva(this)">
                    <div class="upload-text">
                        <strong>Haz clic para subir</strong> o arrastra aquí<br>
                        JPG, PNG, WEBP — máx 5MB
                    </div>
                </div>
                <img id="preview-nueva" alt="Nueva imagen">
            </div>

            <hr class="divider">

            <div class="acciones">
                <a href="../perfil/perfil.php" class="btn-cancelar">Cancelar</a>
                <button type="submit" class="btn-guardar">Guardar cambios →</button>
            </div>

        </form>
    </div>
</div>

<script>
function setEstado(val, btn) {
    document.querySelectorAll('.estado-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('estadoInput').value = val;
}

function previsualizarNueva(input) {
    const preview  = document.getElementById('preview-nueva');
    const imgActual = document.getElementById('imgActual');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (imgActual) imgActual.style.opacity = '.4';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Mostrar/ocultar condición según tipo
const tipoSelect = document.querySelector('select[name="tipo"]');
const campoCondicion = document.getElementById('campo-condicion');
tipoSelect.addEventListener('change', function() {
    campoCondicion.style.display = this.value === 'producto' ? 'block' : 'none';
});
campoCondicion.style.display = tipoSelect.value === 'producto' ? 'block' : 'none';
</script>

</body>
</html>