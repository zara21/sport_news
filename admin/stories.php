<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/media_helpers.php';

require_admin_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $articleId = (int)($_POST['article_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($articleId > 0) {
        if ($action === 'disable') {
            $stmt = $pdo->prepare('UPDATE articles SET show_in_story = 0, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$articleId]);
            $_SESSION['success_message'] = 'სთორი გამოირთო.';
        }

        if ($action === 'extend') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $stmt = $pdo->prepare('UPDATE articles SET show_in_story = 1, story_expires_at = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$expiresAt, $articleId]);
            $_SESSION['success_message'] = 'სთორი 24 საათით გახანგრძლივდა.';
        }
    }

    header('Location: stories.php');
    exit;
}

$filter = $_GET['filter'] ?? 'active';
$where = "a.show_in_story = 1";
if ($filter === 'expired') {
    $where .= " AND a.story_expires_at IS NOT NULL AND a.story_expires_at <= NOW()";
} elseif ($filter === 'all') {
    $where = "a.show_in_story = 1";
} else {
    $where .= " AND (a.story_expires_at IS NULL OR a.story_expires_at > NOW())";
}

$stories = $pdo->query("
    SELECT a.id, a.title, a.slug, a.image, a.story_image, a.story_expires_at, a.created_at,
           a.views, u.first_name, u.last_name
    FROM articles a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE {$where}
    ORDER BY
        CASE WHEN a.story_expires_at IS NULL THEN 1 ELSE 0 END,
        a.story_expires_at ASC,
        a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$clubStories = $pdo->query("
    SELECT c.club_name, c.logo_url,
           a.id AS article_id, a.title, a.slug, a.story_expires_at
    FROM club_logos c
    LEFT JOIN article_tags at ON at.tag_id = c.tag_id
    LEFT JOIN articles a ON a.id = at.article_id
        AND a.show_in_story = 1
        AND (a.story_expires_at IS NULL OR a.story_expires_at > NOW())
        AND a.status = 'published'
    ORDER BY c.club_name ASC, a.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

admin_page_start('სთორების მართვა');
?>

<section class="admin-dashboard">
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="admin-alert admin-alert--success"><?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <section class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2>აქტიური სთორები</h2>
                <p class="admin-muted">აქედან შეგიძლია სთორის გამორთვა ან ვადის 24 საათით გახანგრძლივება.</p>
            </div>
            <div class="admin-actions">
                <a class="admin-button admin-button--neutral" href="stories.php?filter=active">აქტიური</a>
                <a class="admin-button admin-button--neutral" href="stories.php?filter=expired">ვადაგასული</a>
                <a class="admin-button admin-button--neutral" href="stories.php?filter=all">ყველა</a>
            </div>
        </div>

        <div class="admin-story-grid">
            <?php if (empty($stories)): ?>
                <p class="admin-muted">ამ ფილტრში სთორი ჯერ არ არის.</p>
            <?php endif; ?>

            <?php foreach ($stories as $story): ?>
                <?php
                $expired = !empty($story['story_expires_at']) && strtotime($story['story_expires_at']) <= time();
                $author = trim(($story['first_name'] ?? '') . ' ' . ($story['last_name'] ?? ''));
                ?>
                <article class="admin-story-card">
                    <img src="<?= htmlspecialchars(article_story_image_url($story), ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <div>
                        <span class="admin-badge"><?= $expired ? 'ვადაგასული' : 'აქტიური' ?></span>
                        <h3><?= htmlspecialchars($story['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($author ?: 'უცნობი ავტორი', ENT_QUOTES, 'UTF-8') ?> · ნახვები: <?= (int)($story['views'] ?? 0) ?></p>
                        <p>ვადა: <?= htmlspecialchars($story['story_expires_at'] ?: 'უსასრულო', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="admin-actions">
                        <a class="admin-button admin-button--neutral" href="/sport_blog/news/<?= htmlspecialchars($story['slug'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">ნახვა</a>
                        <a class="admin-button admin-button--neutral" href="edit_article.php?id=<?= (int)$story['id'] ?>">რედაქტირება</a>
                        <form class="admin-inline-form" method="post">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="article_id" value="<?= (int)$story['id'] ?>">
                            <button class="admin-button admin-button--primary" type="submit" name="action" value="extend">+24სთ</button>
                        </form>
                        <form class="admin-inline-form" method="post">
                            <?php admin_csrf_field(); ?>
                            <input type="hidden" name="article_id" value="<?= (int)$story['id'] ?>">
                            <button class="admin-button admin-button--danger" type="submit" name="action" value="disable">გამორთვა</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2>ტოპ კლუბების დაფარვა</h2>
                <p class="admin-muted">მწვანე ნიშნავს, რომ კლუბს ამჟამად აქტიური სთორი აქვს.</p>
            </div>
            <a class="admin-button admin-button--primary" href="add_article.php">ახალი სთორი</a>
        </div>
        <div class="admin-club-grid">
            <?php foreach ($clubStories as $club): ?>
                <div class="admin-club-chip <?= !empty($club['article_id']) ? 'admin-club-chip--active' : '' ?>">
                    <img src="<?= htmlspecialchars($club['logo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <div>
                        <strong><?= htmlspecialchars($club['club_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= !empty($club['article_id']) ? htmlspecialchars($club['title'], ENT_QUOTES, 'UTF-8') : 'აქტიური სთორი არ აქვს' ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<?php admin_page_end(); ?>
