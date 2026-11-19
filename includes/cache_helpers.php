<?php

function cache_remember(string $key, int $ttl, callable $resolver)
{
    $cacheDir = __DIR__ . '/../storage/cache';
    $cacheFile = $cacheDir . '/' . sha1($key) . '.json';

    if (is_file($cacheFile) && (filemtime($cacheFile) + $ttl) > time()) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && array_key_exists('value', $cached)) {
            return $cached['value'];
        }
    }

    $value = $resolver();
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }

    $payload = json_encode(['value' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload !== false) {
        file_put_contents($cacheFile, $payload, LOCK_EX);
    }

    return $value;
}

function cache_clear_all(): void
{
    $cacheDir = __DIR__ . '/../storage/cache';
    foreach (glob($cacheDir . '/*.json') ?: [] as $cacheFile) {
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
