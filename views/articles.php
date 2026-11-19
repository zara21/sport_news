<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/club_helpers.php';

$authorId = isset($_GET['author']) ? (int)$_GET['author'] : 0;
$tagId = isset($_GET['tag']) ? (int)$_GET['tag'] : 0;
$clubQuery = trim((string) ($_GET['club'] ?? ''));
$clubName = $clubQuery !== '' ? (canonical_club_name($clubQuery) ?: $clubQuery) : null;
$articleSearch = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120, 'UTF-8') : '';
$isSearch = $articleSearch !== '';
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = (min($page, intdiv(PHP_INT_MAX, $perPage)) - 1) * $perPage;
$totalArticles = 0;
$pageTitle = 'სტატიები';
$kicker = 'სტატიები';
$description = 'ყველა გამოქვეყნებული სტატია';

if ($isSearch) {
    $pageTitle = 'ძებნის შედეგები';
    $pattern = '%' . strtr($articleSearch, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $searchFilter = "a.status = 'published' AND a.slug <> '' AND a.slug NOT LIKE 'default-story-%'
        AND (a.title LIKE ? ESCAPE '!' OR a.summary LIKE ? ESCAPE '!' OR a.content LIKE ? ESCAPE '!')";
    $count = $pdo->prepare("SELECT COUNT(*) FROM articles a WHERE {$searchFilter}");
    $count->execute([$pattern, $pattern, $pattern]);
    $totalArticles = (int) $count->fetchColumn();
    $description = '„' . $articleSearch . '“ — ნაპოვნია ' . $totalArticles;

    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE {$searchFilter}
        ORDER BY (a.title LIKE ? ESCAPE '!') DESC, a.created_at DESC, a.id DESC
        LIMIT ? OFFSET ?");
    foreach ([1, 2, 3, 4] as $parameter) {
        $stmt->bindValue($parameter, $pattern);
    }
    $stmt->bindValue(5, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(6, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($clubName !== null) {
    $aliases = club_tag_aliases($clubName) ?: [$clubName];
    $placeholders = implode(',', array_fill(0, count($aliases), '?'));
    $pageTitle = $clubName;
    $kicker = 'კლუბი';
    $description = $clubName . '-ის შესახებ გამოქვეყნებული სტატიები';

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT a.id)
        FROM articles a
        INNER JOIN article_tags at ON a.id = at.article_id
        INNER JOIN tags t ON t.id = at.tag_id
        WHERE t.name IN ($placeholders)
          AND a.status = 'published'");
    $stmt->execute($aliases);
    $totalArticles = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT DISTINCT a.*, u.first_name, u.last_name
        FROM articles a
        INNER JOIN article_tags at ON a.id = at.article_id
        INNER JOIN tags t ON t.id = at.tag_id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE t.name IN ($placeholders)
          AND a.status = 'published'
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?");
    $parameter = 1;
    foreach ($aliases as $alias) {
        $stmt->bindValue($parameter++, $alias, PDO::PARAM_STR);
    }
    $stmt->bindValue($parameter++, $perPage, PDO::PARAM_INT);
    $stmt->bindValue($parameter, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($authorId > 0) {
    $authorStmt = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
    $authorStmt->execute([$authorId]);
    $author = $authorStmt->fetch(PDO::FETCH_ASSOC);
    $authorName = trim(($author['first_name'] ?? '') . ' ' . ($author['last_name'] ?? ''));
    $pageTitle = $authorName !== '' ? $authorName : 'ავტორი';
    $kicker = 'ავტორი';
    $description = 'ამ ავტორის სტატიები';

    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM articles a
        WHERE a.user_id = ? AND a.status = "published"
    ');
    $stmt->execute([$authorId]);
    $totalArticles = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT a.*, u.first_name, u.last_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.user_id = ? AND a.status = "published"
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $authorId, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($tagId > 0) {
    $tagStmt = $pdo->prepare('SELECT name FROM tags WHERE id = ? LIMIT 1');
    $tagStmt->execute([$tagId]);
    $tagName = $tagStmt->fetchColumn();
    $canonicalClub = canonical_club_name((string) $tagName);
    if ($canonicalClub !== null) {
        $query = ['club' => $canonicalClub];
        if ($page > 1) $query['page'] = $page;
        header('Location: ' . site_path('/views/articles?' . http_build_query($query)));
        exit;
    }
    $pageTitle = $tagName ?: 'თეგი';
    $kicker = 'თეგი';
    $description = 'ამ თეგით მონიშნული სტატიები';

    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT a.id)
        FROM articles a
        INNER JOIN article_tags at ON a.id = at.article_id
        WHERE at.tag_id = ?
          AND a.status = "published"
          AND a.slug NOT LIKE "default-story-%"
    ');
    $stmt->execute([$tagId]);
    $totalArticles = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT DISTINCT a.*, u.first_name, u.last_name
        FROM articles a
        INNER JOIN article_tags at ON a.id = at.article_id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE at.tag_id = ?
          AND a.status = "published"
          AND a.slug NOT LIKE "default-story-%"
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $tagId, PDO::PARAM_INT);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $totalArticles = (int)$pdo->query('
        SELECT COUNT(*)
        FROM articles
        WHERE status = "published"
          AND slug NOT LIKE "default-story-%"
    ')->fetchColumn();

    $stmt = $pdo->prepare('
        SELECT a.*, u.first_name, u.last_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.status = "published"
          AND a.slug NOT LIKE "default-story-%"
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = max(1, (int)ceil($totalArticles / $perPage));
if ($page > $totalPages) {
    $query = $_GET;
    $query['page'] = $totalPages;
    header('Location: /sport_blog/views/articles.php?' . http_build_query($query));
    exit;
}

function articles_page_url(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;
    return '/sport_blog/views/articles.php?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Sport Blog</title>
    <?php if ($isSearch): ?>
        <meta name="robots" content="noindex,follow">
    <?php endif; ?>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/category.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/article.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="sport-articles-page">
    <section class="sport-articles-container">
        <header class="sport-articles-heading">
            <span class="sport-articles-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></span>
            <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
        </header>

        <?php if (empty($articles)): ?>
            <div class="sport-articles-empty">სტატიები ვერ მოიძებნა.</div>
        <?php else: ?>
            <div class="sport-articles-list">
                <?php foreach ($articles as $article): ?>
                    <?php $slug = rawurlencode($article['slug']); ?>
                    <article class="sport-articles-article-card">
                        <a href="/sport_blog/news/<?= $slug ?>" class="sport-articles-image-link">
                            <img src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" class="sport-articles-article-image" loading="lazy">
                        </a>
                        <div class="sport-articles-article-content">
                            <h3>
                                <a href="/sport_blog/news/<?= $slug ?>">
                                    <?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </h3>
                            <p class="sport-articles-summary">
                                <?= htmlspecialchars(mb_strimwidth(strip_tags($article['summary'] ?: $article['content']), 0, 170, '...'), ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <div class="sport-articles-article-footer">
                                <span><?= date('Y-m-d', strtotime($article['created_at'])) ?></span>
                                <?php $authorName = trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? '')); ?>
                                <?php if ($authorName !== ''): ?>
                                    <span><?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="/sport_blog/news/<?= $slug ?>" class="sport-articles-read-more-btn">სრულად</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="sport-articles-pagination" aria-label="სტატიების გვერდები">
                    <?php if ($page > 1): ?>
                        <a href="<?= htmlspecialchars(articles_page_url($page - 1), ENT_QUOTES, 'UTF-8') ?>" class="sport-articles-pagination-btn">წინა</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="<?= htmlspecialchars(articles_page_url($i), ENT_QUOTES, 'UTF-8') ?>" class="sport-articles-pagination-btn <?= $i === $page ? 'sport-articles-pagination-btn--active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= htmlspecialchars(articles_page_url($page + 1), ENT_QUOTES, 'UTF-8') ?>" class="sport-articles-pagination-btn">შემდეგი</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
