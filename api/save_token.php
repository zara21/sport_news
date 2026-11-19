<?php
require_once '../includes/db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';

header('Content-Type: application/json; charset=utf-8');

if ($token) {
    $stmt = $pdo->prepare("INSERT INTO notification_tokens (token, subscribed_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE subscribed_at = NOW()");
    $stmt->execute([$token]);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No token received']);
}
