<?php
/**
 * Push-შეტყობინებები Firebase Cloud Messaging HTTP v1 API-ით.
 * ძველი "legacy" API (fcm.googleapis.com/fcm/send + server key) Google-მა 2024 წელს გათიშა.
 *
 * პარამეტრები: config/firebase.php (ნიმუში: config/firebase.example.php).
 * სანამ კონფიგურაცია სრული არ არის, push გამორთულია და საიტი მხოლოდ შიდა შეტყობინებებს იყენებს.
 */
require_once __DIR__ . '/app_config.php';

function firebase_config(): array
{
    return app_config('firebase', [
        'api_key' => '',
        'auth_domain' => '',
        'project_id' => '',
        'messaging_sender_id' => '',
        'app_id' => '',
        'vapid_key' => '',
        'service_account_file' => __DIR__ . '/../config/firebase-service-account.json',
    ]);
}

/** ბრაუზერისთვის საჭირო (საჯარო) პარამეტრები — token-ის მისაღებად */
function push_client_enabled(): bool
{
    $c = firebase_config();
    return $c['api_key'] !== '' && $c['project_id'] !== '' && $c['messaging_sender_id'] !== '' && $c['app_id'] !== '' && $c['vapid_key'] !== '';
}

function push_client_config(): array
{
    $c = firebase_config();
    return [
        'apiKey' => $c['api_key'],
        'authDomain' => $c['auth_domain'],
        'projectId' => $c['project_id'],
        'messagingSenderId' => $c['messaging_sender_id'],
        'appId' => $c['app_id'],
    ];
}

/** სერვერიდან გაგზავნა შესაძლებელია (service account არსებობს) */
function push_server_enabled(): bool
{
    $account = push_service_account();
    return $account !== null && firebase_config()['project_id'] !== '';
}

function push_service_account(): ?array
{
    static $account = false;
    if ($account === false) {
        $file = (string) firebase_config()['service_account_file'];
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $account = is_array($data) && !empty($data['client_email']) && !empty($data['private_key']) ? $data : null;
    }
    return $account;
}

function push_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/** OAuth2 access token service account-ით (JWT RS256), cache-ში ~55 წუთი */
function push_access_token(): string
{
    $cacheFile = __DIR__ . '/../storage/cache/fcm_access_token.json';
    $cached = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && ($cached['expires_at'] ?? 0) > time() + 60 && !empty($cached['token'])) {
        return (string) $cached['token'];
    }

    $account = push_service_account();
    if (!$account) {
        throw new RuntimeException('Firebase service account არ არის კონფიგურირებული.');
    }

    $now = time();
    $header = push_base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = push_base64url(json_encode([
        'iss' => $account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $signature = '';
    if (!openssl_sign($header . '.' . $claims, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('JWT-ის ხელმოწერა ვერ მოხერხდა (private_key არასწორია).');
    }
    $jwt = $header . '.' . $claims . '.' . push_base64url($signature);

    [$status, $body] = push_http_post($account['token_uri'] ?? 'https://oauth2.googleapis.com/token', http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    $data = json_decode($body, true);
    if ($status !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('Google OAuth შეცდომა: HTTP ' . $status);
    }

    if (!is_dir(dirname($cacheFile))) {
        mkdir(dirname($cacheFile), 0775, true);
    }
    file_put_contents($cacheFile, json_encode([
        'token' => $data['access_token'],
        'expires_at' => $now + (int) ($data['expires_in'] ?? 3600),
    ]), LOCK_EX);
    return (string) $data['access_token'];
}

function push_http_post(string $url, string $body, array $headers): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$status, is_string($response) ? $response : ''];
}

/**
 * Push-ის გაგზავნა. $userId = null — ყველა გამოწერილს, სხვა შემთხვევაში მხოლოდ ამ მომხმარებლის მოწყობილობებს.
 * აბრუნებს ['sent' => n, 'failed' => n, 'removed' => n].
 * გაუქმებული/ვადაგასული token-ები ავტომატურად იშლება.
 */
function push_send(PDO $pdo, string $title, string $body, string $link = '', ?int $userId = null, int $limit = 1000): array
{
    $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];
    if (!push_server_enabled()) {
        return $result;
    }

    if ($userId) {
        $stmt = $pdo->prepare('SELECT id, token FROM notification_tokens WHERE user_id = ? LIMIT ' . (int) $limit);
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->query('SELECT id, token FROM notification_tokens ORDER BY id DESC LIMIT ' . (int) $limit);
    }
    $tokens = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!$tokens) {
        return $result;
    }

    require_once __DIR__ . '/seo_helpers.php';
    $accessToken = push_access_token();
    $endpoint = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode((string) firebase_config()['project_id']) . '/messages:send';
    $headers = ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json; charset=UTF-8'];
    $absoluteLink = $link !== '' ? absolute_site_url($link) : absolute_site_url('/sport_blog/');
    $icon = absolute_site_url('/sport_blog/assets/brand/app-icon-192.png');
    $delete = $pdo->prepare('DELETE FROM notification_tokens WHERE id = ?');

    foreach ($tokens as $id => $token) {
        // data-only შეტყობინება: service worker თავად აჩენს და დაჭერისას ხსნის ბმულს
        $payload = json_encode(['message' => [
            'token' => $token,
            'data' => [
                'title' => mb_substr($title, 0, 120, 'UTF-8'),
                'body' => mb_substr($body, 0, 500, 'UTF-8'),
                'link' => $absoluteLink,
                'icon' => $icon,
            ],
            'webpush' => ['headers' => ['Urgency' => 'normal', 'TTL' => '86400']],
        ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        [$status, $response] = push_http_post($endpoint, $payload, $headers);
        if ($status === 200) {
            $result['sent']++;
            continue;
        }
        $result['failed']++;
        $error = (string) (json_decode($response, true)['error']['status'] ?? '');
        // UNREGISTERED (404) / არასწორი token — მოწყობილობამ გამოწერა გააუქმა
        if ($status === 404 || in_array($error, ['UNREGISTERED', 'NOT_FOUND'], true)
            || ($status === 400 && $error === 'INVALID_ARGUMENT')) {
            $delete->execute([$id]);
            $result['removed']++;
        } elseif ($status === 401) {
            @unlink(__DIR__ . '/../storage/cache/fcm_access_token.json');
            break;
        }
    }

    return $result;
}
