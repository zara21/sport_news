<?php
// საიტის 404 გვერდი (ErrorDocument .htaccess-ში)
require_once __DIR__ . '/includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/includes/db_connect.php';
http_response_code(404);

$latest = $pdo->query("SELECT title, slug FROM articles WHERE status = 'published' AND slug <> '' AND slug NOT LIKE 'default-story-%' ORDER BY created_at DESC LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/includes/head_meta.php'; ?>
    <meta name="robots" content="noindex">
    <title>გვერდი ვერ მოიძებნა | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/pages.css?v=20261119i">
</head>
<body class="sport-body">
<?php require __DIR__ . '/includes/header.php'; ?>
<main class="not-found">
    <div class="not-found__code" aria-hidden="true">4<span>0</span>4</div>
    <h1>გვერდი ვერ მოიძებნა</h1>
    <p>ბმული შეიძლება შეცდომით იყოს აკრეფილი, ან გვერდი წაიშალა. სცადეთ ძებნა ან დაბრუნდით მთავარზე.</p>
    <form class="not-found__search" action="/sport_blog/views/articles" method="get" role="search">
        <input type="search" name="q" placeholder="სტატიების ძებნა…" aria-label="სტატიების ძებნა" required maxlength="120">
        <button type="submit"><i class="fas fa-search" aria-hidden="true"></i> ძებნა</button>
    </form>
    <div class="not-found__actions">
        <a href="/sport_blog/" class="sb-btn sb-btn--accent"><i class="fas fa-house" aria-hidden="true"></i> მთავარი</a>
        <a href="/sport_blog/views/articles" class="sb-btn">ყველა სტატია</a>
        <a href="/sport_blog/views/all_quizzes" class="sb-btn">ქვიზები</a>
    </div>
    <?php if ($latest): ?>
        <section class="not-found__latest">
            <h2>ბოლო სიახლეები</h2>
            <ul>
                <?php foreach ($latest as $article): ?>
                    <li><a href="/sport_blog/news/<?= htmlspecialchars(rawurlencode($article['slug']), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
