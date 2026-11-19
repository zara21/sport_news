<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/seo_helpers.php';

function google_oauth_config(): array
{
    $file = __DIR__ . '/../config/google_oauth.php';
    $config = is_file($file) ? (array) require $file : [];

    $clientId = getenv('GOOGLE_CLIENT_ID') ?: ($config['client_id'] ?? '');
    $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: ($config['client_secret'] ?? '');
    $redirectUri = getenv('GOOGLE_REDIRECT_URI') ?: ($config['redirect_uri'] ?? '');
    if ($redirectUri === '') {
        $redirectUri = rtrim(site_base_url(), '/') . '/auth_system/google_login.php';
    }

    return ['client_id' => (string) $clientId, 'client_secret' => (string) $clientSecret, 'redirect_uri' => (string) $redirectUri];
}

function google_http_request(string $url, ?array $postFields = null, ?string $bearer = null): ?array
{
    $curl = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($bearer !== null) {
        $headers[] = 'Authorization: Bearer ' . $bearer;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($postFields !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postFields));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($body) || $status !== 200) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function google_login_fail(string $message): never
{
    $_SESSION['google_login_error'] = $message;
    site_redirect('/auth_system/login');
}

$config = google_oauth_config();
if ($config['client_id'] === '' || $config['client_secret'] === '') {
    http_response_code(503);
    exit('Google-ით შესვლა არ არის კონფიგურირებული.');
}

if (!empty($_SESSION['user_id'])) {
    site_redirect('/views/profile');
}

// 1) ნაბიჯი: Google-ზე გადამისამართება state-ით (CSRF დაცვა)
if (!isset($_GET['code'])) {
    if (isset($_GET['error'])) {
        google_login_fail('Google-ით შესვლა გაუქმდა.');
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
    ]);
    header('Location: ' . $authUrl);
    exit;
}

// 2) ნაბიჯი: callback
$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);
if ($expectedState === '' || !hash_equals($expectedState, (string) ($_GET['state'] ?? ''))) {
    google_login_fail('Google-ით შესვლის სესია ამოიწურა. სცადეთ თავიდან.');
}

$token = google_http_request('https://oauth2.googleapis.com/token', [
    'code' => (string) $_GET['code'],
    'client_id' => $config['client_id'],
    'client_secret' => $config['client_secret'],
    'redirect_uri' => $config['redirect_uri'],
    'grant_type' => 'authorization_code',
]);
if (empty($token['access_token'])) {
    google_login_fail('Google-ისგან ტოკენის მიღება ვერ მოხერხდა.');
}

$info = google_http_request('https://openidconnect.googleapis.com/v1/userinfo', null, (string) $token['access_token']);
$email = trim((string) ($info['email'] ?? ''));
$googleId = (string) ($info['sub'] ?? '');
if ($googleId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($info['email_verified'])) {
    google_login_fail('Google ანგარიშის ელფოსტა დადასტურებული არ არის.');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $firstName = mb_substr(trim((string) ($info['given_name'] ?? '')) ?: 'Google', 0, 100);
    $lastName = mb_substr(trim((string) ($info['family_name'] ?? '')) ?: 'User', 0, 100);
    $picture = (string) ($info['picture'] ?? '');
    $picture = preg_match('#^https://#i', $picture) ? mb_substr($picture, 0, 255) : null;

    $insert = $pdo->prepare("INSERT INTO users (first_name, last_name, email, oauth_provider, oauth_uid, profile_image, status) VALUES (?, ?, ?, 'google', ?, ?, 'user')");
    $insert->execute([$firstName, $lastName, $email, $googleId, $picture]);

    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif (empty($user['oauth_uid'])) {
    $pdo->prepare("UPDATE users SET oauth_provider = 'google', oauth_uid = ? WHERE id = ?")->execute([$googleId, (int) $user['id']]);
}

if (!$user) {
    google_login_fail('მომხმარებლის შექმნა ვერ მოხერხდა.');
}

session_regenerate_id(true);
unset($user['password']);
unset($_SESSION['form_csrf_token']);
$_SESSION['user'] = $user;
$_SESSION['user_id'] = (int) $user['id'];

$status = strtolower((string) ($user['status'] ?? ''));
$adminStatuses = ['admin', 'administrator', 'super administrator', 'author/editor', 'moderator'];
site_redirect(in_array($status, $adminStatuses, true) ? '/admin/admin.php' : '/index.php');
