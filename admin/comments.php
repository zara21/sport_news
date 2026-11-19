<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user('articles.manage');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $id = (int) ($_POST['comment_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM article_comments WHERE id = ?')->execute([$id]);
        $message = 'კომენტარი წაიშალა.';
    } elseif (in_array($action, ['hide', 'show'], true)) {
        $pdo->prepare('UPDATE article_comments SET status = ? WHERE id = ?')->execute([$action === 'hide' ? 'hidden' : 'visible', $id]);
        $message = $action === 'hide' ? 'კომენტარი დაიმალა.' : 'კომენტარი გამოჩნდა.';
    }
}

$filter = in_array($_GET['status'] ?? '', ['visible', 'hidden'], true) ? $_GET['status'] : 'all';
$search = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];
if ($filter !== 'all') {
    $where[] = 'c.status = ?';
    $params[] = $filter;
}
if ($search !== '') {
    $where[] = '(c.body LIKE ? OR a.title LIKE ? OR u.email LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}

$stmt = $pdo->prepare('SELECT c.id, c.body, c.status, c.created_at, a.title, a.slug,
        u.first_name, u.last_name, u.email
    FROM article_comments c
    JOIN articles a ON a.id = c.article_id
    JOIN users u ON u.id = c.user_id
    ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
    ORDER BY c.created_at DESC
    LIMIT 200');
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(status = 'hidden'), 0) hidden FROM article_comments")->fetch(PDO::FETCH_ASSOC);
$h = static fn(?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

admin_page_start('კომენტარები');
admin_alert($message);
?>
<section class="admin-card">
    <div class="admin-card__header">
        <div>
            <p class="admin-eyebrow">მოდერაცია</p>
            <h2>სტატიების კომენტარები</h2>
        </div>
        <span class="admin-badge"><?= (int) $counts['total'] ?> სულ · <?= (int) $counts['hidden'] ?> დამალული</span>
    </div>

    <form method="get" class="admin-comments__filters">
        <input type="search" name="q" class="admin-search" value="<?= $h($search) ?>" placeholder="ძებნა: ტექსტი, სტატია ან ელფოსტა">
        <select name="status">
            <option value="all">ყველა</option>
            <option value="visible" <?= $filter === 'visible' ? 'selected' : '' ?>>ხილული</option>
            <option value="hidden" <?= $filter === 'hidden' ? 'selected' : '' ?>>დამალული</option>
        </select>
        <button type="submit" class="admin-button admin-button--neutral">ფილტრი</button>
    </form>

    <?php if (!$comments): ?>
        <p class="admin-muted">კომენტარები არ მოიძებნა.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr><th>კომენტარი</th><th>ავტორი</th><th>სტატია</th><th>თარიღი</th><th>მოქმედება</th></tr>
                </thead>
                <tbody>
                <?php foreach ($comments as $comment): $hidden = $comment['status'] === 'hidden'; ?>
                    <tr>
                        <td>
                            <?php if ($hidden): ?><span class="admin-badge admin-badge--warning">დამალული</span><br><?php endif; ?>
                            <?= nl2br($h(mb_strimwidth($comment['body'], 0, 260, '…'))) ?>
                        </td>
                        <td><strong><?= $h(trim($comment['first_name'] . ' ' . $comment['last_name'])) ?></strong><br><small class="admin-muted"><?= $h($comment['email']) ?></small></td>
                        <td><a href="/sport_blog/news/<?= rawurlencode($comment['slug']) ?>#comment-<?= (int) $comment['id'] ?>" target="_blank" rel="noopener"><?= $h(mb_strimwidth($comment['title'], 0, 60, '…')) ?></a></td>
                        <td><?= $h(date('d.m.Y H:i', strtotime($comment['created_at']))) ?></td>
                        <td>
                            <div class="admin-actions">
                                <form method="post" class="admin-inline-form">
                                    <?php admin_csrf_field(); ?>
                                    <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $hidden ? 'show' : 'hide' ?>">
                                    <button type="submit" class="admin-button admin-button--neutral"><?= $hidden ? 'გამოჩენა' : 'დამალვა' ?></button>
                                </form>
                                <form method="post" class="admin-inline-form" onsubmit="return confirm('კომენტარი წაიშალოს?')">
                                    <?php admin_csrf_field(); ?>
                                    <input type="hidden" name="comment_id" value="<?= (int) $comment['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="admin-button admin-button--danger">წაშლა</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php admin_page_end(); ?>
