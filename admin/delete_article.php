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
    exit('არასწორი სტატიის ID.');
}

$articleId = (int)$_POST['id'];
$stmt = $pdo->prepare("SELECT image, second_image, story_image, video FROM articles WHERE id = ?");
$stmt->execute([$articleId]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    http_response_code(404);
    exit('სტატია ვერ მოიძებნა.');
}

$pdo->prepare("DELETE FROM article_tags WHERE article_id = ?")->execute([$articleId]);
$pdo->prepare("DELETE FROM articles WHERE id = ?")->execute([$articleId]);

delete_local_media($article['image'] ?? null, __DIR__ . '/../uploads');
delete_local_media($article['second_image'] ?? null, __DIR__ . '/../uploads/second_images');
delete_local_media($article['story_image'] ?? null, __DIR__ . '/../uploads/story_images');
delete_local_media($article['video'] ?? null, __DIR__ . '/../uploads/videos');

header('Location: admin_articles.php?deleted=1');
exit;
