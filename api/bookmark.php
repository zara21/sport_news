<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . site_path('/auth_system/login'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Invalid request.');
}
$articleId = (int) ($_POST['article_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];
$exists = $pdo->prepare('SELECT 1 FROM article_bookmarks WHERE user_id = ? AND article_id = ?');
$exists->execute([$userId, $articleId]);
if ($exists->fetchColumn()) {
    $pdo->prepare('DELETE FROM article_bookmarks WHERE user_id = ? AND article_id = ?')->execute([$userId, $articleId]);
} else {
    $pdo->prepare('INSERT IGNORE INTO article_bookmarks (user_id, article_id) SELECT ?, id FROM articles WHERE id = ? AND status = "published"')->execute([$userId, $articleId]);
}
$returnTo = (string) ($_POST['return_to'] ?? '/views/library');
if (!str_starts_with($returnTo, '/')) $returnTo = '/views/library';
$base = rtrim(SPORT_BLOG_BASE_PATH, '/');
$redirect = $base !== '' && ($returnTo === $base || str_starts_with($returnTo, $base . '/'))
    ? $returnTo
    : site_path($returnTo);
header('Location: ' . $redirect);
exit;
