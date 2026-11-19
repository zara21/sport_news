<?php
require_once __DIR__ . '/admin_helpers.php';
require_once __DIR__ . '/brand.php';

function admin_nav_item(string $href, string $icon, string $label): void
{
    $capability = admin_required_capability(basename(parse_url($href, PHP_URL_PATH) ?: ''));
    if (!admin_can($capability)) {
        return;
    }
    $current = basename(parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '');
    $target = basename(parse_url($href, PHP_URL_PATH) ?: '');
    $aliases = [
        'admin_articles.php' => ['edit_article.php', 'delete_article.php', 'load_articles.php'],
        'quizzes.php' => ['add_quiz.php', 'edit_quiz.php', 'delete_quiz.php'],
        'quiz_games.php' => ['add_sec_quiz.php', 'edit_quiz_game.php', 'delete_quiz_game.php'],
        'manage_UFC_F1_event.php' => ['add_UFC_F1_event.php', 'edit_UFC_F1_event.php'],
        'user_list.php' => ['edit_user.php', 'reset_password.php'],
        'admin_panel.php' => ['import_matches.php', 'import_csv.php'],
    ];
    $isActive = $current === $target || in_array($current, $aliases[$target] ?? [], true);
    $active = $isActive ? ' admin-sidebar__link--active' : '';
    ?>
    <a class="admin-sidebar__link<?= $active ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
        <span class="admin-sidebar__icon"><?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?></span>
        <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    </a>
    <?php
}

function admin_nav_group(string $title, array $items): void
{
    ?>
    <div class="admin-sidebar__group">
        <span class="admin-sidebar__group-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
        <?php foreach ($items as $item): ?>
            <?php admin_nav_item($item['href'], $item['icon'], $item['label']); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

function admin_page_start(string $title, array $extraStyles = []): void
{
    require_admin_user();
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $userName = trim((string)($_SESSION['user']['first_name'] ?? '') . ' ' . (string)($_SESSION['user']['last_name'] ?? ''));
    $userName = $userName !== '' ? $userName : 'Admin';
    $safeUserName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="ka">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $safeTitle ?> | Admin</title>
        <script>try{const t=localStorage.getItem("sb-theme");if(t==="light"||t==="dark")document.documentElement.dataset.theme=t;}catch(e){}</script>
        <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
        <link rel="stylesheet" href="/sport_blog/css/design-system.css?v=20261119d">
        <link rel="stylesheet" href="/sport_blog/css/admin.css?v=20261119d">
        <?php foreach ($extraStyles as $href): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    </head>
    <body class="admin-page">
    <aside class="admin-sidebar">
        <div class="admin-sidebar__brand">
            <?php sb_render_logo('/sport_blog/admin/admin.php'); ?>
            <span class="admin-sidebar__badge">Admin</span>
        </div>

        <nav class="admin-sidebar__nav" aria-label="ადმინის ნავიგაცია">
            <?php
            admin_nav_group('კონტენტი', [
                ['href' => '/sport_blog/admin/admin.php', 'icon' => '▦', 'label' => 'დეშბორდი'],
                ['href' => '/sport_blog/admin/admin_articles.php', 'icon' => '✎', 'label' => 'სტატიები'],
                ['href' => '/sport_blog/admin/add_article.php', 'icon' => '+', 'label' => 'ახალი სტატია'],
                ['href' => '/sport_blog/admin/stories.php', 'icon' => '◉', 'label' => 'სთორები'],
                ['href' => '/sport_blog/admin/media.php', 'icon' => '▣', 'label' => 'მედია'],
                ['href' => '/sport_blog/admin/tag_aliases.php', 'icon' => '=', 'label' => 'თეგების aliases'],
            ]);
            admin_nav_group('ინტერაქცია', [
                ['href' => '/sport_blog/admin/quizzes.php', 'icon' => '?', 'label' => 'ქვიზები'],
                ['href' => '/sport_blog/admin/quiz_games.php', 'icon' => '★', 'label' => 'სტატისტიკური ქვიზები'],
                ['href' => '/sport_blog/admin/edit_iframes.php', 'icon' => '▶', 'label' => 'მატჩის iframe'],
                ['href' => '/sport_blog/admin/manage_UFC_F1_event.php', 'icon' => '⚑', 'label' => 'UFC / F1'],
            ]);
            admin_nav_group('სისტემა', [
                ['href' => '/sport_blog/admin/user_list.php', 'icon' => '☻', 'label' => 'მომხმარებლები'],
                ['href' => '/sport_blog/admin/send_notification.php', 'icon' => '!', 'label' => 'შეტყობინებები'],
                ['href' => '/sport_blog/admin/analytics.php', 'icon' => '#', 'label' => 'ანალიტიკა'],
                ['href' => '/sport_blog/admin/import/admin_panel.php', 'icon' => '↻', 'label' => 'მონაცემების იმპორტი'],
            ]);
            ?>
        </nav>

        <a class="admin-sidebar__site-link" href="/sport_blog/">
            <span class="admin-sidebar__icon">↗</span>
            <span>საიტზე დაბრუნება</span>
        </a>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <div>
                <p class="admin-eyebrow">მართვის პანელი</p>
                <h1><?= $safeTitle ?></h1>
            </div>
            <div class="admin-topbar__actions">
                <?php sb_render_theme_toggle(); ?>
                <span class="admin-topbar__user"><?= $safeUserName ?></span>
                <a href="/sport_blog/views/profile" class="admin-button admin-button--neutral">პროფილი</a>
                <a href="/sport_blog/auth_system/logout" class="admin-button admin-button--danger">გასვლა</a>
            </div>
        </header>
    <?php
}

function admin_page_end(): void
{
    ?>
    </main>
    <script>
    document.querySelectorAll("[data-theme-toggle]").forEach((b) => b.addEventListener("click", () => {
        const r = document.documentElement, next = r.dataset.theme === "light" ? "dark" : "light";
        r.dataset.theme = next;
        try { localStorage.setItem("sb-theme", next); } catch (e) {}
    }));
    </script>
    </body>
    </html>
    <?php
}

function admin_csrf_field(): void
{
    ?>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php
}

function admin_alert(?string $message, string $type = 'success'): void
{
    $message = trim((string)$message);
    if ($message === '') {
        return;
    }
    $class = $type === 'error' ? 'admin-alert admin-alert--error' : 'admin-alert admin-alert--success';
    ?>
    <div class="<?= $class ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php
}
