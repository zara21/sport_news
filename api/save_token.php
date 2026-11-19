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

$action = $data['action'] ?? 'subscribe';
if (!in_array($action, ['subscribe', 'unsubscribe'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
$userId = (int) $_SESSION['user_id'];

if ($action === 'unsubscribe') {
    // მხოლოდ საკუთარი token-ის წაშლა
    $pdo->prepare('DELETE FROM notification_tokens WHERE token = ? AND (user_id = ? OR user_id IS NULL)')->execute([$token, $userId]);
    echo json_encode(['status' => 'ok']);
    exit;
}

// token ეკუთვნის იმ მომხმარებელს, ვინც ბოლოს დაარეგისტრირა (მოწყობილობა შეიძლება სხვას გადაეცეს)
$stmt = $pdo->prepare('INSERT INTO notification_tokens (token, user_id, subscribed_at) VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), subscribed_at = NOW()');
$stmt->execute([$token, $userId]);
echo json_encode(['status' => 'ok']);
