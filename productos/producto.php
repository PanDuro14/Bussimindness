<?php
session_start();
include(__DIR__ . "/../config/conexion.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.html");
    exit();
}

// Cargar categorías desde BD
$categorias = [];
$res = $conn->query("SELECT id, nombre, icono FROM categorias WHERE estado = 'activo' ORDER BY nombre");
while ($row = $res->fetch_assoc()) $categorias[] = $row;
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publicar | Bussimindness</title>
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

        /* HEADER */
        .header {
            background: var(--navy); height: 68px;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 50px; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .logo { font-weight: 700; font-size: 1.2rem; color: white; }
        .logo span { color: var(--orange); }
        .header a { color: rgba(255,255,255,.7); font-size: .875rem; text-decoration: none; }
        .header a:hover { color: white; }

        /* CONTENEDOR */
        .container {
            max-width: 580px; margin: 40px auto; padding: 0 20px 60px;
        }
        .page-title {
            font-size: 1.4rem; font-weight: 700;
            color: var(--navy); margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
        }

        /* FORM CARD */
        .form-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1.5px solid var(--border);
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
        }

        /* CAMPO */
        .campo { margin-bottom: 18px; }
        .campo label {
            display: block;
            font-size: .82rem; font-weight: 600;
            color: var(--navy); margin-bottom: 6px;
        }
        .campo label span { color: var(--orange); }

        .campo input,
        .campo textarea,
        .campo select {
            width: 100%; padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: .9rem; color: var(--text);
            background: var(--white);
            outline: none;
            transition: border-color .2s;
        }
        .campo input:focus,
        .campo textarea:focus,
        .campo select:focus { border-color: var(--navy); }
        .campo textarea { resize: vertical; min-height: 100px; }

        /* FILA DOS COLUMNAS */
        .fila-dos {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
            margin-bottom: 18px;
        }
        .fila-dos .campo { margin-bottom: 0; }

        /* IMAGEN UPLOAD */
        .upload-area {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .upload-area:hover { border-color: var(--navy); background: #f8fafc; }
        .upload-area input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer; width: 100%; height: 100%;
        }
        .upload-icon { font-size: 2rem; margin-bottom: 8px; }
        .upload-text { font-size: .85rem; color: var(--muted); }
        .upload-text strong { color: var(--navy); }
        #preview-img {
            display: none; width: 100%; max-height: 200px;
            object-fit: cover; border-radius: 8px; margin-top: 12px;
        }

        /* BOTÓN */
        .btn-submit {
            width: 100%; padding: 13px;
            background: var(--orange); color: white;
            border: none; border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background .2s, transform .15s;
            margin-top: 8px;
        }
        .btn-submit:hover { background: var(--orange2); transform: translateY(-1px); }

        /* HINT */
        .hint { font-size: .75rem; color: var(--muted); margin-top: 4px; }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="header">
    <div class="logo">Bussim<span>i</span>ndness</div>
    <div style="display:flex;gap:20px;">
        <a href="../index.php">← Inicio</a>
        <a href="../perfil/perfil.php">Mi perfil</a>
    </div>
</header>

<div class="container">
    <h1 class="page-title">📢 Nueva publicación</h1>

    <div class="form-card">
        <form action="guardar_producto.php" method="POST" enctype="multipart/form-data">

            <!-- NOMBRE -->
            <div class="campo">
                <label>Nombre <span>*</span></label>
                <input type="text" name="nombre"
                       placeholder="Ej: iPhone 14, Clases de inglés, Sillón de oficina"
                       required maxlength="200">
            </div>

            <!-- DESCRIPCIÓN -->
            <div class="campo">
                <label>Descripción <span>*</span></label>
                <textarea name="descripcion"
                          placeholder="Describe tu producto o servicio con detalle..."
                          required></textarea>
            </div>

            <!-- TIPO Y CATEGORÍA -->
            <div class="fila-dos">
                <div class="campo">
                    <label>Tipo <span>*</span></label>
                    <select name="tipo" required>
                        <option value="">Selecciona...</option>
                        <option value="producto">📦 Producto</option>
                        <option value="servicio">🛠️ Servicio</option>
                    </select>
                </div>
                <div class="campo">
                    <label>Categoría <span>*</span></label>
                    <select name="categoria_id" required>
                        <option value="">Selecciona...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- PRECIO Y UBICACIÓN -->
            <div class="fila-dos">
                <div class="campo">
                    <label>Precio <span>*</span></label>
                    <input type="number" step="0.01" min="0"
                           name="precio" placeholder="0.00" required>
                    <p class="hint">En pesos mexicanos</p>
                </div>
                <div class="campo">
                    <label>Ubicación</label>
                    <input type="text" name="ubicacion"
                           placeholder="Ej: Centro, Aguascalientes">
                </div>
            </div>

            <!-- CONDICIÓN (solo para productos) -->
            <div class="campo" id="campo-condicion">
                <label>Condición</label>
                <select name="condicion">
                    <option value="">Selecciona...</option>
                    <option value="nuevo">✨ Nuevo</option>
                    <option value="usado_buen_estado">👍 Usado — buen estado</option>
                    <option value="usado_regular">⚠️ Usado — estado regular</option>
                </select>
            </div>

            <!-- IMAGEN -->
            <div class="campo">
                <label>Imagen <span>*</span></label>
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="imagen" accept="image/*"
                           required id="inputImagen"
                           onchange="previsualizarImagen(this)">
                    <div id="upload-placeholder">
                        <div class="upload-icon">🖼️</div>
                        <div class="upload-text">
                            <strong>Haz clic para subir</strong> o arrastra aquí<br>
                            JPG, PNG, WEBP — máx 5MB
                        </div>
                    </div>
                    <img id="preview-img" alt="Vista previa">
                </div>
            </div>

            <button type="submit" class="btn-submit">Publicar ahora →</button>

        </form>
    </div>
</div>

<script>
// Mostrar/ocultar condición según tipo
document.querySelector('select[name="tipo"]').addEventListener('change', function() {
    document.getElementById('campo-condicion').style.display =
        this.value === 'producto' ? 'block' : 'none';
});
// Ocultar condición al inicio si es servicio
document.getElementById('campo-condicion').style.display = 'none';

// Preview de imagen
function previsualizarImagen(input) {
    const preview = document.getElementById('preview-img');
    const placeholder = document.getElementById('upload-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
            placeholder.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>