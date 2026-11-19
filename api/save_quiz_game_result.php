<?php
require_once __DIR__ . '/../includes/auth_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function quiz_game_response(array $result, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    quiz_game_response(['ok' => false], 405);
}
if (!verify_form_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    quiz_game_response(['ok' => false], 403);
}

$data = json_decode(file_get_contents('php://input'), true);
$token = $data['attempt'] ?? null;
$action = $data['action'] ?? null;
if (!is_string($token) || !in_array($action, ['guess', 'finish'], true)) {
    quiz_game_response(['ok' => false], 400);
}

$attempt = $_SESSION['game_quiz_attempts'][$token] ?? null;
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (!$attempt || $attempt['user_id'] !== $userId) {
    quiz_game_response(['ok' => false], 409);
}
if (isset($attempt['result'])) {
    quiz_game_response($attempt['result']);
}

$players = $attempt['players'];
$total = count($players);
$remaining = max(0, $attempt['ends_at'] - time());

if ($action === 'guess' && $remaining > 0 && count($attempt['guessed']) < $total) {
    $guess = $data['guess'] ?? null;
    if (!is_string($guess) || trim($guess) === '' || mb_strlen($guess, 'UTF-8') > 255) {
        quiz_game_response(['ok' => false], 400);
    }
    $guess = mb_strtolower(trim($guess), 'UTF-8');
    $matches = [];
    foreach ($players as $index => $player) {
        $georgian = mb_strtolower(trim((string) $player['player_name_geo']), 'UTF-8');
        $english = mb_strtolower(trim((string) $player['player_name_eng']), 'UTF-8');
        if ($guess === $georgian || $guess === $english) {
            $attempt['guessed'][$index] = true;
            $matches[] = ['index' => $index, 'name' => $player['player_name_geo'] ?: $player['player_name_eng']];
        }
    }
    $_SESSION['game_quiz_attempts'][$token] = $attempt;
    quiz_game_response(['ok' => true, 'matches' => $matches, 'score' => count($attempt['guessed'])]);
}

$score = count($attempt['guessed']);
if ($remaining > 0 && $score < $total) {
    quiz_game_response(['ok' => false, 'remaining' => $remaining], 409);
}

if ($userId > 0) {
    require_once __DIR__ . '/../includes/db_connect.php';
    $pdo->prepare('INSERT INTO quiz_game_results (user_id, quiz_game_id, score, total) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $attempt['game_id'], $score, $total]);
}

$answers = [];
foreach ($players as $index => $player) {
    $answers[] = [
        'name' => $player['player_name_geo'] ?: $player['player_name_eng'],
        'guessed' => isset($attempt['guessed'][$index]),
    ];
}
$result = ['ok' => true, 'finished' => true, 'score' => $score, 'total' => $total, 'players' => $answers];
$_SESSION['game_quiz_attempts'][$token] = ['user_id' => $userId, 'result' => $result];
quiz_game_response($result);
