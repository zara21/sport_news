<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/embed_helpers.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/club_helpers.php';

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
    if (!$match) http_response_code(404);

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
$isLive = $isLive || ($status !== '' && !$isFinished && !in_array($status, ['postponed', 'cancelled', 'canceled'], true));

$tournamentMatches = [];
$teamArticles = [];
if ($match) {
    $otherStmt = $pdo->prepare('SELECT slug, match_time, home_team, away_team, home_logo, away_logo, home_goals, away_goals, match_status
        FROM matches WHERE tournament_name = ? AND slug <> ? ORDER BY match_time ASC LIMIT 8');
    $otherStmt->execute([$match['tournament_name'], $match['slug']]);
    $tournamentMatches = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

    $aliases = array_values(array_unique(array_merge(
        database_club_aliases($pdo, (string) $match['home_team']),
        database_club_aliases($pdo, (string) $match['away_team'])
    )));
    if ($aliases) {
        $ph = implode(',', array_fill(0, count($aliases), '?'));
        $articleStmt = $pdo->prepare("SELECT DISTINCT a.title, a.slug, a.image, a.created_at
            FROM articles a JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id
            WHERE t.name IN ($ph) AND a.status = 'published' AND a.slug NOT LIKE 'default-story-%'
            ORDER BY a.created_at DESC LIMIT 4");
        $articleStmt->execute($aliases);
        $teamArticles = $articleStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function match_status_label(array $m): array
{
    $status = trim((string) ($m['match_status'] ?? ''));
    $lower = strtolower($status);
    if (in_array($lower, ['finished', 'after pen.', 'after et'], true)) {
        return ['finished', $lower === 'after pen.' ? 'დასრულდა (პენ.)' : 'დასრულდა'];
    }
    if (in_array($lower, ['postponed', 'cancelled', 'canceled'], true)) {
        return ['off', $lower === 'postponed' ? 'გადაიდო' : 'გაუქმდა'];
    }
    if ($lower === 'half time') {
        return ['live', 'შესვენება'];
    }
    if ($status !== '') {
        return ['live', is_numeric($status) ? $status . "'" : $status];
    }
    return ['scheduled', $m['match_time'] !== '' ? $m['match_time'] : 'TBD'];
}

function match_club_url(string $team): string
{
    return '/sport_blog/clubs/' . rawurlencode(canonical_club_name($team) ?: $team);
}

[$statusKind, $statusLabel] = $match ? match_status_label($match) : ['scheduled', ''];
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= matchEsc($pageTitle) ?> - Sport Blog</title>
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/match.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="match-page">
    <?php if (!$match): ?>
        <section class="match-not-found">
            <span>404</span>
            <h1>მატჩი ვერ მოიძებნა</h1>
            <p>მოთხოვნილი მატჩი არ არსებობს, ან უკვე აღარ არის დღევანდელ სიაში.</p>
            <a href="/sport_blog/#matches" class="match-button">დღის მატჩები</a>
        </section>
    <?php else: ?>
        <nav class="match-breadcrumb" aria-label="ნავიგაცია">
            <a href="/sport_blog/">მთავარი</a><span>/</span><span><?= matchEsc($match['tournament_name']) ?></span>
        </nav>

        <section class="match-hero match-hero--<?= $statusKind ?>">
            <h1 class="sr-only"><?= matchEsc($match['home_team'] . ' — ' . $match['away_team']) ?></h1>
            <div class="match-hero__meta">
                <span class="match-hero__tournament"><i class="fas fa-trophy" aria-hidden="true"></i> <?= matchEsc($match['tournament_name'] ?? 'მატჩი') ?></span>
                <span class="match-hero__status match-hero__status--<?= $statusKind ?>"><?= matchEsc($statusLabel) ?></span>
            </div>

            <div class="match-hero__board">
                <a class="match-hero__team" href="<?= matchEsc(match_club_url($match['home_team'])) ?>">
                    <span class="match-hero__logo">
                        <?php if (!empty($match['home_logo'])): ?><img src="<?= matchEsc(cached_remote_image_url($match['home_logo'])) ?>" alt="" width="96" height="96"><?php endif; ?>
                    </span>
                    <strong><?= matchEsc($match['home_team']) ?></strong>
                    <small>მასპინძელი</small>
                </a>

                <div class="match-hero__score">
                    <?php if ($statusKind === 'live' || $statusKind === 'finished'): ?>
                        <span><?= is_numeric($match['home_goals']) ? (int) $match['home_goals'] : 0 ?></span>
                        <i>:</i>
                        <span><?= is_numeric($match['away_goals']) ? (int) $match['away_goals'] : 0 ?></span>
                    <?php else: ?>
                        <span class="match-hero__time"><?= matchEsc($match['match_time'] ?: 'TBD') ?></span>
                    <?php endif; ?>
                </div>

                <a class="match-hero__team" href="<?= matchEsc(match_club_url($match['away_team'])) ?>">
                    <span class="match-hero__logo">
                        <?php if (!empty($match['away_logo'])): ?><img src="<?= matchEsc(cached_remote_image_url($match['away_logo'])) ?>" alt="" width="96" height="96"><?php endif; ?>
                    </span>
                    <strong><?= matchEsc($match['away_team']) ?></strong>
                    <small>სტუმარი</small>
                </a>
            </div>
        </section>

        <div class="match-layout">
            <section class="match-panel match-panel--stream">
                <h2><i class="fas fa-tv" aria-hidden="true"></i> ტრანსლაცია</h2>
                <?php if (!empty($iframeLive)): ?>
                    <div class="match-live-frame">
                        <iframe src="<?= matchEsc($iframeLive) ?>" title="<?= matchEsc($pageTitle) ?> — ტრანსლაცია" allowfullscreen referrerpolicy="no-referrer"></iframe>
                    </div>
                <?php else: ?>
                    <div class="match-live-empty">
                        <i class="fas fa-satellite-dish" aria-hidden="true"></i>
                        <p>ამ მატჩისთვის ტრანსლაცია ჯერ არ არის დამატებული.</p>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="match-panel">
                <h2><?= matchEsc($match['tournament_name']) ?></h2>
                <?php if (!$tournamentMatches): ?>
                    <p class="match-muted">ამ ტურნირში დღეს სხვა მატჩი არ არის.</p>
                <?php else: ?>
                    <ul class="match-list">
                        <?php foreach ($tournamentMatches as $other): [$kind, $label] = match_status_label($other); ?>
                            <li>
                                <a href="/sport_blog/matches/<?= matchEsc(rawurlencode($other['slug'])) ?>">
                                    <span class="match-list__status match-list__status--<?= $kind ?>"><?= matchEsc($label) ?></span>
                                    <span class="match-list__teams">
                                        <span><?= matchEsc($other['home_team']) ?></span>
                                        <span><?= matchEsc($other['away_team']) ?></span>
                                    </span>
                                    <?php if ($kind === 'live' || $kind === 'finished'): ?>
                                        <span class="match-list__score"><b><?= (int) $other['home_goals'] ?></b><b><?= (int) $other['away_goals'] ?></b></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($teamArticles): ?>
            <section class="match-panel match-articles">
                <h2>სიახლეები გუნდებზე</h2>
                <div class="match-articles__grid">
                    <?php foreach ($teamArticles as $article): ?>
                        <a href="/sport_blog/news/<?= matchEsc(rawurlencode($article['slug'])) ?>" class="match-article">
                            <img src="<?= matchEsc(article_image_url($article['image'])) ?>" alt="" loading="lazy" width="320" height="180">
                            <strong><?= matchEsc($article['title']) ?></strong>
                            <span><?= date('d.m.Y', strtotime($article['created_at'])) ?></span>
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
