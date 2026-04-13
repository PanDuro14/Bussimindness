<?php
// ============================================================
//  lib/auth_middleware.php
//  Protege endpoints de API que usan JWT
//  Uso: $payload = requireAuth($pdo);
// ============================================================
require_once __DIR__ . '/JWT.php';

function requireAuth(PDO $pdo, string $rolMinimo = ''): array {
    $headers    = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token requerido']);
        exit;
    }

    $payload = JWT::verify($m[1]);
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Token inválido o expirado']);
        exit;
    }

    // Verificar blacklist
    if (JWT::isRevoked($payload['jti'], $pdo)) {
        http_response_code(401);
        echo json_encode(['error' => 'Token revocado']);
        exit;
    }

    // Verificar sesión activa en BD
    if (!empty($payload['session_uuid'])) {
        $s = $pdo->prepare(
            "SELECT id FROM sesiones
             WHERE session_uuid = ? AND activa = 1
             AND (expira_en IS NULL OR expira_en > NOW())"
        );
        $s->execute([$payload['session_uuid']]);
        if (!$s->fetch()) {
            http_response_code(401);
            echo json_encode(['error' => 'Sesión cerrada o expirada']);
            exit;
        }
        // Actualizar actividad
        $pdo->prepare("UPDATE sesiones SET ultima_actividad = NOW() WHERE session_uuid = ?")
            ->execute([$payload['session_uuid']]);
    }

    // Verificar rol
    if ($rolMinimo) {
        $jerarquia = ['usuario'=>1,'editor'=>2,'admin'=>3];
        $userNivel = $jerarquia[$payload['role'] ?? 'usuario'] ?? 0;
        $minNivel  = $jerarquia[$rolMinimo] ?? 999;
        if ($userNivel < $minNivel) {
            http_response_code(403);
            echo json_encode(['error' => "Rol '$rolMinimo' requerido"]);
            exit;
        }
    }

    return $payload;
}

function requireAdmin(PDO $pdo): array {
    return requireAuth($pdo, 'admin');
}