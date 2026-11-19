<?php

function normalize_embed_url(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (stripos($value, '<iframe') !== false
        && preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $value, $match)) {
        $value = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $value : '';
}

/**
 * YouTube-ის ნებისმიერი ბმულიდან (watch, youtu.be, shorts, embed) ქმნის უსაფრთხო embed URL-ს.
 * თუ ბმული YouTube-ის არ არის, აბრუნებს ცარიელ სტრიქონს.
 */
function youtube_embed_url(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || !preg_match('#^https?://#i', $value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
    $path = (string) parse_url($value, PHP_URL_PATH);
    parse_str((string) parse_url($value, PHP_URL_QUERY), $query);

    $id = '';
    if ($host === 'youtu.be') {
        $id = trim($path, '/');
    } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
        if ($path === '/watch') {
            $id = (string) ($query['v'] ?? '');
        } elseif (preg_match('#^/(embed|shorts|live)/([^/]+)#', $path, $match)) {
            $id = $match[2];
        }
    }

    return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? 'https://www.youtube-nocookie.com/embed/' . $id : '';
}
