<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

$stmt = $pdo->query("SELECT id, title, slug, created_at FROM quizzes ORDER BY id DESC");
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('ქვიზების მართვა');
admin_alert(isset($_GET['deleted']) ? 'ქვიზი წარმატებით წაიშალა.' : null);
?>

<section class="admin-card">
    <div class="admin-card__header">
        <h2>ქვიზები</h2>
        <a href="add_quiz.php" class="admin-button admin-button--primary">ქვიზის დამატება</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>სათაური</th>
                    <th>მოქმედება</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($quizzes as $quiz): ?>
                <tr>
                    <td><?= (int)$quiz['id'] ?></td>
                    <td><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="admin-actions">
                            <a href="/sport_blog/quizzes/<?= htmlspecialchars($quiz['slug'], ENT_QUOTES, 'UTF-8') ?>" class="admin-button admin-button--neutral" target="_blank" rel="noopener noreferrer">ნახვა</a>
                            <a href="edit_quiz.php?id=<?= (int)$quiz['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a>
                            <form method="post" action="delete_quiz.php" class="admin-inline-form" onsubmit="return confirm('დარწმუნებული ხარ წაშლაში?')">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int)$quiz['id'] ?>">
                                <button type="submit" class="admin-button--danger">წაშლა</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php admin_page_end(); ?>
