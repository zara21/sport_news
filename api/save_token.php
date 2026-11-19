<?php
require_once __DIR__ . '/../includes/auth_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

auth_start_session();
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

if (!verify_form_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? null;
if (!is_string($token) || !preg_match('/\A[!-~]{1,4096}\z/', $token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
$stmt = $pdo->prepare('INSERT INTO notification_tokens (token, subscribed_at) VALUES (?, NOW())
    ON DUPLICATE KEY UPDATE subscribed_at = NOW()');
$stmt->execute([$token]);
echo json_encode(['status' => 'ok']);
