<?php
// ============================================================
//  lib/Security.php
//  Utilidades de seguridad 
// ============================================================
class Security {

    // ── IP real del cliente (detecta proxies) ──────────────
    public static function getClientIp(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '0.0.0.0';
    }

    // ── Detección de fuerza bruta (>10 intentos / 15 min) ──
    public static function isBruteForce(string $ip, PDO $pdo): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM logs_seguridad
             WHERE ip = ? AND evento IN ('login_fallido','mfa_fallido')
             AND creado_en > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() >= 10;
    }

    // ── Registro de evento de seguridad ────────────────────
    public static function log(PDO $pdo, ?int $userId, string $evento,
                               string $ip = '', string $ua = '', string $detalle = ''): void {
        try {
            $pdo->prepare(
                "INSERT INTO logs_seguridad (usuario_id, evento, ip, user_agent, detalle)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$userId, $evento, $ip, substr($ua, 0, 512), $detalle]);
        } catch (PDOException $e) { /* no interrumpir el flujo */ }
    }

    // ── Sanitizar texto (anti-XSS) ─────────────────────────
    public static function sanitize(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── Validar contraseña segura ───────────────────────────
    // Mínimo: 8 chars, mayúscula, minúscula, número, símbolo
    public static function isStrongPassword(string $password): bool {
        return (bool)preg_match(
            '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/',
            $password
        );
    }

    // ── Hash con Argon2id (preferido) o bcrypt ─────────────
    public static function hashPassword(string $password): string {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost'   => 4,
                'threads'     => 2,
            ]);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Token aleatorio seguro (hex) ────────────────────────
    public static function generateToken(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }

    // ── OTP de 6 dígitos ───────────────────────────────────
    public static function generateOtp(): string {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // ── Headers HTTP de seguridad ───────────────────────────
    public static function setSecurityHeaders(): void {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline'");
        }
    }

    // ── CSRF: generar token de sesión ───────────────────────
    public static function csrfGenerate(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken(32);
        }
        return $_SESSION['csrf_token'];
    }

    // ── CSRF: verificar token ───────────────────────────────
    public static function csrfVerify(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    // ── Prevención Session Fixation ─────────────────────────
    public static function regenerateSession(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    // ── Credential stuffing: muchos emails distintos / IP ───
    public static function isCredentialStuffing(string $ip, PDO $pdo): bool {
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT detalle) FROM logs_seguridad
             WHERE ip = ? AND evento = 'login_fallido'
             AND creado_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn() >= 5;
    }
}