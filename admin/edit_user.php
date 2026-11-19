<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';

require_admin_user();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('მომხმარებელი ვერ მოიძებნა.');
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    exit('მომხმარებელი ვერ მოიძებნა.');
}

$roles = ['user', 'admin', 'moderator', 'Super Administrator', 'Administrator', 'Author/Editor', 'Subscriber', 'Advertiser', 'Guest'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    try {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $status = (string)($_POST['status'] ?? 'user');

        if ($firstName === '' || $lastName === '' || $email === '') {
            throw new RuntimeException('სახელი, გვარი და ელფოსტა სავალდებულოა.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ელფოსტის ფორმატი არასწორია.');
        }

        if (!in_array($status, $roles, true)) {
            throw new RuntimeException('როლი არასწორია.');
        }

        $duplicate = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
        $duplicate->execute([$email, $id]);
        if ((int)$duplicate->fetchColumn() > 0) {
            throw new RuntimeException('ეს ელფოსტა სხვა მომხმარებელს უკვე აქვს.');
        }

        $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, email = ?, status = ? WHERE id = ?');
        $update->execute([$firstName, $lastName, $email, $status, $id]);

        $_SESSION['success_message'] = 'მომხმარებელი განახლდა.';
        header('Location: user_list.php');
        exit;
    } catch (Throwable $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: edit_user.php?id=' . $id);
        exit;
    }
}

admin_page_start('მომხმარებლის რედაქტირება');
?>

<section class="admin-card">
    <div class="admin-card__header">
        <div>
            <h2>მომხმარებლის რედაქტირება</h2>
            <p class="admin-muted"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="admin-button admin-button--neutral" href="user_list.php">მომხმარებლები</a>
    </div>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form class="admin-form" method="POST">
        <?php admin_csrf_field(); ?>

        <div class="admin-form-grid">
            <label>სახელი
                <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>გვარი
                <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
        </div>

        <label>ელფოსტა
            <input type="email" name="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </label>

        <label>როლი
            <select name="status" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $user['status'] === $role ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="admin-actions">
            <button class="admin-button admin-button--primary" type="submit">შენახვა</button>
            <a class="admin-button admin-button--neutral" href="user_list.php">გაუქმება</a>
        </div>
    </form>
</section>

<?php admin_page_end(); ?>
