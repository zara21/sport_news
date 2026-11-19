<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/cache_helpers.php';

$heroArticles = cache_remember('home.hero.v2', 120, function () use ($pdo): array {
    $stmt = $pdo->query("SELECT id, title, slug, image, has_video, category, created_at
        FROM articles
        WHERE status = 'published'
          AND slug NOT LIKE 'default-story-%'
        ORDER BY created_at DESC
        LIMIT 3");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

$latestArticles = cache_remember('home.latest-rail.v2', 120, function () use ($pdo): array {
    $stmt = $pdo->query("SELECT title, slug, image, created_at
        FROM articles
        WHERE status = 'published'
          AND slug NOT LIKE 'default-story-%'
        ORDER BY created_at DESC
        LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

$popularArticles = cache_remember('home.popular-rail.v2', 120, function () use ($pdo): array {
    $stmt = $pdo->query("SELECT title, slug, image, created_at
        FROM articles
        WHERE status = 'published' AND popular = 1
          AND slug NOT LIKE 'default-story-%'
        ORDER BY created_at DESC
        LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

$videoArticles = cache_remember('home.video-rail.v2', 120, function () use ($pdo): array {
    $stmt = $pdo->query("SELECT title, slug, image, created_at
        FROM articles
        WHERE status = 'published' AND has_video = 1
          AND slug NOT LIKE 'default-story-%'
        ORDER BY created_at DESC
        LIMIT 5");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
});

if (!$popularArticles) {
    $popularArticles = $latestArticles;
}

$articleLists = [
    'popular' => $popularArticles,
    'latest' => $latestArticles,
    'videos' => $videoArticles,
];
?>

<section class="home-hero" aria-label="მთავარი სიახლეები">
    <div class="home-hero__grid">
        <aside class="home-news-rail sb-card" aria-label="არჩეული სიახლეები">
            <div class="home-news-tabs" role="tablist" aria-label="სიახლეების ფილტრი">
                <button type="button" class="home-news-tab is-active" role="tab" aria-selected="true" data-news-tab="popular">პოპულარული</button>
                <button type="button" class="home-news-tab" role="tab" aria-selected="false" data-news-tab="latest">ახალი</button>
                <button type="button" class="home-news-tab" role="tab" aria-selected="false" data-news-tab="videos">ვიდეო</button>
            </div>

            <?php foreach ($articleLists as $listKey => $articles): ?>
                <div class="home-news-list <?= $listKey === 'popular' ? 'is-active' : '' ?>" data-news-panel="<?= $listKey ?>">
                    <?php foreach ($articles as $article): ?>
                        <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="home-news-list__item">
                            <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" width="90" height="64" loading="lazy" decoding="async">
                            <span><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </aside>

        <div class="home-feature-grid">
            <?php if (!empty($heroArticles[0])): $main = $heroArticles[0]; ?>
                <a href="/sport_blog/news/<?= htmlspecialchars($main['slug'], ENT_QUOTES, 'UTF-8') ?>" class="home-feature home-feature--main">
                    <img src="<?= htmlspecialchars(article_image_url($main['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($main['title'], ENT_QUOTES, 'UTF-8') ?>" width="1200" height="675" fetchpriority="high" decoding="async">
                    <span class="home-feature__overlay">
                        <span class="home-feature__category"><?= htmlspecialchars($main['category'] ?: 'სიახლე', ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= htmlspecialchars($main['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                    </span>
                </a>
            <?php endif; ?>

            <div class="home-feature__side">
                <?php foreach (array_slice($heroArticles, 1, 2) as $article): ?>
                    <a href="/sport_blog/news/<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" class="home-feature home-feature--side">
                        <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" width="640" height="360" loading="lazy" decoding="async">
                        <span class="home-feature__overlay">
                            <strong><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <aside class="home-standings sb-card" aria-labelledby="homeStandingsTitle">
            <div class="home-standings__header">
                <div>
                    <span class="home-section-kicker">ცხრილი</span>
                    <h2 id="homeStandingsTitle">ლიგის პოზიციები</h2>
                </div>
                <select id="leagueSelect" aria-label="ლიგის არჩევა">
                    <option value="premier_league">პრემიერ ლიგა</option>
                    <option value="la_liga">ლა ლიგა</option>
                    <option value="serie_a">სერია A</option>
                    <option value="ligue_1">ლიგა 1</option>
                    <option value="bundesliga">ბუნდესლიგა</option>
                </select>
            </div>
            <div class="home-standings__table-wrap">
                <table class="home-standings__table">
                    <thead><tr><th>#</th><th>გუნდი</th><th>თ</th><th>ქულა</th></tr></thead>
                    <tbody id="standingsBody"><tr><td colspan="4">იტვირთება...</td></tr></tbody>
                </table>
            </div>
        </aside>
    </div>
</section>
