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
require_once __DIR__ . '/../includes/comment_helpers.php';

function currentArticleSlug(): string
{
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
    if (!str_starts_with($path, site_path('/news/'))) {
        return '';
    }
    $last = rawurldecode(basename($path));
    if (strcasecmp($last, 'index.php') === 0 && is_string($_GET['slug'] ?? null)) {
        return trim($_GET['slug']);
    }
    return preg_replace('/\.php$/i', '', $last);
}

$slug = currentArticleSlug();
$legacySlugIds = ['-1745996214' => 45];
$legacyArticleId ??= $legacySlugIds[$slug] ?? null;
$article = null;
$similarArticles = [];
$relatedArticles = [];
$tags = [];
$tagRows = [];

if (isset($legacyArticleId) || ($slug !== '' && $slug !== 'index')) {
    $lookup = isset($legacyArticleId) ? 'a.id' : 'a.slug';
    $stmt = $pdo->prepare('
        SELECT a.*, u.first_name, u.last_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE ' . $lookup . ' = ? AND a.status = "published"
        LIMIT 1
    ');
    $stmt->execute([$legacyArticleId ?? $slug]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isLegacyIdPage = $slug === '';
if (isset($legacyArticleId) && !empty($article['slug']) && ($isLegacyIdPage || $slug !== $article['slug'])) {
    site_redirect('/news/' . rawurlencode($article['slug']), 301);
}

if (!$article || empty($article['slug'])) {
    $article = null;
    http_response_code(404);
}

if ($article) {
    $slug = $article['slug'];
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
$canonicalUrl = $article ? $shareUrl : '';
$metaImage = absolute_site_url(article_image_url($article['image'] ?? null));
$jsonLd = $article ? article_json_ld($article, $canonicalUrl, $metaImage) : '';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($article): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
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
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/article.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<?php if ($article): ?>
    <div class="reading-progress" aria-hidden="true"><span id="readingProgress"></span></div>
<?php endif; ?>
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
                        <?php
                        // კითხვის დრო: ~180 სიტყვა წუთში
                        $wordCount = count(preg_split('/\s+/u', trim(strip_tags(($article['summary'] ?? '') . ' ' . $article['content'])), -1, PREG_SPLIT_NO_EMPTY));
                        $readingMinutes = max(1, (int) ceil($wordCount / 180));
                        ?>
                        <span><i class="far fa-clock"></i> <?= $readingMinutes ?> წთ კითხვა</span>
                    </div>
                </div>

                <img class="article-hero-image" src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"<?= sb_srcset(article_image_url($article['image'] ?? null), '(max-width: 900px) 100vw, 820px', 1280) ?> alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>">

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

</div>

                <?php
                $shareText = $article['title'];
                $shareLinks = [
                    ['facebook', 'Facebook', 'fab fa-facebook-f', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($shareUrl)],
                    ['messenger', 'Messenger', 'fab fa-facebook-messenger', 'fb-messenger://share/?link=' . rawurlencode($shareUrl)],
                    ['telegram', 'Telegram', 'fab fa-telegram-plane', 'https://t.me/share/url?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareText)],
                    ['whatsapp', 'WhatsApp', 'fab fa-whatsapp', 'https://wa.me/?text=' . rawurlencode($shareText . ' ' . $shareUrl)],
                    ['x', 'X', 'fab fa-x-twitter', 'https://twitter.com/intent/tweet?url=' . rawurlencode($shareUrl) . '&text=' . rawurlencode($shareText)],
                ];
                ?>
                <div class="article-share-bar" data-share-url="<?= htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8') ?>" data-share-title="<?= htmlspecialchars($shareText, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="article-share-bar__label">გაზიარება</span>
                    <button type="button" class="article-share-bar__btn article-share-bar__btn--native" data-share-native hidden aria-label="გაზიარება"><i class="fas fa-share-nodes" aria-hidden="true"></i></button>
                    <?php foreach ($shareLinks as [$key, $label, $icon, $href]): ?>
                        <a class="article-share-bar__btn article-share-bar__btn--<?= $key ?><?= $key === 'messenger' ? ' article-share-bar__btn--mobile' : '' ?>" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= $label ?>" title="<?= $label ?>"><i class="<?= $icon ?>" aria-hidden="true"></i></a>
                    <?php endforeach; ?>
                    <button type="button" class="article-share-bar__btn" data-share-copy aria-label="ბმულის კოპირება" title="ბმულის კოპირება"><i class="fas fa-link" aria-hidden="true"></i></button>
                    <span class="article-share-bar__toast" data-share-toast role="status" aria-live="polite"></span>
                </div>

                <?php render_article_comments($pdo, $article, $articleUrl); ?>
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
                            <img src="<?= htmlspecialchars(article_image_url($related['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>"<?= sb_srcset(article_image_url($related['image'] ?? null), '(max-width: 760px) 100vw, 25vw', 640) ?> alt="">
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
<?php if ($article): ?>
<script>
  (() => {
    // კითხვის პროგრესი
    const bar = document.getElementById('readingProgress');
    const content = document.querySelector('.article-main');
    if (bar && content) {
      const update = () => {
        const rect = content.getBoundingClientRect();
        const total = rect.height - window.innerHeight * 0.6;
        const progress = total > 0 ? Math.min(1, Math.max(0, -rect.top / total)) : 1;
        bar.style.transform = `scaleX(${progress})`;
      };
      update();
      window.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update);
    }

    // გაზიარება
    const share = document.querySelector('.article-share-bar');
    if (!share) return;
    const url = share.dataset.shareUrl;
    const title = share.dataset.shareTitle;
    const toast = share.querySelector('[data-share-toast]');
    const showToast = (text) => {
      toast.textContent = text;
      toast.classList.add('is-visible');
      clearTimeout(showToast.timer);
      showToast.timer = setTimeout(() => toast.classList.remove('is-visible'), 2200);
    };

    const nativeButton = share.querySelector('[data-share-native]');
    if (navigator.share) {
      nativeButton.hidden = false;
      nativeButton.addEventListener('click', () => navigator.share({ title, url }).catch(() => {}));
    }

    share.querySelector('[data-share-copy]').addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(url);
      } catch (e) {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
      }
      showToast('ბმული დაკოპირდა');
    });
  })();
</script>
<?php endif; ?>
</body>
</html>
