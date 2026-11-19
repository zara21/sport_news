<?php
require_once __DIR__ . '/../includes/site_config.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    unset($cookie['lifetime']);
    setcookie(session_name(), '', ['expires' => time() - 3600] + $cookie);
}
session_destroy();

site_redirect('/', 303);
