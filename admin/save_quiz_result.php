<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!verify_form_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if (!is_int($data['quiz_id'] ?? null) || !is_string($data['attempt'] ?? null)
    || !is_array($data['answers'] ?? null) || !array_is_list($data['answers'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$quizId = $data['quiz_id'];
$attempt = $_SESSION['classic_quiz_attempts'][$data['attempt']] ?? null;
if (!is_array($attempt) || $attempt['quiz_id'] !== $quizId
    || $attempt['user_id'] !== (int) $_SESSION['user_id'] || $attempt['expires_at'] < time()) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired quiz attempt']);
    exit;
}
if (isset($attempt['result'])) {
    echo json_encode($attempt['result']);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
$questionStmt = $pdo->prepare('SELECT q.id, q.correct_option, COUNT(o.id) AS option_count
    FROM questions q
    LEFT JOIN options o ON o.question_id = q.id
    WHERE q.quiz_id = ?
    GROUP BY q.id, q.correct_option');
$questionStmt->execute([$quizId]);
$questions = [];
foreach ($questionStmt->fetchAll(PDO::FETCH_ASSOC) as $question) {
    $questions[(int) $question['id']] = $question;
}

if (!$questions) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Quiz not found']);
    exit;
}

$validatedAnswers = [];
$score = 0;
foreach ($data['answers'] as $answer) {
    if (!is_int($answer['question_id'] ?? null) || !is_int($answer['selected'] ?? null)) {
        continue;
    }
    $questionId = $answer['question_id'];
    $selected = $answer['selected'];
    if (!isset($questions[$questionId]) || isset($validatedAnswers[$questionId])) {
        continue;
    }
    if ($selected < 0 || $selected >= (int) $questions[$questionId]['option_count']) {
        continue;
    }
    $validatedAnswers[$questionId] = $selected;
    if ($selected === (int) $questions[$questionId]['correct_option']) {
        $score++;
    }
}

if (count($validatedAnswers) !== count($questions) || count($validatedAnswers) !== count($data['answers'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'One valid answer per question is required']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO quiz_results (user_id, quiz_id, score) VALUES (?, ?, ?)');
    $stmt->execute([(int) $_SESSION['user_id'], $quizId, $score]);
    $resultId = (int) $pdo->lastInsertId();

    $answerStmt = $pdo->prepare('INSERT INTO user_answers (result_id, question_id, selected_option) VALUES (?, ?, ?)');
    foreach ($validatedAnswers as $questionId => $selected) {
        $answerStmt->execute([$resultId, $questionId, $selected]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

$result = ['status' => 'success', 'score' => $score, 'total' => count($questions)];
$_SESSION['classic_quiz_attempts'][$data['attempt']]['result'] = $result;
echo json_encode($result);
