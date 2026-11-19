<?php

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function form_csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['form_csrf_token'])) {
        $_SESSION['form_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['form_csrf_token'];
}

function verify_form_csrf_token(?string $token): bool
{
    auth_start_session();
    return !empty($token)
        && !empty($_SESSION['form_csrf_token'])
        && hash_equals($_SESSION['form_csrf_token'], $token);
}

function auth_rate_limit_key(string $email = ''): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    return hash('sha256', strtolower($ip . '|' . trim($email)));
}

function auth_rate_limit_file(string $key): string
{
    return __DIR__ . '/../storage/rate_limits/' . preg_replace('/[^a-f0-9]/', '', $key) . '.json';
}

function auth_rate_limit_state(string $key, int $windowSeconds): array
{
    $file = auth_rate_limit_file($key);
    $state = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($state) || (int) ($state['started_at'] ?? 0) < time() - $windowSeconds) {
        return ['started_at' => time(), 'attempts' => 0];
    }
    return $state;
}

function auth_is_rate_limited(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    $state = auth_rate_limit_state($key, $windowSeconds);
    return (int) $state['attempts'] >= $maxAttempts;
}

function auth_record_failure(string $key, int $windowSeconds = 900): void
{
    $state = auth_rate_limit_state($key, $windowSeconds);
    $state['attempts'] = (int) $state['attempts'] + 1;
    $dir = dirname(auth_rate_limit_file($key));
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents(auth_rate_limit_file($key), json_encode($state), LOCK_EX);
}

function auth_clear_failures(string $key): void
{
    $file = auth_rate_limit_file($key);
    if (is_file($file)) {
        unlink($file);
    }
}
