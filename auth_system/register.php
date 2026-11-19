<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /sport_blog/views/profile.php');
    exit;
}

$error_message = '';
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$birthdate = trim($_POST['birthdate'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        $error_message = 'ფორმის სესია ამოიწურა. გთხოვთ სცადოთ თავიდან.';
    } elseif ($first_name === '' || $last_name === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error_message = 'გთხოვთ შეავსოთ სავალდებულო ველები.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'გთხოვთ შეიყვანოთ სწორი ელფოსტა.';
    } elseif (strlen($password) < 10) {
        $error_message = 'პაროლი უნდა იყოს მინიმუმ 10 სიმბოლო.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'პაროლები არ ემთხვევა.';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetchColumn()) {
            $error_message = 'ეს ელფოსტა უკვე რეგისტრირებულია.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, birthdate, gender, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'user')");
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $birthdate ?: null,
                $gender ?: null,
                $phone ?: null,
            ]);

            header('Location: /sport_blog/auth_system/login?registration_success=true');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title>რეგისტრაცია | Sport Blog</title>

    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119h">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119h">
    <link rel="stylesheet" href="/sport_blog/css/auth.css?v=20261119h">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="auth-page">
    <h1 class="auth-page__title">რეგისტრაცია</h1>

    <div class="registration-form-card">
        <?php if ($error_message): ?>
            <div class="auth-form__error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" class="registration-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="registration-form__input-group">
                <label for="first_name" class="registration-form__label">სახელი</label>
                <input type="text" id="first_name" name="first_name" class="registration-form__input" value="<?= htmlspecialchars($first_name) ?>" required>
            </div>

            <div class="registration-form__input-group">
                <label for="last_name" class="registration-form__label">გვარი</label>
                <input type="text" id="last_name" name="last_name" class="registration-form__input" value="<?= htmlspecialchars($last_name) ?>" required>
            </div>

            <div class="registration-form__input-group">
                <label for="email" class="registration-form__label">Email</label>
                <input type="email" id="email" name="email" class="registration-form__input" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <div class="registration-form__input-group">
                <label for="password" class="registration-form__label">პაროლი</label>
                <input type="password" id="password" name="password" class="registration-form__input" minlength="10" required autocomplete="new-password">
            </div>

            <div class="registration-form__input-group">
                <label for="confirm_password" class="registration-form__label">გაიმეორეთ პაროლი</label>
                <input type="password" id="confirm_password" name="confirm_password" class="registration-form__input" minlength="10" required autocomplete="new-password">
            </div>

            <div class="registration-form__input-group">
                <label for="birthdate" class="registration-form__label">დაბადების თარიღი</label>
                <input type="date" id="birthdate" name="birthdate" class="registration-form__input" value="<?= htmlspecialchars($birthdate) ?>">
            </div>

            <div class="registration-form__input-group">
                <label for="gender" class="registration-form__label">სქესი</label>
                <select id="gender" name="gender" class="registration-form__input">
                    <option value="">აირჩიეთ</option>
                    <option value="male" <?= $gender === 'male' ? 'selected' : '' ?>>მამრობითი</option>
                    <option value="female" <?= $gender === 'female' ? 'selected' : '' ?>>მდედრობითი</option>
                    <option value="other" <?= $gender === 'other' ? 'selected' : '' ?>>სხვა</option>
                </select>
            </div>

            <div class="registration-form__input-group">
                <label for="phone" class="registration-form__label">ტელეფონი</label>
                <input type="tel" id="phone" name="phone" class="registration-form__input" value="<?= htmlspecialchars($phone) ?>">
            </div>

            <p class="auth-page__hint">რეგისტრაციით ეთანხმებით <a href="/sport_blog/views/terms">წესებს</a> და <a href="/sport_blog/views/privacy">კონფიდენციალურობის პოლიტიკას</a>.</p>

            <button type="submit" class="registration-form__submit-btn">რეგისტრაცია</button>

            <?php render_google_login_button('Google-ით რეგისტრაცია'); ?>

            <p class="registration-form__login-link">
                უკვე გაქვთ ანგარიში? <a href="/sport_blog/auth_system/login">შესვლა</a>
            </p>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
