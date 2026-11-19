<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

require_admin_user();

$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
$search = trim((string)($_GET['search'] ?? ''));

$sql = 'SELECT id, title, created_at FROM articles';
$params = [];

if ($search !== '') {
    $sql .= ' WHERE title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY created_at DESC LIMIT :offset, :limit';
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($articles as $row): ?>
<tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?></td>
    <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
    <td>
        <div class="admin-actions">
            <a href="edit_article.php?id=<?= (int)$row['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
            <form method="post" action="delete_article.php" class="admin-inline-form" onsubmit="return confirm('წავშალოთ სტატია?')">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="admin-button--danger">წაშლა</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
