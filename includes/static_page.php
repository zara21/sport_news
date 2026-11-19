<?php
require_once __DIR__ . '/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/app_config.php';

function static_page_start(string $title, string $kicker, string $description, string $updated = ''): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/head_meta.php'; ?>
    <title><?= $safeTitle ?> | Sport Blog</title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/pages.css?v=20261119g">
</head>
<body class="sport-body">
<?php require __DIR__ . '/header.php'; ?>
<main class="static-page">
    <header class="static-page__hero">
        <span class="static-page__kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></span>
        <h1><?= $safeTitle ?></h1>
        <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($updated !== ''): ?><small>ბოლო განახლება: <?= htmlspecialchars($updated, ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
    </header>
    <article class="static-page__body">
    <?php
}

function static_page_end(): void
{
    ?>
    </article>
</main>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
    <?php
}

function site_contact_email(): string
{
    $email = (string) (app_config('site')['contact_email'] ?? '');
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}
