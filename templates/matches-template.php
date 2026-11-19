<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/embed_helpers.php';

function currentMatchSlug(): string
{
    if (!empty($GLOBALS['slug'])) {
        return trim((string) $GLOBALS['slug']);
    }

    if (!empty($_GET['slug'])) {
        return trim((string) $_GET['slug']);
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $parts = explode('/', trim($path, '/'));
    $last = urldecode((string) end($parts));

    return preg_replace('/\.php$/i', '', $last);
}

function matchEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$slug = currentMatchSlug();
$match = null;
$iframeLive = '';

if ($slug !== '' && $slug !== 'index') {
    $stmt = $pdo->prepare('SELECT * FROM matches WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $match = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($match) {
        $iframeStmt = $pdo->prepare('SELECT iframe_live FROM match_iframes WHERE slug = :slug LIMIT 1');
        $iframeStmt->execute([':slug' => $slug]);
        $iframeLive = normalize_embed_url($iframeStmt->fetchColumn() ?: '');
    }
}

$pageTitle = $match
    ? ($match['home_team'] . ' vs ' . $match['away_team'])
    : 'მატჩი ვერ მოიძებნა';
$status = strtolower(trim((string)($match['match_status'] ?? '')));
$isLive = $status === 'live' || $status === 'half time' || is_numeric($status);
$isFinished = in_array($status, ['finished', 'after pen.'], true);
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= matchEsc($pageTitle) ?> - Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119c">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119c">
    <link rel="stylesheet" href="/sport_blog/css/match.css?v=20261119c">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="match-page">
    <?php if (!$match): ?>
        <section class="match-not-found">
            <span>404</span>
            <h1>მატჩი ვერ მოიძებნა</h1>
            <p>მოთხოვნილი მატჩი არ არსებობს ან ჯერ არ არის დამატებული.</p>
            <a href="/sport_blog/" class="match-button">მთავარზე დაბრუნება</a>
        </section>
    <?php else: ?>
        <section class="match-card">
            <div class="match-kicker"><?= matchEsc($match['tournament_name'] ?? 'მატჩი') ?></div>

            <div class="match-scoreboard">
                <div class="match-team match-team--home">
                    <?php if (!empty($match['home_logo'])): ?>
                        <img src="<?= matchEsc($match['home_logo']) ?>" alt="<?= matchEsc($match['home_team']) ?>">
                    <?php endif; ?>
                    <strong><?= matchEsc($match['home_team']) ?></strong>
                </div>

                <div class="match-score">
                    <?php if ($isFinished || $isLive): ?>
                        <span><?= matchEsc($match['home_goals'] ?? 0) ?></span>
                        <span>-</span>
                        <span><?= matchEsc($match['away_goals'] ?? 0) ?></span>
                    <?php else: ?>
                        <span class="match-score__dash">VS</span>
                    <?php endif; ?>
                </div>

                <div class="match-team match-team--away">
                    <?php if (!empty($match['away_logo'])): ?>
                        <img src="<?= matchEsc($match['away_logo']) ?>" alt="<?= matchEsc($match['away_team']) ?>">
                    <?php endif; ?>
                    <strong><?= matchEsc($match['away_team']) ?></strong>
                </div>
            </div>

            <div class="match-status <?= $isLive ? 'match-status--live' : '' ?> <?= $isFinished ? 'match-status--finished' : '' ?>">
                <?php if ($isFinished): ?>
                    დასრულდა
                <?php elseif ($status === 'half time'): ?>
                    ტაიმებს შორის შესვენება
                <?php elseif (is_numeric($status)): ?>
                    <?= matchEsc($status) ?>'
                <?php elseif ($status === 'live'): ?>
                    LIVE
                <?php else: ?>
                    <?= matchEsc($match['match_time'] ?: 'დრო დასაზუსტებელია') ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($iframeLive)): ?>
                <div class="match-live-frame">
                    <iframe src="<?= matchEsc($iframeLive) ?>" title="Live match" allowfullscreen></iframe>
                </div>
            <?php else: ?>
                <p class="match-live-empty">ლაივი ამ მატჩისთვის ხელმისაწვდომი არ არის.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
