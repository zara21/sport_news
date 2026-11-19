<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!empty($_SESSION['user_id'])) {
    site_redirect('/views/profile_edit');
}

$error_message = '';
$sent = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $rateLimitKey = auth_rate_limit_key('reset|' . $email);

    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = 'ფორმის სესია ამოიწურა. სცადეთ თავიდან.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'შეიყვანეთ სწორი ელფოსტა.';
    } elseif (auth_is_rate_limited($rateLimitKey, 3, 900)) {
        http_response_code(429);
        $error_message = 'ძალიან ბევრი მოთხოვნაა. სცადეთ 15 წუთში.';
    } else {
        auth_record_failure($rateLimitKey, 900);

        $stmt = $pdo->prepare('SELECT id, first_name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([(int) $user['id']]);
            $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))')
                ->execute([(int) $user['id'], hash('sha256', $token)]);

            $link = site_public_url('/auth_system/reset_password?token=' . $token);
            $name = trim((string) $user['first_name']) ?: 'მომხმარებელო';
            send_mail(
                $user['email'],
                'პაროლის აღდგენა — Sport Blog',
                "გამარჯობა, {$name}!\n\n"
                . "პაროლის შესაცვლელად გახსენით ბმული (მოქმედებს 60 წუთი):\n{$link}\n\n"
                . "თუ ეს მოთხოვნა თქვენ არ გაგიგზავნიათ, უბრალოდ დააიგნორეთ ეს წერილი — პაროლი არ შეიცვლება.\n\n"
                . "Sport Blog"
            );
        }
        // ერთნაირი პასუხი ყოველთვის — ასე ვერ გაიგებენ, რომელი ელფოსტაა რეგისტრირებული
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>პაროლის აღდგენა | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/auth.css?v=20261119f">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="auth-page">
    <h1 class="auth-page__title">პაროლის აღდგენა</h1>

    <div class="login-form-card">
        <?php if ($sent): ?>
            <div class="auth-form__success-message">თუ ეს ელფოსტა რეგისტრირებულია, მასზე გამოიგზავნა პაროლის აღდგენის ბმული. შეამოწმეთ შემოსულები (და Spam).</div>
            <p class="login-form__register-link"><a href="/sport_blog/auth_system/login">შესვლის გვერდზე დაბრუნება</a></p>
        <?php else: ?>
            <?php if ($error_message): ?>
                <div class="auth-form__error-message"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST" class="login-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <p class="auth-page__hint">შეიყვანეთ ანგარიშის ელფოსტა — გამოგიგზავნით ბმულს ახალი პაროლის დასაყენებლად.</p>
                <div class="login-form__input-group">
                    <label for="email" class="login-form__label">Email</label>
                    <input type="email" id="email" name="email" class="login-form__input" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="email">
                </div>
                <button type="submit" class="login-form__submit-btn">ბმულის გაგზავნა</button>
                <p class="login-form__register-link"><a href="/sport_blog/auth_system/login">შესვლაზე დაბრუნება</a></p>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
