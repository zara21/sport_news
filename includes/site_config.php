<?php

$configuredBasePath = $_SERVER['SPORT_BLOG_BASE_PATH'] ?? getenv('SPORT_BLOG_BASE_PATH') ?: '/sport_blog';
$configuredBasePath = '/' . trim((string) $configuredBasePath, '/');
if ($configuredBasePath === '/') {
    $configuredBasePath = '';
}

define('SPORT_BLOG_BASE_PATH', $configuredBasePath);

// საიტის დროის სარტყელი. db_connect.php MySQL-ის სესიასაც იგივე offset-ზე აყენებს,
// რომ დაგეგმილი სტატიების შემოწმება (PHP) და გამოქვეყნება (MySQL NOW()) ერთ დროზე მუშაობდეს.
date_default_timezone_set(getenv('SPORT_BLOG_TIMEZONE') ?: 'Asia/Tbilisi');

function site_path(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return SPORT_BLOG_BASE_PATH . ($path === '/' ? '/' : $path);
}

function site_redirect(string $path, int $status = 302): never
{
    header('Location: ' . site_path($path), true, $status);
    exit;
}

if (SPORT_BLOG_BASE_PATH !== '/sport_blog') {
    ob_start(static function (string $buffer): string {
        return str_replace('/sport_blog/', site_path('/'), $buffer);
    });

    header_register_callback(static function (): void {
        foreach (headers_list() as $headerLine) {
            if (stripos($headerLine, 'Location: /sport_blog/') === 0) {
                header_remove('Location');
                header(str_replace('/sport_blog/', site_path('/'), $headerLine), true);
            }
        }
    });
}
