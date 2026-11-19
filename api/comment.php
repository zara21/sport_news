<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/comment_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

function comment_redirect(string $returnTo, ?string $type = null, ?string $message = null, string $anchor = '#comments'): never
{
    if ($type !== null) {
        $_SESSION['comment_flash'] = ['type' => $type, 'message' => $message];
    }
    // მხოლოდ საიტის შიდა სტატიის ბმული (open redirect-ისგან დაცვა)
    $prefix = rtrim(SPORT_BLOG_BASE_PATH, '/') . '/news/';
    $slugPart = str_starts_with($returnTo, $prefix) ? substr($returnTo, strlen($prefix)) : '';
    if ($slugPart === '' || strpbrk($slugPart, "/?#\\\r\n") !== false) {
        $returnTo = site_path('/');
        $anchor = '';
    }
    header('Location: ' . $returnTo . $anchor, true, 303);
    exit;
}

$returnTo = (string) ($_POST['return_to'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$userId) {
    site_redirect('/auth_system/login', 303);
}
if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
    comment_redirect($returnTo, 'error', 'ფორმის სესია ამოიწურა. სცადეთ თავიდან.');
}

$action = (string) ($_POST['action'] ?? '');

if ($action === 'add') {
    $articleId = (int) ($_POST['article_id'] ?? 0);
    $body = trim(str_replace("\r\n", "\n", (string) ($_POST['body'] ?? '')));
    $body = preg_replace("/\n{3,}/", "\n\n", $body);
    $rateKey = auth_rate_limit_key('comment|' . $userId);

    if ($body === '' || mb_strlen($body, 'UTF-8') > COMMENT_MAX_LENGTH) {
        comment_redirect($returnTo, 'error', 'კომენტარი ცარიელია ან ძალიან გრძელია (მაქს. ' . COMMENT_MAX_LENGTH . ' სიმბოლო).');
    }
    if (auth_is_rate_limited($rateKey, 5, 600)) {
        comment_redirect($returnTo, 'error', 'ძალიან ბევრი კომენტარი მოკლე დროში. სცადეთ რამდენიმე წუთში.');
    }

    $article = $pdo->prepare("SELECT id FROM articles WHERE id = ? AND status = 'published' LIMIT 1");
    $article->execute([$articleId]);
    if (!$article->fetchColumn()) {
        comment_redirect($returnTo, 'error', 'სტატია ვერ მოიძებნა.');
    }

    auth_record_failure($rateKey, 600);
    $pdo->prepare('INSERT INTO article_comments (article_id, user_id, body) VALUES (?, ?, ?)')->execute([$articleId, $userId, $body]);
    comment_redirect($returnTo, 'success', 'კომენტარი გამოქვეყნდა.', '#comment-' . (int) $pdo->lastInsertId());
}

$commentId = (int) ($_POST['comment_id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, user_id FROM article_comments WHERE id = ? LIMIT 1');
$stmt->execute([$commentId]);
$comment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$comment) {
    comment_redirect($returnTo, 'error', 'კომენტარი ვერ მოიძებნა.');
}

$canModerate = comment_can_moderate();

if ($action === 'delete') {
    if (!$canModerate && (int) $comment['user_id'] !== $userId) {
        http_response_code(403);
        exit('Access denied.');
    }
    $pdo->prepare('DELETE FROM article_comments WHERE id = ?')->execute([$commentId]);
    comment_redirect($returnTo, 'success', 'კომენტარი წაიშალა.');
}

if (in_array($action, ['hide', 'show'], true)) {
    if (!$canModerate) {
        http_response_code(403);
        exit('Access denied.');
    }
    $pdo->prepare('UPDATE article_comments SET status = ? WHERE id = ?')->execute([$action === 'hide' ? 'hidden' : 'visible', $commentId]);
    comment_redirect($returnTo, 'success', $action === 'hide' ? 'კომენტარი დაიმალა.' : 'კომენტარი გამოჩნდა.', '#comment-' . $commentId);
}

http_response_code(400);
exit('Unknown action.');
