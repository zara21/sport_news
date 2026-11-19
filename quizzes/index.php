<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$parts = explode('/', trim($path, '/'));
$slug = $_GET['slug'] ?? urldecode((string) end($parts));
$slug = preg_replace('/\.php$/i', '', trim((string) $slug));

if ($slug === '' || $slug === 'index') {
    header('Location: /sport_blog/views/all_quizzes');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM quiz_games WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
if ($stmt->fetchColumn()) {
    require __DIR__ . '/../templates/quiz-sec-template.php';
    exit;
}

require __DIR__ . '/../templates/quiz-template.php';
