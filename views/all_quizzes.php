<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$search = trim((string) ($_GET['search'] ?? ''));
$type = (string) ($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'classic', 'squad'], true)) {
    $type = 'all';
}

$union = "(SELECT id, title, slug, quiz_image, created_at, 'classic' AS type FROM quizzes)
    UNION ALL
    (SELECT id, title, slug, image AS quiz_image, created_at, 'squad' AS type FROM quiz_games)";
$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = 'title LIKE :search';
    $params[':search'] = '%' . $search . '%';
}
if ($type !== 'all') {
    $conditions[] = 'type = :type';
    $params[':type'] = $type;
}
$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$union}) quiz_items{$where}");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT id, title, slug, quiz_image, created_at, type
    FROM ({$union}) quiz_items{$where}
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function quiz_list_url(int $page, string $search, string $type): string
{
    $query = array_filter(['page' => $page, 'search' => $search, 'type' => $type === 'all' ? '' : $type], fn($value) => $value !== '');
    return '/sport_blog/views/all_quizzes?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title>ყველა ქვიზი | Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119g">
    <link rel="stylesheet" href="/sport_blog/css/category.css?v=20261119g">
</head>
<body class="sport-body">
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<main class="sport-articles-page">
    <section class="sport-articles-container quiz-page">
        <header class="sport-articles-heading"><span class="sport-articles-kicker">ქვიზები</span><h1>ყველა ქვიზი</h1></header>
        <form class="quiz-filters" method="get">
            <input type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="ქვიზის ძებნა...">
            <select name="type">
                <option value="all">ყველა ტიპი</option>
                <option value="classic" <?= $type === 'classic' ? 'selected' : '' ?>>სტანდარტული</option>
                <option value="squad" <?= $type === 'squad' ? 'selected' : '' ?>>სეზონის გუნდი</option>
            </select>
            <button type="submit" class="quiz-filter-button"><i class="fas fa-search" aria-hidden="true"></i><span>ძებნა</span></button>
        </form>

        <?php if (!$quizzes): ?>
            <div class="sport-articles-empty">ქვიზი ვერ მოიძებნა.</div>
        <?php else: ?>
            <div class="quiz-container">
                <?php foreach ($quizzes as $quiz):
                    $image = $quiz['type'] === 'classic'
                        ? article_image_url($quiz['quiz_image'] ?? null, 'uploads/quizzes')
                        : article_image_url($quiz['quiz_image'] ?? null);
                ?>
                    <a class="quiz-card" href="/sport_blog/quizzes/<?= rawurlencode($quiz['slug']) ?>">
                        <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" width="640" height="400" loading="lazy" decoding="async">
                        <div class="quiz-card-content">
                            <h3><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="quiz-type"><?= $quiz['type'] === 'classic' ? 'სტანდარტული ქვიზი' : 'სეზონის გუნდის ქვიზი' ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="sport-articles-pagination" aria-label="ქვიზების გვერდები">
                <?php for ($number = 1; $number <= $totalPages; $number++): ?>
                    <a class="sport-articles-pagination-btn <?= $number === $page ? 'sport-articles-pagination-btn--active' : '' ?>" href="<?= htmlspecialchars(quiz_list_url($number, $search, $type), ENT_QUOTES, 'UTF-8') ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
