<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

require_admin_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

verify_csrf_token($_POST['csrf_token'] ?? null);

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    exit('არასწორი ID.');
}

$id = (int)$_POST['id'];
$pdo->prepare('DELETE FROM quiz_game_questions WHERE quiz_game_id = ?')->execute([$id]);
$pdo->prepare('DELETE FROM quiz_games WHERE id = ?')->execute([$id]);

header('Location: quiz_games.php?deleted=1');
exit;
