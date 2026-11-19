<?php
require_once __DIR__ . '/../includes/site_config.php';

$source = trim((string) ($_GET['src'] ?? ''));
$allowedHosts = ['static.flashscore.com', 'crests.football-data.org', 'cdn.sportfeeds.io'];
$host = strtolower((string) parse_url($source, PHP_URL_HOST));
if (filter_var($source, FILTER_VALIDATE_URL) === false
    || strtolower((string) parse_url($source, PHP_URL_SCHEME)) !== 'https'
    || !in_array($host, $allowedHosts, true)) {
    http_response_code(400);
    exit;
}

$cacheDir = __DIR__ . '/../storage/image_cache';
$cacheKey = hash('sha256', $source);
$metaFile = $cacheDir . '/' . $cacheKey . '.json';
$dataFile = $cacheDir . '/' . $cacheKey . '.bin';
$mime = '';

if (!is_file($dataFile) || !is_file($metaFile)) {
    if (!function_exists('curl_init')) {
        http_response_code(503);
        exit;
    }
    $body = '';
    $curl = curl_init($source);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_USERAGENT => 'SportBlogImageCache/1.0',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!$ok || $status !== 200 || $body === '') {
        http_response_code(502);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($body);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'], true)) {
        http_response_code(415);
        exit;
    }
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0775, true);
    file_put_contents($dataFile, $body, LOCK_EX);
    file_put_contents($metaFile, json_encode(['mime' => $mime]), LOCK_EX);
} else {
    $meta = json_decode((string) file_get_contents($metaFile), true);
    $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    exit;
}

if ($mime === 'image/svg+xml') {
    // SVG შეიძლება შეიცავდეს სკრიპტს; <img>-ში ის არ ეშვება, მაგრამ პირდაპირ გახსნისას
    // ამ header-ებით სკრიპტი იბლოკება და დოკუმენტი sandbox-ში იხსნება.
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
    header('Content-Disposition: inline; filename="image.svg"');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=604800, immutable');
header('ETag: "' . $cacheKey . '"');
readfile($dataFile);
