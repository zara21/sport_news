<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/seo_helpers.php';
require_once __DIR__ . '/../includes/auth_helpers.php';
require_once __DIR__ . '/../includes/user_activity_helpers.php';
require_once __DIR__ . '/../includes/embed_helpers.php';

function currentArticleSlug(): string
{
    if (!empty($GLOBALS['slug'])) {
        return trim((string) $GLOBALS['slug']);
    }

    if (!empty($_GET['slug'])) {
        return trim((string) $_GET['slug']);
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $path = trim($path, '/');
    $parts = explode('/', $path);
    $last = urldecode((string) end($parts));

    return preg_replace('/\.php$/i', '', $last);
}

$slug = currentArticleSlug();
$article = null;
$similarArticles = [];
$relatedArticles = [];
$tags = [];
$tagRows = [];

if ($slug !== '' && $slug !== 'index') {
    $stmt = $pdo->prepare('
        SELECT a.*, u.first_name, u.last_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.slug = ? AND a.status = "published"
        LIMIT 1
    ');
    $stmt->execute([$slug]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($article) {
    $pdo->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([(int) $article['id']]);
    if (!empty($_SESSION['user_id'])) record_article_read($pdo, (int) $_SESSION['user_id'], (int) $article['id']);
    $similarStmt = $pdo->prepare('
        SELECT title, slug, image, created_at
        FROM articles
        WHERE category = ? AND slug != ? AND status = "published"
        ORDER BY created_at DESC
        LIMIT 7
    ');
    $similarStmt->execute([$article['category'], $slug]);
    $similarArticles = $similarStmt->fetchAll(PDO::FETCH_ASSOC);

    $tagStmt = $pdo->prepare('
        SELECT t.id, t.name
        FROM tags t
        INNER JOIN article_tags at ON t.id = at.tag_id
        WHERE at.article_id = ?
    ');
    $tagStmt->execute([$article['id']]);
    $tagRows = $tagStmt->fetchAll(PDO::FETCH_ASSOC);
    $tags = array_column($tagRows, 'name');
    $tagIds = array_column($tagRows, 'id');

    if (!empty($tagIds)) {
        $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
        $relatedStmt = $pdo->prepare("
            SELECT DISTINCT a.title, a.slug, a.image, a.created_at
            FROM articles a
            INNER JOIN article_tags at ON a.id = at.article_id
            WHERE at.tag_id IN ($placeholders)
              AND a.id != ?
              AND a.status = 'published'
            ORDER BY a.created_at DESC
            LIMIT 4
        ");
        $relatedStmt->execute([...$tagIds, $article['id']]);
        $relatedArticles = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = $article['title'] ?? 'სტატია ვერ მოიძებნა';
$descriptionSource = $article['summary'] ?? ($article['content'] ?? '');
$description = seo_description($descriptionSource);
$articleUrl = '/sport_blog/news/' . rawurlencode($slug);
$shareUrl = absolute_site_url($articleUrl);
$canonicalUrl = $article ? $shareUrl : absolute_site_url('/sport_blog/');
$metaImage = absolute_site_url(article_image_url($article['image'] ?? null));
$jsonLd = $article ? article_json_ld($article, $canonicalUrl, $metaImage) : '';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="<?= $article ? 'index,follow' : 'noindex,follow' ?>">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="article">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($metaImage, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($jsonLd !== ''): ?>
        <script type="application/ld+json"><?= $jsonLd ?></script>
    <?php endif; ?>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/css/article.css?v=20261119d">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="article-page">
    <?php if (!$article): ?>
        <section class="article-not-found">
            <span>404</span>
            <h1>სტატია ვერ მოიძებნა</h1>
            <p>მოთხოვნილი სტატია არ არსებობს ან წაშლილია.</p>
            <a href="/sport_blog/" class="article-button">მთავარზე დაბრუნება</a>
        </section>
    <?php else: ?>
        <section class="article-layout">
            <article class="article-main">
                <div class="article-header">
                    <span class="article-category"><?= htmlspecialchars($article['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <h1><?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="article-meta">
                        <?php if (!empty($article['first_name']) || !empty($article['last_name'])): ?>
                            <a class="article-meta-link" href="/sport_blog/views/articles?author=<?= (int) $article['user_id'] ?>">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars(trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        <?php endif; ?>
                        <span><i class="far fa-calendar"></i> <?= date('Y-m-d', strtotime($article['created_at'])) ?></span>
                    </div>
                </div>

                <img class="article-hero-image" src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>">

                <?php if (!empty($article['summary'])): ?>
                    <p class="article-summary"><?= htmlspecialchars($article['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <div class="article-content">
                    <?= render_article_content($article['content']) ?>
                </div>

                <?php if (!empty($article['second_image'])): ?>
                    <img class="article-inline-image" src="/sport_blog/uploads/second_images/<?= htmlspecialchars($article['second_image'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                <?php endif; ?>

                <?php if (!empty($article['has_video']) && !empty($article['video'])): ?>
                    <div class="article-video">
                        <?php $youtubeEmbed = youtube_embed_url($article['video']); ?>
                        <?php if ($youtubeEmbed !== ''): ?>
                            <iframe src="<?= htmlspecialchars($youtubeEmbed, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                        <?php elseif (!preg_match('#[/\\\\:]#', $article['video'])): ?>
                            <video controls>
                                <source src="/sport_blog/uploads/videos/<?= htmlspecialchars($article['video'], ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
                            </video>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="article-bottom">
                    <?php if (!empty($tags)): ?>
                        <div class="article-tags">
                            <?php foreach ($tagRows as $tag): ?>
                                <a href="/sport_blog/views/articles?tag=<?= (int) $tag['id'] ?>">
                                    <?= htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <form method="post" action="/sport_blog/api/bookmark.php" class="article-bookmark-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="article_id" value="<?= (int) $article['id'] ?>">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="article-share"><?= article_is_bookmarked($pdo, (int) $_SESSION['user_id'], (int) $article['id']) ? 'რჩეულებიდან ამოღება' : 'მოგვიანებით წაკითხვა' ?></button>
                        </form>
                    <?php endif; ?>

                    <a class="article-share" href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($shareUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <i class="fab fa-facebook-f"></i>
                        გაზიარება
                    </a>
                </div>
            </article>

            <aside class="article-sidebar">
                <h2>მსგავსი სიახლეები</h2>
                <?php if (empty($similarArticles)): ?>
                    <p class="article-muted">ამ კატეგორიაში მსგავსი სიახლე ჯერ არ არის.</p>
                <?php endif; ?>
                <?php foreach ($similarArticles as $similar): ?>
                    <a class="article-side-card" href="/sport_blog/news/<?= htmlspecialchars($similar['slug'], ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= htmlspecialchars(article_image_url($similar['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <span><?= htmlspecialchars(mb_strimwidth($similar['title'], 0, 70, '...'), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </aside>
        </section>

        <?php if (!empty($relatedArticles)): ?>
            <section class="article-related">
                <h2>ამავე თემაზე</h2>
                <div class="article-related-grid">
                    <?php foreach ($relatedArticles as $related): ?>
                        <a class="article-related-card" href="/sport_blog/news/<?= htmlspecialchars($related['slug'], ENT_QUOTES, 'UTF-8') ?>">
                            <img src="<?= htmlspecialchars(article_image_url($related['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
                            <strong><?= htmlspecialchars(mb_strimwidth($related['title'], 0, 80, '...'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= date('Y-m-d', strtotime($related['created_at'])) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
