<?php
// ============================================================
//  api/traducir.php
//  Traduce texto usando MyMemory API (100% gratuita)
//  Sin API key, sin registro
//  POST { texto: "...", idioma: "en" }
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$texto  = trim($input['texto'] ?? '');
$idioma = $input['idioma'] ?? 'en';

if (!$texto) {
    echo json_encode(['traduccion' => '']);
    exit;
}

if ($idioma === 'es') {
    echo json_encode(['traduccion' => $texto]);
    exit;
}

// ── MyMemory API (gratis, sin key) ────────────────────────
$url = 'https://api.mymemory.translated.net/get?' . http_build_query([
    'q'        => $texto,
    'langpair' => 'es|' . $idioma,
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $httpCode !== 200) {
    echo json_encode(['traduccion' => $texto]);
    exit;
}

$data       = json_decode($response, true);
$traduccion = $data['responseData']['translatedText'] ?? $texto;

echo json_encode(['traduccion' => trim($traduccion)]);