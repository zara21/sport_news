<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
if (!verify_form_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
$data = json_decode(file_get_contents('php://input'), true);
$gameId = (int)($data['game_id'] ?? 0);
$guessed = array_values(array_unique(array_filter(array_map('intval', (array)($data['guessed_ids'] ?? [])))));
$totalStmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_game_questions WHERE quiz_game_id=?');
$totalStmt->execute([$gameId]); $total=(int)$totalStmt->fetchColumn();
if (!$total) { http_response_code(404); echo json_encode(['ok'=>false]); exit; }
$score=0;
if ($guessed) {
    $ph=implode(',',array_fill(0,count($guessed),'?'));
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM quiz_game_questions WHERE quiz_game_id=? AND id IN ($ph)");
    $stmt->execute([$gameId,...$guessed]); $score=(int)$stmt->fetchColumn();
}
$pdo->prepare('INSERT INTO quiz_game_results(user_id,quiz_game_id,score,total) VALUES(?,?,?,?)')->execute([(int)$_SESSION['user_id'],$gameId,$score,$total]);
echo json_encode(['ok'=>true,'score'=>$score,'total'=>$total]);
