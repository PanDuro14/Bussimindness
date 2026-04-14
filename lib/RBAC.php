<?php
// ============================================================
//  lib/RBAC.php  —  Control de Acceso Basado en Roles
//  Roles: usuario < editor < admin
// ============================================================
class RBAC {

    private static array $hierarchy = [
        'usuario' => 1,
        'editor'  => 2,
        'admin'   => 3,
    ];

    private static array $permissions = [
        'admin' => [
            'ver_usuarios','editar_usuarios','eliminar_usuarios','gestionar_roles',
            'ver_productos','editar_productos','eliminar_productos',
            'ver_logs','ver_sesiones_todas','gestionar_reportes',
        ],
        'editor' => [
            'ver_productos','editar_productos',
            'ver_usuarios',
        ],
        'usuario' => [
            'ver_productos',
        ],
    ];

    // ── Verificar permiso ───────────────────────────────────
    public static function can(string $rol, string $permiso): bool {
        return in_array($permiso, self::$permissions[$rol] ?? [], true);
    }

    // ── Requerir permiso (o abortar 403) ───────────────────
    public static function require(array $user, string $permiso): void {
        $rol = $user['rol'] ?? $user['role'] ?? 'usuario';
        if (!self::can($rol, $permiso)) {
            if (!headers_sent()) header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado', 'permiso_requerido' => $permiso]);
            exit;
        }
    }

    // ── Requerir rol mínimo ─────────────────────────────────
    public static function requireRole(array $user, string $rolMinimo): void {
        $rol       = $user['rol'] ?? $user['role'] ?? 'usuario';
        $nivelUser = self::$hierarchy[$rol] ?? 0;
        $nivelMin  = self::$hierarchy[$rolMinimo] ?? 999;
        if ($nivelUser < $nivelMin) {
            if (!headers_sent()) header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => "Se requiere rol '$rolMinimo' o superior"]);
            exit;
        }
    }

    // ── Menú de navegación dinámico por rol ────────────────
    public static function getNavMenu(string $rol): array {
        $menus = [
            'usuario' => [
                ['label'=>'Inicio',         'url'=>'/index.php',         'icon'=>'🏠'],
                ['label'=>'Productos',       'url'=>'/productos/',        'icon'=>'📦'],
                ['label'=>'Mis mensajes',    'url'=>'/mensajes/chat.php', 'icon'=>'💬'],
                ['label'=>'Mi perfil',       'url'=>'/perfil/perfil.php', 'icon'=>'👤'],
                ['label'=>'Configuración',   'url'=>'/perfil/config.php', 'icon'=>'⚙️'],
            ],
            'editor' => [
                ['label'=>'Inicio',         'url'=>'/index.php',                'icon'=>'🏠'],
                ['label'=>'Productos',       'url'=>'/productos/',               'icon'=>'📦'],
                ['label'=>'Mis mensajes',    'url'=>'/mensajes/chat.php',        'icon'=>'💬'],
                ['label'=>'Gestionar prod.', 'url'=>'/productos/editar_producto.php','icon'=>'✏️'],
                ['label'=>'Mi perfil',       'url'=>'/perfil/perfil.php',        'icon'=>'👤'],
            ],
            'admin' => [
                ['label'=>'Dashboard',       'url'=>'/admin/',                   'icon'=>'📊'],
                ['label'=>'Usuarios',        'url'=>'/admin/usuarios.php',       'icon'=>'👥'],
                ['label'=>'Productos',       'url'=>'/admin/productos.php',      'icon'=>'📦'],
                ['label'=>'Sesiones',        'url'=>'/admin/sesiones.php',       'icon'=>'🔐'],
                ['label'=>'Logs',            'url'=>'/admin/logs.php',           'icon'=>'📋'],
                ['label'=>'Mi perfil',       'url'=>'/perfil/perfil.php',        'icon'=>'👤'],
            ],
        ];
        return $menus[$rol] ?? $menus['usuario'];
    }

    // ── Para usar en vistas PHP (no API) ───────────────────
    public static function checkSession(string $rolMinimo = 'usuario'): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            header('Location: /auth/login.php');
            exit;
        }
        $nivelUser = self::$hierarchy[$_SESSION['user_rol'] ?? 'usuario'] ?? 0;
        $nivelMin  = self::$hierarchy[$rolMinimo] ?? 0;
        if ($nivelUser < $nivelMin) {
            http_response_code(403);
            die('<h1>403 — Acceso denegado</h1>');
        }
        return [
            'id'     => $_SESSION['user_id'],
            'nombre' => $_SESSION['user_nombre'],
            'rol'    => $_SESSION['user_rol'],
        ];
    }
}