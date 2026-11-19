<?php

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(sys_get_temp_dir());
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_once __DIR__ . '/../includes/embed_helpers.php';
require_once __DIR__ . '/../includes/user_activity_helpers.php';
require_once __DIR__ . '/../includes/club_helpers.php';
require_once __DIR__ . '/../includes/sports_import_helpers.php';

function workflow_assert(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException("FAIL {$label}");
    echo "PASS {$label}\n";
}

$userId = (int) $pdo->query("SELECT id FROM users ORDER BY CASE WHEN LOWER(status) IN ('admin','administrator','super administrator') THEN 0 ELSE 1 END, id LIMIT 1")->fetchColumn();
if ($userId <= 0) {
    fwrite(STDERR, "FAIL no user available for transactional workflow test\n");
    exit(1);
}

$seed = bin2hex(random_bytes(5));
$pdo->beginTransaction();
try {
    $slug = unique_slug($pdo, 'articles', safe_slug('Workflow Article ' . $seed));
    $content = sanitize_article_html('<p>ტესტი</p><script>alert(1)</script>');
    workflow_assert(!str_contains($content, '<script'), 'article html sanitization');

    $insert = $pdo->prepare("INSERT INTO articles (user_id, title, content, image, category, slug, has_video, popular, summary, status, views, show_in_story, story_expires_at, type) VALUES (?, ?, ?, '', 'football', ?, 0, 0, ?, 'draft', 0, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), 'news')");
    $insert->execute([$userId, 'Workflow Article ' . $seed, $content, $slug, 'Transactional test']);
    $articleId = (int) $pdo->lastInsertId();
    workflow_assert($articleId > 0, 'article draft creation');

    ob_start();
    require __DIR__ . '/../templates/articles-template.php';
    $draftPage = ob_get_clean();
    workflow_assert(str_contains($draftPage, 'article-not-found'), 'draft article is not public');

    $pdo->prepare("UPDATE articles SET scheduled_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?")->execute([$articleId]);
    workflow_assert(publish_due_articles($pdo) >= 1, 'scheduled article publication');
    workflow_assert((string)$pdo->query("SELECT status FROM articles WHERE id={$articleId}")->fetchColumn() === 'published', 'scheduled article status');
    workflow_assert((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE link LIKE '%{$slug}%'")->fetchColumn() === 1, 'publication notification');

    $pdo->prepare('INSERT INTO article_bookmarks(user_id,article_id) VALUES(?,?)')->execute([$userId,$articleId]);
    record_article_read($pdo,$userId,$articleId);
    workflow_assert(article_is_bookmarked($pdo,$userId,$articleId), 'article bookmark');
    workflow_assert((int)$pdo->query("SELECT read_count FROM article_reads WHERE user_id={$userId} AND article_id={$articleId}")->fetchColumn() === 1, 'article reading history');

    $update = $pdo->prepare("UPDATE articles SET status = 'published', title = ? WHERE id = ?");
    $update->execute(['Workflow Article Updated ' . $seed, $articleId]);
    $article = $pdo->query("SELECT status, show_in_story, story_expires_at FROM articles WHERE id = {$articleId}")->fetch(PDO::FETCH_ASSOC);
    workflow_assert($article['status'] === 'published' && (int) $article['show_in_story'] === 1 && $article['story_expires_at'] !== null, 'article edit and story fields');

    $quizSlug = unique_slug($pdo, 'quizzes', 'workflow-quiz-' . $seed);
    $quizInsert = $pdo->prepare('INSERT INTO quizzes (title, slug, category_id, quiz_image) VALUES (?, ?, 1, ?)');
    $quizInsert->execute(['Workflow Quiz ' . $seed, $quizSlug, '']);
    $quizId = (int) $pdo->lastInsertId();
    workflow_assert($quizId > 0, 'quiz creation');
    workflow_assert(unique_slug($pdo, 'quizzes', $quizSlug) !== $quizSlug, 'slug collision handling');
    workflow_assert(admin_role_capabilities('Author/Editor') === ['dashboard', 'articles.manage', 'stories.manage', 'media.manage'], 'editor capability boundary');
    workflow_assert(!in_array('users.manage', admin_role_capabilities('moderator'), true), 'moderator cannot manage users');
    workflow_assert(normalize_embed_url('<iframe src="https://example.com/live"></iframe>') === 'https://example.com/live', 'iframe url normalization');
    workflow_assert(in_array('Arsenal', database_club_aliases($pdo,'არსენალი'), true), 'club alias resolution');
    workflow_assert((int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='quiz_game_results'")->fetchColumn() === 1, 'quiz game history table');
    $importPreview=sports_preview($pdo,'all',__DIR__.'/../admin/import');
    workflow_assert(isset($importPreview['matches']['incoming'],$importPreview['standings']['incoming']), 'sports import preview');

    $pdo->rollBack();
    echo "PASS transaction rolled back\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
