<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_helpers.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /sport_blog/auth_system/login');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT id, first_name, last_name, email, birthdate, gender, phone, password, profile_image FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    session_unset();
    session_destroy();
    header('Location: /sport_blog/auth_system/login');
    exit;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_form_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $errorMessage = 'ფორმის სესია ამოიწურა. სცადეთ თავიდან.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'profile');
            if ($action === 'profile') {
                $firstName = trim((string) ($_POST['first_name'] ?? ''));
                $lastName = trim((string) ($_POST['last_name'] ?? ''));
                $birthdate = trim((string) ($_POST['birthdate'] ?? ''));
                $gender = trim((string) ($_POST['gender'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                if ($firstName === '' || $lastName === '') {
                    throw new RuntimeException('სახელი და გვარი სავალდებულოა.');
                }
                if ($gender !== '' && !in_array($gender, ['male', 'female', 'other'], true)) {
                    throw new RuntimeException('სქესი არასწორია.');
                }
                if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{5,20}$/', $phone)) {
                    throw new RuntimeException('ტელეფონის ნომრის ფორმატი არასწორია.');
                }

                $profileImage = (string) ($user['profile_image'] ?? '');
                $newAvatar = upload_media_file('profile_image', __DIR__ . '/../uploads/profile_images', ['image/jpeg', 'image/png', 'image/webp'], 2 * 1024 * 1024);
                if ($newAvatar) {
                    if (str_starts_with($profileImage, '/sport_blog/uploads/profile_images/')) {
                        delete_local_media(basename($profileImage), __DIR__ . '/../uploads/profile_images');
                    }
                    $profileImage = '/sport_blog/uploads/profile_images/' . $newAvatar;
                }

                $update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, birthdate = ?, gender = ?, phone = ?, profile_image = ? WHERE id = ?');
                $update->execute([$firstName, $lastName, $birthdate ?: null, $gender ?: null, $phone ?: null, $profileImage ?: null, $userId]);
                $user = array_merge($user, ['first_name' => $firstName, 'last_name' => $lastName, 'birthdate' => $birthdate, 'gender' => $gender, 'phone' => $phone, 'profile_image' => $profileImage]);
                $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $user);
                $successMessage = 'პროფილი განახლდა.';
            } elseif ($action === 'password') {
                $currentPassword = (string) ($_POST['current_password'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $confirmation = (string) ($_POST['confirm_password'] ?? '');
                if (empty($user['password']) || !password_verify($currentPassword, $user['password'])) {
                    throw new RuntimeException('მიმდინარე პაროლი არასწორია.');
                }
                if (strlen($newPassword) < 10) {
                    throw new RuntimeException('ახალი პაროლი მინიმუმ 10 სიმბოლოს უნდა შეიცავდეს.');
                }
                if ($newPassword !== $confirmation) {
                    throw new RuntimeException('ახალი პაროლები ერთმანეთს არ ემთხვევა.');
                }
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                $successMessage = 'პაროლი წარმატებით შეიცვალა.';
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

function profile_edit_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>პროფილის რედაქტირება | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/profile.css?v=20261119d">
</head>
<body class="profile-page">
<?php require __DIR__ . '/../includes/header.php'; ?>
<main class="profile-shell">
    <section class="profile-settings">
        <header class="profile-settings__heading">
            <div><p class="user-profile__eyebrow">ანგარიშის პარამეტრები</p><h1>პროფილის რედაქტირება</h1></div>
            <a href="/sport_blog/views/profile" class="user-profile__button user-profile__button--neutral">პროფილზე დაბრუნება</a>
        </header>

        <?php if ($successMessage): ?><div class="profile-settings__alert profile-settings__alert--success"><?= profile_edit_h($successMessage) ?></div><?php endif; ?>
        <?php if ($errorMessage): ?><div class="profile-settings__alert profile-settings__alert--error"><?= profile_edit_h($errorMessage) ?></div><?php endif; ?>

        <div class="profile-settings__grid">
            <form method="post" enctype="multipart/form-data" class="profile-settings__panel">
                <input type="hidden" name="csrf_token" value="<?= profile_edit_h(form_csrf_token()) ?>">
                <input type="hidden" name="action" value="profile">
                <h2>პირადი ინფორმაცია</h2>
                <div class="profile-settings__fields profile-settings__fields--two">
                    <label>სახელი<input type="text" name="first_name" value="<?= profile_edit_h($user['first_name']) ?>" required></label>
                    <label>გვარი<input type="text" name="last_name" value="<?= profile_edit_h($user['last_name']) ?>" required></label>
                </div>
                <label>ელფოსტა<input type="email" value="<?= profile_edit_h($user['email']) ?>" disabled></label>
                <div class="profile-settings__fields profile-settings__fields--two">
                    <label>დაბადების თარიღი<input type="date" name="birthdate" value="<?= profile_edit_h($user['birthdate']) ?>"></label>
                    <label>სქესი<select name="gender"><option value="">არ არის მითითებული</option><?php foreach (['male' => 'მამრობითი', 'female' => 'მდედრობითი', 'other' => 'სხვა'] as $value => $label): ?><option value="<?= $value ?>" <?= $user['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
                </div>
                <label>ტელეფონი<input type="tel" name="phone" value="<?= profile_edit_h($user['phone']) ?>"></label>
                <label>პროფილის ფოტო<input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG ან WebP, მაქსიმუმ 2MB.</small></label>
                <button type="submit" class="user-profile__button user-profile__button--primary">ცვლილებების შენახვა</button>
            </form>

            <form method="post" class="profile-settings__panel">
                <input type="hidden" name="csrf_token" value="<?= profile_edit_h(form_csrf_token()) ?>">
                <input type="hidden" name="action" value="password">
                <h2>პაროლის შეცვლა</h2>
                <label>მიმდინარე პაროლი<input type="password" name="current_password" required autocomplete="current-password"></label>
                <label>ახალი პაროლი<input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label>
                <label>გაიმეორეთ ახალი პაროლი<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
                <button type="submit" class="user-profile__button user-profile__button--primary">პაროლის განახლება</button>
            </form>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
