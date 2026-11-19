<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/cache_helpers.php';

$videoArticles = cache_remember('home.video-cards.v2', 180, function () use ($pdo): array {
    $stmt = $pdo->query("SELECT a.title, a.slug, a.image, u.first_name, u.last_name
        FROM articles a
        JOIN users u ON a.user_id = u.id
        WHERE a.has_video = 1 AND a.status = 'published'
        ORDER BY a.created_at DESC
        LIMIT 8");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});
?>

<?php if ($videoArticles): ?>
<section class="video-articles" id="video-news" aria-labelledby="videoNewsTitle">
    <div class="home-strip-heading">
        <div>
            <h2 id="videoNewsTitle">ვიდეო სიახლეები</h2>
        </div>
        <div class="home-strip-controls">
            <button type="button" class="home-icon-button" data-scroll-target="videoScroller" data-scroll-direction="-1" aria-label="წინა ვიდეოები">&#8249;</button>
            <button type="button" class="home-icon-button" data-scroll-target="videoScroller" data-scroll-direction="1" aria-label="შემდეგი ვიდეოები">&#8250;</button>
        </div>
    </div>
    <div class="video-scroll-container" id="videoScroller" tabindex="0" aria-label="ვიდეო სიახლეების სია">
        <?php foreach ($videoArticles as $article): ?>
            <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="video-card sb-card">
                <span class="video-thumbnail">
                    <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" width="480" height="270" loading="lazy" decoding="async">
                    <span class="video-play-icon" aria-hidden="true">&#9654;</span>
                </span>
                <span class="video-info">
                    <strong><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars(trim($article['first_name'] . ' ' . $article['last_name']), ENT_QUOTES, 'UTF-8') ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
