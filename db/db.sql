-- ============================================================
--  BUSSIMINDNESS — Base de datos completa
--  Marketplace tipo Facebook Marketplace
--  Versión: 2.0
--  Fecha: 2026
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `bussimindness`;
CREATE DATABASE `bussimindness`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `bussimindness`;

-- ============================================================
--  TABLA: categorias
--  Las categorías de las publicaciones
-- ============================================================
CREATE TABLE `categorias` (
    `id`      INT(11)      NOT NULL AUTO_INCREMENT,
    `nombre`  VARCHAR(100) NOT NULL,
    `icono`   VARCHAR(10)  DEFAULT '📦',
    `slug`    VARCHAR(100) DEFAULT NULL,        -- para URLs amigables ej: "tecnologia"
    `estado`  ENUM('activo','inactivo') DEFAULT 'activo',
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categorias` (`nombre`, `icono`, `slug`, `estado`) VALUES
('Tecnología',       '💻', 'tecnologia',       'activo'),
('Reparaciones',     '🔧', 'reparaciones',     'activo'),
('Salud & Bienestar','❤️', 'salud',            'activo'),
('Hogar',            '🏠', 'hogar',            'activo'),
('Ropa & Accesorios','👟', 'ropa',             'activo'),
('Mascotas',         '🐾', 'mascotas',         'activo'),
('Vehículos',        '🚗', 'vehiculos',        'activo'),
('Fotografía',       '📸', 'fotografia',       'activo'),
('Comida',           '🍔', 'comida',           'activo'),
('Música',           '🎵', 'musica',           'activo'),
('Deportes',         '⚽', 'deportes',         'activo'),
('Educación',        '📚', 'educacion',        'activo');


-- ============================================================
--  TABLA: usuarios
--  Compradores y vendedores (mismo usuario puede ser ambos)
-- ============================================================
CREATE TABLE `usuarios` (
    `id`              INT(11)      NOT NULL AUTO_INCREMENT,
    `nombre`          VARCHAR(100) NOT NULL,
    `email`           VARCHAR(150) NOT NULL,
    `password`        VARCHAR(255) NOT NULL,           -- bcrypt hash
    `foto`            VARCHAR(255) DEFAULT NULL,       -- ruta: uploads/usuarios/foto.jpg
    `bio`             TEXT         DEFAULT NULL,       -- descripción del vendedor
    `ciudad`          VARCHAR(100) DEFAULT NULL,
    `telefono`        VARCHAR(20)  DEFAULT NULL,
    `rol`             ENUM('usuario','admin') DEFAULT 'usuario',
    `estado`          ENUM('activo','bloqueado') DEFAULT 'activo',
    `fecha_registro`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario admin de prueba (password: Admin1234)
INSERT INTO `usuarios` (`nombre`, `email`, `password`, `rol`, `estado`) VALUES
('Admin Bussimindness', 'admin@bussimindness.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin', 'activo');


-- ============================================================
--  TABLA: ofertas
--  Publicaciones de productos y servicios
--  Imágenes como ARCHIVOS (ruta), no como BLOB
-- ============================================================
CREATE TABLE `ofertas` (
    `id`               INT(11)         NOT NULL AUTO_INCREMENT,
    `idUsuario`        INT(11)         NOT NULL,
    `categoria_id`     INT(11)         DEFAULT NULL,
    `nombre`           VARCHAR(200)    NOT NULL,
    `tipo`             ENUM('producto','servicio') NOT NULL DEFAULT 'producto',
    `descripcion`      TEXT            DEFAULT NULL,
    `precio`           DECIMAL(10,2)   DEFAULT NULL,
    `ubicacion`        VARCHAR(150)    DEFAULT NULL,
    `imagen`           VARCHAR(255)    DEFAULT NULL,   -- ruta al archivo: uploads/ofertas/abc.jpg
    `estado`           ENUM('activo','vendido','pausado','eliminado') DEFAULT 'activo',
    `condicion`        ENUM('nuevo','usado_buen_estado','usado_regular') DEFAULT NULL,
    `vistas`           INT(11)         DEFAULT 0,
    `fecha_publicacion` DATETIME       DEFAULT CURRENT_TIMESTAMP,
    `fecha_actualizacion` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_usuario`   (`idUsuario`),
    KEY `idx_categoria` (`categoria_id`),
    KEY `idx_estado`    (`estado`),
    KEY `idx_fecha`     (`fecha_publicacion`),
    CONSTRAINT `fk_oferta_usuario`   FOREIGN KEY (`idUsuario`)    REFERENCES `usuarios`   (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oferta_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: oferta_imagenes
--  Múltiples imágenes por oferta (la principal está en ofertas.imagen)
-- ============================================================
CREATE TABLE `oferta_imagenes` (
    `id`        INT(11)      NOT NULL AUTO_INCREMENT,
    `oferta_id` INT(11)      NOT NULL,
    `ruta`      VARCHAR(255) NOT NULL,
    `orden`     INT(11)      DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_oferta` (`oferta_id`),
    CONSTRAINT `fk_img_oferta` FOREIGN KEY (`oferta_id`) REFERENCES `ofertas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: conversaciones
--  Un hilo de chat por oferta + par de usuarios
-- ============================================================
CREATE TABLE `conversaciones` (
    `id`           INT(11)  NOT NULL AUTO_INCREMENT,
    `oferta_id`    INT(11)  DEFAULT NULL,
    `comprador_id` INT(11)  NOT NULL,
    `vendedor_id`  INT(11)  NOT NULL,
    `fecha_inicio` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `ultimo_mensaje` DATETIME DEFAULT NULL,       -- para ordenar por actividad
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_conv` (`oferta_id`, `comprador_id`, `vendedor_id`),  -- evitar duplicados
    KEY `idx_comprador` (`comprador_id`),
    KEY `idx_vendedor`  (`vendedor_id`),
    CONSTRAINT `fk_conv_oferta`    FOREIGN KEY (`oferta_id`)    REFERENCES `ofertas`   (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_conv_comprador` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conv_vendedor`  FOREIGN KEY (`vendedor_id`)  REFERENCES `usuarios`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: mensajes
--  Mensajes dentro de una conversación
-- ============================================================
CREATE TABLE `mensajes` (
    `id`               INT(11)  NOT NULL AUTO_INCREMENT,
    `conversacion_id`  INT(11)  NOT NULL,
    `remitente_id`     INT(11)  NOT NULL,
    `mensaje`          TEXT     NOT NULL,
    `leido`            TINYINT(1) DEFAULT 0,
    `fecha`            DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_conv`      (`conversacion_id`),
    KEY `idx_remitente` (`remitente_id`),
    KEY `idx_fecha`     (`fecha`),
    CONSTRAINT `fk_msg_conv`      FOREIGN KEY (`conversacion_id`) REFERENCES `conversaciones` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_msg_remitente` FOREIGN KEY (`remitente_id`)    REFERENCES `usuarios`       (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: resenas
--  Calificaciones de vendedores (1-5 estrellas)
-- ============================================================
CREATE TABLE `resenas` (
    `id`           INT(11)  NOT NULL AUTO_INCREMENT,
    `vendedor_id`  INT(11)  NOT NULL,
    `comprador_id` INT(11)  NOT NULL,
    `oferta_id`    INT(11)  DEFAULT NULL,
    `puntuacion`   TINYINT  NOT NULL CHECK (`puntuacion` BETWEEN 1 AND 5),
    `comentario`   TEXT     DEFAULT NULL,
    `fecha`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resena` (`vendedor_id`, `comprador_id`, `oferta_id`),  -- 1 reseña por transacción
    KEY `idx_vendedor` (`vendedor_id`),
    CONSTRAINT `fk_res_vendedor`  FOREIGN KEY (`vendedor_id`)  REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_res_comprador` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_res_oferta`    FOREIGN KEY (`oferta_id`)    REFERENCES `ofertas`  (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: favoritos
--  Usuarios pueden guardar ofertas que les interesan
-- ============================================================
CREATE TABLE `favoritos` (
    `id`         INT(11)  NOT NULL AUTO_INCREMENT,
    `usuario_id` INT(11)  NOT NULL,
    `oferta_id`  INT(11)  NOT NULL,
    `fecha`      DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fav` (`usuario_id`, `oferta_id`),
    CONSTRAINT `fk_fav_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fav_oferta`  FOREIGN KEY (`oferta_id`)  REFERENCES `ofertas`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: reportes
--  Para moderar contenido inapropiado
-- ============================================================
CREATE TABLE `reportes` (
    `id`          INT(11)      NOT NULL AUTO_INCREMENT,
    `oferta_id`   INT(11)      NOT NULL,
    `usuario_id`  INT(11)      NOT NULL,
    `motivo`      ENUM('spam','inapropiado','fraude','duplicado','otro') NOT NULL,
    `descripcion` TEXT         DEFAULT NULL,
    `estado`      ENUM('pendiente','revisado','resuelto') DEFAULT 'pendiente',
    `fecha`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oferta`  (`oferta_id`),
    KEY `idx_estado`  (`estado`),
    CONSTRAINT `fk_rep_oferta`  FOREIGN KEY (`oferta_id`)  REFERENCES `ofertas`  (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rep_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  TABLA: errores
--  Log de errores del sistema (ya la tenías)
-- ============================================================
CREATE TABLE `errores` (
    `id`      INT(11)      NOT NULL AUTO_INCREMENT,
    `codigo`  INT(11)      DEFAULT NULL,
    `mensaje` VARCHAR(255) DEFAULT NULL,
    `url`     VARCHAR(255) DEFAULT NULL,
    `fecha`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  VISTA: vista_ofertas
--  Joins frecuentes pre-calculados para facilitar consultas
-- ============================================================
CREATE VIEW `vista_ofertas` AS
SELECT
    o.id,
    o.nombre,
    o.tipo,
    o.descripcion,
    o.precio,
    o.ubicacion,
    o.imagen,
    o.estado,
    o.condicion,
    o.vistas,
    o.fecha_publicacion,
    o.idUsuario         AS usuario_id,
    u.nombre            AS vendedor_nombre,
    u.foto              AS vendedor_foto,
    u.ciudad            AS vendedor_ciudad,
    c.id                AS categoria_id,
    c.nombre            AS categoria_nombre,
    c.icono             AS categoria_icono
FROM `ofertas` o
LEFT JOIN `usuarios`   u ON o.idUsuario    = u.id
LEFT JOIN `categorias` c ON o.categoria_id = c.id
WHERE o.estado != 'eliminado';


-- ============================================================
--  CARPETAS necesarias en el servidor (crear manualmente)
--
--  uploads/
--  └── ofertas/     ← imágenes de publicaciones
--  └── usuarios/    ← fotos de perfil
--
--  En PHP para subir imagen:
--    $destino = "../uploads/ofertas/" . uniqid() . ".jpg";
--    move_uploaded_file($_FILES['imagen']['tmp_name'], $destino);
--    // Guardar $destino en ofertas.imagen
-- ============================================================


-- ============================================================
--  NOTAS DE SEGURIDAD
--
--  1. CONTRASEÑAS — usar en register.php:
--       $hash = password_hash($password, PASSWORD_DEFAULT);
--     y en login.php:
--       if (password_verify($input, $hash)) { ... }
--
--  2. CONSULTAS — SIEMPRE usar prepared statements:
--       $stmt = $conn->prepare("SELECT * FROM ofertas WHERE id = ?");
--       $stmt->bind_param("i", $id);
--
--  3. IMÁGENES — validar tipo y tamaño antes de guardar:
--       $permitidos = ['image/jpeg','image/png','image/webp'];
--       if (!in_array($_FILES['img']['type'], $permitidos)) { ... }
--       if ($_FILES['img']['size'] > 5 * 1024 * 1024) { ... } // máx 5MB
-- ============================================================