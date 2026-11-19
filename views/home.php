<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';

$stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name
    FROM articles a
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'published'
      AND a.slug NOT LIKE 'default-story-%'
    ORDER BY a.created_at DESC
    LIMIT 13
");
$stmt->execute();
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latestArticles = array_slice($articles, 3, 10);
?>

<section class="latest-news-section" aria-label="ბოლო სიახლეები">
    <div class="home-section-heading">
        <div>
            <h2>ახალი სტატიები</h2>
        </div>
        <a href="/sport_blog/views/articles.php" class="home-section-link">ყველა სტატია</a>
    </div>

    <div class="latest-news-grid">
        <?php foreach ($latestArticles as $article): ?>
            <?php $author = trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? '')); ?>
            <article class="article-card latest-news-card">
                <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="latest-news-card__image">
                    <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"<?= sb_srcset(article_image_url($article['image'] ?? null), '(max-width: 760px) 100vw, (max-width: 1180px) 33vw, 20vw', 960) ?> alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" width="640" height="360" loading="lazy" decoding="async">
                </a>
                <div class="latest-news-card__body">
                    <span class="latest-news-card__category"><?= htmlspecialchars($article['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <h3>
                        <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </h3>
                    <p><?= htmlspecialchars(mb_strimwidth(strip_tags($article['summary'] ?: $article['content']), 0, 120, '...', 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="latest-news-card__meta">
                        <span><?= htmlspecialchars($author ?: 'Sport Blog', ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= date('d.m.Y', strtotime($article['created_at'])) ?></span>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
