<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$stmt = $pdo->query('SELECT id, title, category, created_at, slug, status, scheduled_at FROM articles ORDER BY created_at DESC');
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('სტატიების მართვა');
admin_alert(isset($_GET['deleted']) ? 'სტატია წარმატებით წაიშალა.' : null);
?>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>სტატიები</h2>
        <a href="add_article.php" class="admin-button admin-button--primary">სტატიის დამატება</a>
    </div>

    <?php if (empty($articles)): ?>
        <p class="admin-muted">ჯერ სტატია არ არის დამატებული.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>სათაური</th>
                        <th>კატეგორია</th>
                        <th>თარიღი</th>
                        <th>მოქმედება</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $article): ?>
                    <tr>
                        <td><?= (int)$article['id'] ?></td>
                        <td><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($article['category'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= date('Y-m-d', strtotime($article['created_at'])) ?></td>
                        <td>
                            <div class="admin-actions">
                                <?php if($article['status']==='published'): ?><a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="admin-button admin-button--neutral" target="_blank" rel="noopener noreferrer">ნახვა</a><?php else: ?><a href="preview_article.php?id=<?= (int)$article['id'] ?>" class="admin-button admin-button--neutral" target="_blank">Preview</a><?php endif; ?>
                                <a href="edit_article.php?id=<?= (int)$article['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                                <form method="post" action="delete_article.php" class="admin-inline-form" onsubmit="return confirm('დარწმუნებული ხარ, რომ გინდა წაშლა?')">
                                    <?php admin_csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                    <button type="submit" class="admin-button--danger">წაშლა</button>
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
