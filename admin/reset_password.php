<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_admin_user();

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    exit('მომხმარებელი ვერ მოიძებნა.');
}

$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($password) < 10) {
        $errorMessage = 'პაროლი მინიმუმ 10 სიმბოლოს უნდა შეიცავდეს.';
    } elseif ($password !== $confirmation) {
        $errorMessage = 'პაროლები ერთმანეთს არ ემთხვევა.';
    } else {
        $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        header('Location: user_list.php?password_reset=1');
        exit;
    }
}

admin_page_start('პაროლის შეცვლა');
?>
<section class="admin-card admin-card--narrow">
    <div class="admin-card__header">
        <div><p class="admin-eyebrow">მომხმარებელი #<?= $userId ?></p><h2><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']), ENT_QUOTES, 'UTF-8') ?></h2></div>
        <a href="user_list.php" class="admin-button admin-button--neutral">მომხმარებლები</a>
    </div>
    <p class="admin-muted"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
    <?php admin_alert($errorMessage, 'error'); ?>
    <form method="post" class="admin-form">
        <?php admin_csrf_field(); ?>
        <label>ახალი პაროლი<input type="password" name="password" minlength="10" autocomplete="new-password" required></label>
        <label>გაიმეორეთ პაროლი<input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required></label>
        <div class="admin-actions"><button type="submit" class="admin-button admin-button--primary">პაროლის განახლება</button></div>
    </form>
</section>
<?php admin_page_end(); ?>
