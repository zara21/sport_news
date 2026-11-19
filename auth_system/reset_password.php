<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error_message = '';
$reset = null;

function find_password_reset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT pr.id, pr.user_id, u.email FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$reset = find_password_reset($pdo, $token);

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = 'ფორმის სესია ამოიწურა. სცადეთ თავიდან.';
    } elseif (strlen($password) < 10) {
        $error_message = 'პაროლი მინიმუმ 10 სიმბოლო უნდა იყოს.';
    } elseif ($password !== $confirm) {
        $error_message = 'პაროლები არ ემთხვევა.';
    } else {
        $pdo->beginTransaction();
        try {
            // ტოკენი ერთჯერადია: "used_at IS NULL" პირობა იცავს ორმაგი გამოყენებისგან
            $claim = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $claim->execute([(int) $reset['id']]);
            if ($claim->rowCount() !== 1) {
                throw new RuntimeException('token already used');
            }
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $reset['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND id <> ?')
                ->execute([(int) $reset['user_id'], (int) $reset['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            $reset = null;
        }

        if ($reset) {
            auth_clear_failures(auth_rate_limit_key(strtolower((string) $reset['email'])));
            site_redirect('/auth_system/login?password_reset=1');
        }
    }
}

if (!$reset) {
    http_response_code(400);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>ახალი პაროლი | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/auth.css?v=20261119f">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="auth-page">
    <h1 class="auth-page__title">ახალი პაროლი</h1>

    <div class="login-form-card">
        <?php if (!$reset): ?>
            <div class="auth-form__error-message">ბმული არასწორია, უკვე გამოყენებულია ან ვადა გაუვიდა (მოქმედებს 60 წუთი).</div>
            <p class="login-form__register-link"><a href="/sport_blog/auth_system/forgot_password">ახალი ბმულის მოთხოვნა</a></p>
        <?php else: ?>
            <?php if ($error_message): ?>
                <div class="auth-form__error-message"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST" class="login-form" action="/sport_blog/auth_system/reset_password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <p class="auth-page__hint">ანგარიში: <strong><?= htmlspecialchars($reset['email'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <div class="login-form__input-group">
                    <label for="password" class="login-form__label">ახალი პაროლი</label>
                    <input type="password" id="password" name="password" class="login-form__input" minlength="10" required autocomplete="new-password">
                </div>
                <div class="login-form__input-group">
                    <label for="confirm_password" class="login-form__label">გაიმეორეთ პაროლი</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="login-form__input" minlength="10" required autocomplete="new-password">
                </div>
                <button type="submit" class="login-form__submit-btn">პაროლის შენახვა</button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
