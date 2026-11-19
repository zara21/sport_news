<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/includes/head_meta.php'; ?>
    <meta name="description" content="Sport Blog - სპორტული სიახლეები, მატჩები, ცხრილები, ვიდეოები და ქვიზები.">
    <title>Sport Blog</title>

    <link rel="stylesheet" href="/sport_blog/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/stories.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/home.css?v=20261119g">
    <script src="/sport_blog/js/home.js?v=20260624-1" defer></script>
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main class="site-main">
    <?php if (isset($_GET['account_deleted'])): ?>
        <div class="home-notice" role="status">ანგარიში და მასთან დაკავშირებული მონაცემები წაიშალა.</div>
    <?php endif; ?>
    <section class="home-band home-band--stories">
        <?php require_once __DIR__ . '/views/stories.php'; ?>
    </section>
    <section class="home-band home-band--hero">
        <?php require_once __DIR__ . '/views/featured_articles.php'; ?>
    </section>
    <section class="home-band home-band--matches" aria-label="ლაივ მატჩები">
        <?php require_once __DIR__ . '/views/matchs.php'; ?>
    </section>
    <section class="home-band home-band--latest">
        <?php require_once __DIR__ . '/views/home.php'; ?>
    </section>
    <section class="home-band home-band--interactive">
        <?php require_once __DIR__ . '/views/quiz_list.php'; ?>
    </section>
    <section class="home-band home-band--videos">
        <?php require_once __DIR__ . '/views/video.php'; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
