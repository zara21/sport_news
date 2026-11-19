<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';

$category = $category ?? 'ფეხბურთი';
$categories = is_array($category) ? $category : [$category];
$categoryPlaceholders = [];
$categoryParams = [];
foreach ($categories as $index => $cat) {
    $key = ':category_' . $index;
    $categoryPlaceholders[] = $key;
    $categoryParams[$key] = $cat;
}
$title = $title ?? $category;
$per_page = 15;
$page = isset($_GET['page']) ? max((int) $_GET['page'], 1) : 1;
$offset = ($page - 1) * $per_page;

$featured_stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name
    FROM articles a
    JOIN users u ON a.user_id = u.id
    WHERE a.category IN (" . implode(',', $categoryPlaceholders) . ")
      AND a.status = 'published'
      AND a.slug NOT LIKE 'default-story-%'
    ORDER BY a.created_at DESC
    LIMIT 1
");
$featured_stmt->execute($categoryParams);
$featured = $featured_stmt->fetch(PDO::FETCH_ASSOC);
$featured_id = $featured ? (int) $featured['id'] : 0;

$articles_stmt = $pdo->prepare("
    SELECT a.*, u.first_name, u.last_name
    FROM articles a
    JOIN users u ON a.user_id = u.id
    WHERE a.category IN (" . implode(',', $categoryPlaceholders) . ")
      AND a.status = 'published'
      AND a.slug NOT LIKE 'default-story-%'
      AND a.id != :featured_id
    ORDER BY a.created_at DESC
    LIMIT :offset, :limit
");
foreach ($categoryParams as $key => $cat) {
    $articles_stmt->bindValue($key, $cat, PDO::PARAM_STR);
}
$articles_stmt->bindValue(':featured_id', $featured_id, PDO::PARAM_INT);
$articles_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$articles_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$articles_stmt->execute();
$articles = $articles_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> | Sport Blog</title>

    <link rel="stylesheet" href="/sport_blog/assets/vendor/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119c">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119c">
    <link rel="stylesheet" href="/sport_blog/css/category.css?v=20261119c">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<main class="sport-articles-page">
    <section class="sport-articles-container">
        <header class="sport-articles-heading">
            <span class="sport-articles-kicker">კატეგორია</span>
            <h1><?= htmlspecialchars($title) ?></h1>
        </header>

        <?php if ($page === 1 && $featured): ?>
            <article class="sport-articles-article-card sport-articles-featured-article">
                <a href="/sport_blog/news/<?= htmlspecialchars($featured['slug']) ?>" class="sport-articles-image-link">
                    <img src="<?= htmlspecialchars(article_image_url($featured['image'] ?? null)) ?>" alt="<?= htmlspecialchars($featured['title']) ?>" class="sport-articles-featured-image">
                </a>

                <div class="sport-articles-featured-content">
                    <h2>
                        <a href="/sport_blog/news/<?= htmlspecialchars($featured['slug']) ?>">
                            <?= htmlspecialchars($featured['title']) ?>
                        </a>
                    </h2>
                    <p class="sport-articles-summary"><?= htmlspecialchars(mb_substr(strip_tags($featured['content']), 0, 200)) ?>...</p>

                    <div class="sport-articles-article-footer">
                        <span>ავტორი: <?= htmlspecialchars($featured['first_name'] . ' ' . $featured['last_name']) ?></span>
                        <span>გამოქვეყნდა: <?= date('d.m.Y', strtotime($featured['created_at'])) ?></span>
                    </div>
                </div>
            </article>
        <?php endif; ?>

        <div class="sport-articles-list">
            <?php foreach ($articles as $article): ?>
                <article class="sport-articles-article-card">
                    <a href="/sport_blog/news/<?= htmlspecialchars($article['slug']) ?>" class="sport-articles-image-link">
                        <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null)) ?>" class="sport-articles-article-image" alt="<?= htmlspecialchars($article['title']) ?>">
                    </a>

                    <div class="sport-articles-article-content">
                        <h3>
                            <a href="/sport_blog/news/<?= htmlspecialchars($article['slug']) ?>">
                                <?= htmlspecialchars($article['title']) ?>
                            </a>
                        </h3>
                        <p class="sport-articles-summary"><?= htmlspecialchars(mb_substr(strip_tags($article['content']), 0, 150)) ?>...</p>
                        <div class="sport-articles-article-footer">
                            <span>ავტორი: <?= htmlspecialchars($article['first_name'] . ' ' . $article['last_name']) ?></span>
                            <span>თარიღი: <?= date('d.m.Y', strtotime($article['created_at'])) ?></span>
                        </div>
                        <a href="/sport_blog/news/<?= htmlspecialchars($article['slug']) ?>" class="sport-articles-read-more-btn">სრულად</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if (!$featured && !$articles): ?>
            <div class="sport-articles-empty">ამ კატეგორიაში სტატია ჯერ არ არის დამატებული.</div>
        <?php endif; ?>

        <nav class="sport-articles-pagination" aria-label="გვერდები">
            <?php if ($page > 1): ?>
                <a href="/sport_blog/category/<?= htmlspecialchars($slug ?? '') ?>?page=<?= $page - 1 ?>" class="sport-articles-pagination-btn">← უკან</a>
            <?php endif; ?>
            <?php if (count($articles) === $per_page): ?>
                <a href="/sport_blog/category/<?= htmlspecialchars($slug ?? '') ?>?page=<?= $page + 1 ?>" class="sport-articles-pagination-btn">მეტის ნახვა →</a>
            <?php endif; ?>
        </nav>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
