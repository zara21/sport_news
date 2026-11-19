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
// სურათები წაშლის შემდეგ გასასუფთავებლად
$images = $pdo->prepare('SELECT quiz_image FROM quizzes WHERE id = ? UNION SELECT quiz_image FROM questions WHERE quiz_id = ?');
$images->execute([$id, $id]);
$imageNames = array_filter(array_map(static fn($v) => basename((string) $v), $images->fetchAll(PDO::FETCH_COLUMN)));

$pdo->beginTransaction();
try {
    // quiz_tags → quizzes კავშირი RESTRICT-ია: თეგები ჯერ უნდა წაიშალოს, თორემ წაშლა ჩავარდება
    $pdo->prepare('DELETE FROM quiz_tags WHERE quiz_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM options WHERE question_id IN (SELECT id FROM questions WHERE quiz_id = ?)')->execute([$id]);
    $pdo->prepare('DELETE FROM questions WHERE quiz_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM quizzes WHERE id = ?')->execute([$id]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('delete_quiz: ' . $e->getMessage());
    http_response_code(500);
    exit('ქვიზი ვერ წაიშალა.');
}

foreach ($imageNames as $name) {
    delete_local_media($name, __DIR__ . '/../uploads/quizzes');
    delete_local_media($name, __DIR__ . '/../uploads');
}

header('Location: quizzes.php?deleted=1');
exit;
