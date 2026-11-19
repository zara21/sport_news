<?php
/**
 * ატვირთული სურათის პატარა ვერსია (WebP, თუ შესაძლებელია), cache-ით.
 * /sport_blog/media/thumb.php?src=uploads/xxx.jpg&w=640
 * GD-ის გარეშე ორიგინალს აბრუნებს (გადამისამართებით).
 */
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/media_helpers.php';

$allowedWidths = SB_THUMB_WIDTHS;
$src = ltrim(str_replace('\\', '/', (string) ($_GET['src'] ?? '')), '/');
$width = (int) ($_GET['w'] ?? 0);

$root = realpath(__DIR__ . '/../uploads');
$file = realpath(__DIR__ . '/../' . $src);
$extension = strtolower(pathinfo((string) $file, PATHINFO_EXTENSION));

if (!$root || !$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)
    || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) || !in_array($width, $allowedWidths, true)) {
    http_response_code(404);
    exit;
}

$originalUrl = '/sport_blog/' . str_replace('\\', '/', substr($file, strlen(dirname($root)) + 1));

if (!sb_thumbs_supported()) {
    header('Location: ' . $originalUrl, true, 302);
    exit;
}

$cacheDir = __DIR__ . '/../storage/thumbs';
$cacheFile = $cacheDir . '/' . hash('sha256', $file . '|' . filemtime($file) . '|' . $width) . '.webp';

if (!is_file($cacheFile)) {
    $info = @getimagesize($file);
    $loader = match ($info['mime'] ?? '') {
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
        'image/gif' => 'imagecreatefromgif',
        default => null,
    };
    $source = $loader && function_exists($loader) ? @$loader($file) : false;
    if (!$source) {
        header('Location: ' . $originalUrl, true, 302);
        exit;
    }
    [$origW, $origH] = [imagesx($source), imagesy($source)];
    $targetW = min($width, $origW);
    $targetH = max(1, (int) round($origH * $targetW / $origW));
    $target = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($target, false);
    imagesavealpha($target, true);
    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }
    $tmp = $cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
    imagewebp($target, $tmp, 80);
    rename($tmp, $cacheFile);
    imagedestroy($source);
    imagedestroy($target);
}

// თუ პატარა ვერსია ორიგინალზე მსუბუქი არ გამოვიდა — ორიგინალი
if (filesize($cacheFile) >= filesize($file)) {
    header('Location: ' . $originalUrl, true, 302);
    exit;
}

header('Content-Type: image/webp');
header('Cache-Control: public, max-age=2592000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($cacheFile));
readfile($cacheFile);
