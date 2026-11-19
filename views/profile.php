<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_helpers.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['user']['id'])) {
    header('Location: /sport_blog/auth_system/login');
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? $_SESSION['user']['id']);
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, birthdate, gender, phone, oauth_provider, status, created_at, profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: /sport_blog/auth_system/login');
    exit;
}

$_SESSION['user'] = array_merge($_SESSION['user'] ?? [], $user);
$_SESSION['user_id'] = $userId;

function profile_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function profile_value(?string $value): string
{
    $value = trim((string)$value);
    return $value !== '' ? profile_h($value) : '<span class="user-profile__empty">არ არის მითითებული</span>';
}

$fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$displayName = $fullName !== '' ? $fullName : 'მომხმარებელი';
$initials = mb_substr(trim((string)($user['first_name'] ?? 'U')), 0, 1) . mb_substr(trim((string)($user['last_name'] ?? '')), 0, 1);
$profileImage = trim((string)($user['profile_image'] ?? ''));
$status = strtolower((string)($user['status'] ?? 'user'));
$adminStatuses = ['admin', 'administrator', 'super administrator', 'author/editor', 'moderator'];
$isAdmin = in_array($status, $adminStatuses, true);
$createdAt = !empty($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '';
$authProvider = $user['oauth_provider'] ?: 'manual';
?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title>ჩემი პროფილი | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/profile.css?v=20261119i">
</head>
<body class="profile-page">
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="profile-shell">
    <section class="user-profile">
        <header class="user-profile__header">
            <div class="user-profile__avatar" aria-hidden="true">
                <?php if ($profileImage !== ''): ?>
                    <img src="<?= profile_h($profileImage) ?>" alt="">
                <?php else: ?>
                    <span><?= profile_h(mb_strtoupper($initials ?: 'U')) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <p class="user-profile__eyebrow">პირადი სივრცე</p>
                <h1><?= profile_h($displayName) ?></h1>
                <div class="user-profile__meta">
                    <span><?= profile_h($user['email'] ?? '') ?></span>
                    <span><?= profile_h(ucfirst($authProvider)) ?></span>
                    <?php if ($createdAt): ?><span>რეგისტრაცია: <?= profile_h($createdAt) ?></span><?php endif; ?>
                </div>
            </div>
        </header>

        <div class="user-profile__grid">
            <article class="user-profile__panel">
                <h2>ინფორმაცია</h2>
                <dl class="user-profile__details">
                    <div>
                        <dt>სახელი</dt>
                        <dd><?= profile_value($user['first_name'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>გვარი</dt>
                        <dd><?= profile_value($user['last_name'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>ელფოსტა</dt>
                        <dd><?= profile_value($user['email'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>დაბადების თარიღი</dt>
                        <dd><?= profile_value($user['birthdate'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>სქესი</dt>
                        <dd><?= profile_value($user['gender'] ?? '') ?></dd>
                    </div>
                    <div>
                        <dt>ტელეფონი</dt>
                        <dd><?= profile_value($user['phone'] ?? '') ?></dd>
                    </div>
                </dl>
            </article>

            <aside class="user-profile__panel user-profile__side-panel">
                <h2>ანგარიში</h2>
                <div class="user-profile__status">
                    <span>როლი</span>
                    <strong><?= profile_h($user['status'] ?? 'user') ?></strong>
                </div>
                <div class="user-profile__actions">
                    <a href="/sport_blog/views/library" class="user-profile__button user-profile__button--primary">ჩემი ბიბლიოთეკა</a>
                    <a href="/sport_blog/views/notifications" class="user-profile__button user-profile__button--neutral">შეტყობინებები</a>
                    <a href="/sport_blog/views/profile_edit" class="user-profile__button user-profile__button--primary">პროფილის რედაქტირება</a>
                    <a href="/sport_blog/" class="user-profile__button user-profile__button--neutral">მთავარი</a>
                    <form action="/sport_blog/auth_system/logout" method="post">
                        <?php render_logout_token(); ?>
                        <button type="submit" class="user-profile__button user-profile__button--danger">გასვლა</button>
                    </form>
                </div>
            </aside>
        </div>

        <?php if ($isAdmin): ?>
            <section class="user-profile__admin-panel">
                <div>
                    <p class="user-profile__eyebrow">მართვა</p>
                    <h2>ადმინის სწრაფი მოქმედებები</h2>
                </div>
                <div class="user-profile__admin-links">
                    <a href="/sport_blog/admin/add_article.php">სტატიის დამატება</a>
                    <a href="/sport_blog/admin/admin_articles.php">სტატიები</a>
                    <a href="/sport_blog/admin/add_match.php">მატჩის დამატება</a>
                    <a href="/sport_blog/admin/add_quiz.php">ქვიზის დამატება</a>
                </div>
            </section>
        <?php endif; ?>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
