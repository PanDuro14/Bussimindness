<?php
// ============================================================
//  lib/JWT.php
//  JSON Web Token — HMAC-SHA256
//  Access Token: 15 min  |  Refresh Token: 7 días
// ============================================================
class JWT {

    private static function secret(bool $refresh = false): string {
        $key = $refresh ? 'JWT_REFRESH_SECRET' : 'JWT_SECRET';
        // En producción: usar variables de entorno
        $defaults = [
            'JWT_SECRET'         => 'bussimindness_jwt_secret_2024_CAMBIA_ESTO!',
            'JWT_REFRESH_SECRET' => 'bussimindness_refresh_secret_2024_CAMBIA_ESTO!',
        ];
        return $_ENV[$key] ?? $defaults[$key];
    }

    private static function b64e(string $d): string {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }
    private static function b64d(string $d): string {
        return base64_decode(strtr($d, '-_', '+/'));
    }

    // ── Generar Access Token (default 15 min) ──────────────
    public static function generate(array $payload, int $ttl = 900): string {
        $header  = self::b64e(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $payload = array_merge($payload, [
            'iat' => time(),
            'exp' => time() + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ]);
        $body = self::b64e(json_encode($payload));
        $sig  = self::b64e(hash_hmac('sha256', "$header.$body", self::secret(), true));
        return "$header.$body.$sig";
    }

    // ── Generar Refresh Token (7 días) ─────────────────────
    public static function generateRefresh(int $userId, string $sessionUuid): string {
        $header = self::b64e(json_encode(['alg'=>'HS256','typ'=>'JWT']));
        $body   = self::b64e(json_encode([
            'sub'          => $userId,
            'session_uuid' => $sessionUuid,
            'iat'          => time(),
            'exp'          => time() + 604800, // 7 días
            'jti'          => bin2hex(random_bytes(16)),
        ]));
        $sig = self::b64e(hash_hmac('sha256', "$header.$body", self::secret(true), true));
        return "$header.$body.$sig";
    }

    // ── Verificar token — devuelve payload o false ──────────
    public static function verify(string $token, bool $isRefresh = false): array|false {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        [$h, $b, $sig] = $parts;

        $expected = self::b64e(hash_hmac('sha256', "$h.$b", self::secret($isRefresh), true));
        if (!hash_equals($expected, $sig)) return false;

        $data = json_decode(self::b64d($b), true);
        if (!$data || ($data['exp'] ?? 0) < time()) return false;

        return $data;
    }

    // ── Extraer JTI sin verificar (para blacklist) ──────────
    public static function getJti(string $token): ?string {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        $d = json_decode(self::b64d($parts[1]), true);
        return $d['jti'] ?? null;
    }

    // ── Verificar si el JTI está en blacklist ───────────────
    public static function isRevoked(string $jti, PDO $pdo): bool {
        $s = $pdo->prepare("SELECT id FROM tokens_revocados WHERE jti = ?");
        $s->execute([$jti]);
        return (bool)$s->fetch();
    }

    // ── Revocar token (añadir a blacklist) ──────────────────
    public static function revoke(string $token, PDO $pdo): void {
        $data = self::verify($token);
        if (!$data) return;
        try {
            $pdo->prepare(
                "INSERT IGNORE INTO tokens_revocados (jti, expira_en) VALUES (?, FROM_UNIXTIME(?))"
            )->execute([$data['jti'], $data['exp']]);
        } catch (PDOException $e) {}
    }

    // ── Limpiar blacklist expirada ──────────────────────────
    public static function cleanExpired(PDO $pdo): void {
        $pdo->exec("DELETE FROM tokens_revocados WHERE expira_en < NOW()");
    }
}