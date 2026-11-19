<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$users = $pdo->query('SELECT id, first_name, last_name, email, status, created_at FROM users ORDER BY created_at DESC LIMIT 250')->fetchAll(PDO::FETCH_ASSOC);
admin_page_start('მომხმარებლები');
?>
<section class="admin-card">
    <div class="admin-card__header"><div><p class="admin-eyebrow">სისტემა</p><h2>მომხმარებლების სია</h2></div><span class="admin-badge"><?= count($users) ?> ჩანაწერი</span></div>
    <?php if (isset($_GET['password_reset'])) admin_alert('მომხმარებლის პაროლი განახლდა.'); ?>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>ID</th><th>მომხმარებელი</th><th>ელფოსტა</th><th>როლი</th><th>რეგისტრაცია</th><th>მოქმედება</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int) $user['id'] ?></td>
                    <td><strong><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']), ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="admin-badge"><?= htmlspecialchars($user['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($user['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><div class="admin-actions"><a href="edit_user.php?id=<?= (int) $user['id'] ?>" class="admin-button admin-button--neutral">რედაქტირება</a><a href="reset_password.php?id=<?= (int) $user['id'] ?>" class="admin-button admin-button--neutral">პაროლი</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php admin_page_end(); ?>
