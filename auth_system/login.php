<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';

$error_message = '';
$success_message = '';
$email = '';

if (isset($_SESSION['user_id'])) {
    $sessionStatus = strtolower((string)($_SESSION['user']['status'] ?? ''));
    $adminStatuses = ['admin', 'administrator', 'super administrator', 'author/editor', 'moderator'];
    header('Location: ' . (in_array($sessionStatus, $adminStatuses, true) ? '/sport_blog/admin/admin.php' : '/sport_blog/views/profile.php'));
    exit;
}

if (!empty($_SESSION['google_login_error'])) {
    $error_message = (string) $_SESSION['google_login_error'];
    unset($_SESSION['google_login_error']);
}

if (isset($_GET['registration_success']) && $_GET['registration_success'] === 'true') {
    $success_message = 'რეგისტრაცია წარმატებით დასრულდა. ახლა შეგიძლიათ შეხვიდეთ სისტემაში.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rateLimitKey = auth_rate_limit_key($email);

    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = 'ფორმის სესია ამოიწურა. გთხოვთ სცადოთ თავიდან.';
    } elseif (auth_is_rate_limited($rateLimitKey)) {
        http_response_code(429);
        $error_message = 'შესვლის მცდელობები დროებით შეზღუდულია. სცადეთ 15 წუთში.';
    } elseif ($email === '' || $password === '') {
        $error_message = 'გთხოვთ შეიყვანოთ ელფოსტა და პაროლი.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'გთხოვთ შეიყვანოთ სწორი ელფოსტა.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            auth_clear_failures($rateLimitKey);
            session_regenerate_id(true);
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['id'];
            $status = strtolower((string)($user['status'] ?? ''));
            $adminStatuses = ['admin', 'administrator', 'super administrator', 'author/editor', 'moderator'];
            header('Location: ' . (in_array($status, $adminStatuses, true) ? '/sport_blog/admin/admin.php' : '/sport_blog/index.php'));
            exit;
        }

        auth_record_failure($rateLimitKey);
        $error_message = 'ელფოსტა ან პაროლი არასწორია.';
    }
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>შესვლა | Sport Blog</title>

    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119e">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119e">
    <link rel="stylesheet" href="/sport_blog/css/auth.css?v=20261119e">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="auth-page">
    <h1 class="auth-page__title">შესვლა</h1>

    <div class="login-form-card">
        <?php if ($error_message): ?>
            <div class="auth-form__error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="auth-form__success-message"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="login-form__input-group">
                <label for="email" class="login-form__label">Email</label>
                <input type="email" id="email" name="email" class="login-form__input" placeholder="შეიყვანეთ Email" value="<?= htmlspecialchars($email) ?>" required autocomplete="email">
            </div>

            <div class="login-form__input-group">
                <label for="password" class="login-form__label">პაროლი</label>
                <input type="password" id="password" name="password" class="login-form__input" placeholder="პაროლი" required autocomplete="current-password">
            </div>

            <button type="submit" class="login-form__submit-btn">შესვლა</button>

            <p class="login-form__register-link">
                არ გაქვთ ანგარიში? <a href="/sport_blog/auth_system/register">დარეგისტრირდით</a>
            </p>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
