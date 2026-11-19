<?php
require __DIR__ . '/includes/db_connect.php';
require __DIR__ . '/includes/seo_helpers.php';

header('Content-Type: application/xml; charset=UTF-8');

$urls = [
    ['loc' => absolute_site_url('/sport_blog/'), 'changefreq' => 'hourly', 'priority' => '1.0'],
    ['loc' => absolute_site_url('/sport_blog/views/articles.php'), 'changefreq' => 'daily', 'priority' => '0.8'],
    ['loc' => absolute_site_url('/sport_blog/views/all_quizzes'), 'changefreq' => 'weekly', 'priority' => '0.7'],
    ['loc' => absolute_site_url('/sport_blog/views/contact'), 'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => absolute_site_url('/sport_blog/views/privacy'), 'changefreq' => 'yearly', 'priority' => '0.2'],
    ['loc' => absolute_site_url('/sport_blog/views/terms'), 'changefreq' => 'yearly', 'priority' => '0.2'],
];

foreach (['football', 'basketball', 'tennis', 'formula1', 'mma', 'rugby', 'other'] as $category) {
    $urls[] = ['loc' => absolute_site_url('/sport_blog/category/' . $category), 'changefreq' => 'daily', 'priority' => '0.8'];
}

$stmt = $pdo->query("
    SELECT slug, updated_at, created_at
    FROM articles
    WHERE status = 'published' AND slug IS NOT NULL AND slug != ''
    ORDER BY created_at DESC
    LIMIT 1000
");
foreach ($stmt as $article) {
    $urls[] = [
        'loc' => absolute_site_url('/sport_blog/news/' . rawurlencode($article['slug'])),
        'lastmod' => date('Y-m-d', strtotime($article['updated_at'] ?: $article['created_at'])),
        'changefreq' => 'weekly',
        'priority' => '0.7',
    ];
}

$quizStmt = $pdo->query("
    SELECT slug, created_at
    FROM quizzes
    WHERE slug IS NOT NULL AND slug != ''
    ORDER BY created_at DESC
    LIMIT 500
");
foreach ($quizStmt as $quiz) {
    $urls[] = [
        'loc' => absolute_site_url('/sport_blog/quizzes/' . rawurlencode($quiz['slug'])),
        'lastmod' => date('Y-m-d', strtotime($quiz['created_at'])),
        'changefreq' => 'monthly',
        'priority' => '0.5',
    ];
}

$quizGameStmt = $pdo->query("
    SELECT slug, created_at
    FROM quiz_games
    WHERE slug IS NOT NULL AND slug != ''
    ORDER BY created_at DESC
    LIMIT 500
");
foreach ($quizGameStmt as $quiz) {
    $urls[] = [
        'loc' => absolute_site_url('/sport_blog/quizzes/' . rawurlencode($quiz['slug'])),
        'lastmod' => date('Y-m-d', strtotime($quiz['created_at'])),
        'changefreq' => 'monthly',
        'priority' => '0.5',
    ];
}

$matchStmt = $pdo->query("
    SELECT slug
    FROM matches
    WHERE slug IS NOT NULL AND slug != ''
    ORDER BY id DESC
    LIMIT 1000
");
foreach ($matchStmt as $match) {
    $urls[] = [
        'loc' => absolute_site_url('/sport_blog/matches/' . rawurlencode($match['slug'])),
        'changefreq' => 'daily',
        'priority' => '0.5',
    ];
}

$eventStmt = $pdo->query("
    SELECT id, event_date
    FROM sports_events
    ORDER BY event_date DESC, id DESC
    LIMIT 500
");
foreach ($eventStmt as $event) {
    $urls[] = [
        'loc' => absolute_site_url('/sport_blog/events/' . (int) $event['id']),
        'lastmod' => date('Y-m-d', strtotime($event['event_date'])),
        'changefreq' => 'weekly',
        'priority' => '0.6',
    ];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') ?></loc>
    <?php if (!empty($url['lastmod'])): ?><lastmod><?= htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8') ?></lastmod><?php endif; ?>
    <changefreq><?= htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8') ?></changefreq>
    <priority><?= htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8') ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
