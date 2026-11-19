<?php
require __DIR__ . '/includes/db_connect.php';
require __DIR__ . '/includes/seo_helpers.php';
require __DIR__ . '/includes/media_helpers.php';

header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: public, max-age=600');

$x = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$articles = $pdo->query("
    SELECT a.title, a.slug, a.summary, a.content, a.category, a.image, a.created_at, a.updated_at,
           u.first_name, u.last_name
    FROM articles a
    LEFT JOIN users u ON u.id = a.user_id
    WHERE a.status = 'published' AND a.slug IS NOT NULL AND a.slug <> '' AND a.slug NOT LIKE 'default-story-%'
    ORDER BY a.created_at DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

$lastBuild = $articles ? date(DATE_RSS, strtotime($articles[0]['updated_at'] ?: $articles[0]['created_at'])) : date(DATE_RSS);
$self = absolute_site_url('/sport_blog/feed.xml');

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Sport Blog</title>
    <link><?= $x(absolute_site_url('/sport_blog/')) ?></link>
    <description>სპორტული სიახლეები, მატჩები, ცხრილები და ქვიზები</description>
    <language>ka</language>
    <lastBuildDate><?= $lastBuild ?></lastBuildDate>
    <atom:link href="<?= $x($self) ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($articles as $article):
    $url = absolute_site_url('/sport_blog/news/' . rawurlencode($article['slug']));
    $image = absolute_site_url(article_image_url($article['image'] ?? null));
    $author = trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? ''));
?>
    <item>
        <title><?= $x($article['title']) ?></title>
        <link><?= $x($url) ?></link>
        <guid isPermaLink="true"><?= $x($url) ?></guid>
        <pubDate><?= date(DATE_RSS, strtotime($article['created_at'])) ?></pubDate>
        <?php if ($article['category'] !== null && $article['category'] !== ''): ?><category><?= $x($article['category']) ?></category><?php endif; ?>
        <?php if ($author !== ''): ?><dc:creator><?= $x($author) ?></dc:creator><?php endif; ?>
        <description><?= $x(seo_description($article['summary'] ?: $article['content'], 300)) ?></description>
        <enclosure url="<?= $x($image) ?>" type="<?= str_ends_with(strtolower($image), '.svg') ? 'image/svg+xml' : 'image/jpeg' ?>" length="0"/>
    </item>
<?php endforeach; ?>
</channel>
</rss>
