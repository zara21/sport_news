<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

$pdo->beginTransaction();
try {
    $articles = $pdo->query("SELECT id, title, slug FROM articles
        WHERE (id = 44 AND (slug IS NULL OR slug = '')) OR (id = 45 AND slug = '-1745996214')
        ORDER BY id FOR UPDATE")->fetchAll();
    $update = $pdo->prepare('UPDATE articles SET slug = ?, updated_at = updated_at WHERE id = ?');
    foreach ($articles as $article) {
        $id = (int) $article['id'];
        $base = safe_slug($article['title'], 'article-' . $id);
        $update->execute([unique_slug($pdo, 'articles', $base, $id), $id]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    $pdo->rollBack();
    throw $error;
}

printf("Updated slugs: %d\n", count($articles));
