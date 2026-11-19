<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/club_helpers.php';
require_once __DIR__ . '/../includes/seo_helpers.php';

$raw = urldecode((string) ($_GET['club'] ?? ''));
$club = canonical_club_name($raw) ?: trim($raw);
if ($club === '') {
    require __DIR__ . '/../404.php';
    exit;
}

$aliases = database_club_aliases($pdo, $club);
$ph = implode(',', array_fill(0, count($aliases), '?'));

$logoStmt = $pdo->prepare("SELECT logo_url FROM club_logos WHERE club_name IN ($ph) ORDER BY club_name = ? DESC LIMIT 1");
$logoStmt->execute([...$aliases, $club]);
$logoRaw = $logoStmt->fetchColumn();
$logo = $logoRaw ? cached_remote_image_url(article_image_url($logoRaw)) : '';

$articleStmt = $pdo->prepare("SELECT DISTINCT a.title, a.slug, a.image, a.summary, a.category, a.created_at
    FROM articles a JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id
    WHERE t.name IN ($ph) AND a.status = 'published' AND a.slug NOT LIKE 'default-story-%'
    ORDER BY a.created_at DESC LIMIT 12");
$articleStmt->execute($aliases);
$articles = $articleStmt->fetchAll(PDO::FETCH_ASSOC);

$standingStmt = $pdo->prepare("SELECT league_name, position, team, played, wins, draws, losses, score, goal_diff, points
    FROM league_standings WHERE team IN ($ph) LIMIT 1");
$standingStmt->execute($aliases);
$standing = $standingStmt->fetch(PDO::FETCH_ASSOC);

$neighbours = [];
if ($standing) {
    $nStmt = $pdo->prepare('SELECT position, team, played, goal_diff, points FROM league_standings
        WHERE league_name = ? AND position BETWEEN ? AND ? ORDER BY position');
    $nStmt->execute([$standing['league_name'], max(1, $standing['position'] - 2), $standing['position'] + 2]);
    $neighbours = $nStmt->fetchAll(PDO::FETCH_ASSOC);
}

$matchStmt = $pdo->prepare("SELECT home_team, away_team, home_logo, away_logo, home_goals, away_goals, match_time, match_status, slug, tournament_name
    FROM matches WHERE home_team IN ($ph) OR away_team IN ($ph) ORDER BY match_time ASC LIMIT 10");
$matchStmt->execute([...$aliases, ...$aliases]);
$matches = $matchStmt->fetchAll(PDO::FETCH_ASSOC);

// უცნობი კლუბი (არც ლოგო, არც ცხრილი, არც სტატია, არც მატჩი) — ნამდვილი 404
if (canonical_club_name($raw) === null && !$logoRaw && !$standing && !$articles && !$matches) {
    require __DIR__ . '/../404.php';
    exit;
}

$leagueNames = [
    'premier_league' => 'პრემიერ ლიგა', 'la_liga' => 'ლა ლიგა', 'serie_a' => 'სერია A',
    'bundesliga' => 'ბუნდესლიგა', 'ligue_1' => 'ლიგა 1',
];
$h = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$isAliasOf = static function (string $team) use ($aliases): bool {
    foreach ($aliases as $alias) {
        if (mb_strtolower($alias, 'UTF-8') === mb_strtolower(trim($team), 'UTF-8')) return true;
    }
    return false;
};
$description = $club . ' — სიახლეები, მატჩები და ადგილი ცხრილში.';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= $h($club) ?> | Sport Blog</title>
    <meta name="description" content="<?= $h($description) ?>">
    <link rel="canonical" href="<?= $h(absolute_site_url('/sport_blog/clubs/' . rawurlencode($club))) ?>">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/club.css?v=20261119i">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>
<main class="club-page">
    <header class="club-hero">
        <div class="club-hero__logo">
            <?php if ($logo !== ''): ?>
                <img src="<?= $h($logo) ?>" alt="" width="140" height="140">
            <?php else: ?>
                <span aria-hidden="true"><?= $h(mb_substr($club, 0, 1, 'UTF-8')) ?></span>
            <?php endif; ?>
        </div>
        <div class="club-hero__info">
            <p class="club-hero__kicker">კლუბი<?= $standing ? ' · ' . $h($leagueNames[$standing['league_name']] ?? $standing['league_name']) : '' ?></p>
            <h1><?= $h($club) ?></h1>
            <?php if ($standing): ?>
                <div class="club-stats">
                    <div><strong><?= (int) $standing['position'] ?></strong><span>ადგილი</span></div>
                    <div><strong><?= (int) $standing['points'] ?></strong><span>ქულა</span></div>
                    <div><strong><?= (int) $standing['played'] ?></strong><span>მატჩი</span></div>
                    <div><strong><?= (int) $standing['wins'] ?>-<?= (int) $standing['draws'] ?>-<?= (int) $standing['losses'] ?></strong><span>მ-ფ-წ</span></div>
                    <div><strong><?= $h($standing['score']) ?></strong><span>გოლები</span></div>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="club-layout">
        <section class="club-section club-section--articles">
            <h2>სიახლეები</h2>
            <?php if (!$articles): ?>
                <p class="club-empty">ამ კლუბზე სტატიები ჯერ არ არის.</p>
            <?php else: ?>
                <div class="club-articles">
                    <?php foreach ($articles as $index => $article): ?>
                        <a class="club-article<?= $index === 0 ? ' club-article--featured' : '' ?>" href="/sport_blog/news/<?= $h(rawurlencode($article['slug'])) ?>">
                            <img src="<?= $h(article_image_url($article['image'])) ?>" alt="" loading="<?= $index < 3 ? 'eager' : 'lazy' ?>" width="640" height="360">
                            <div>
                                <span><?= $h($article['category']) ?> · <?= date('d.m.Y', strtotime($article['created_at'])) ?></span>
                                <strong><?= $h($article['title']) ?></strong>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <aside class="club-aside">
            <?php if ($neighbours): ?>
                <section class="club-section">
                    <h2><?= $h($leagueNames[$standing['league_name']] ?? $standing['league_name']) ?></h2>
                    <table class="club-table">
                        <thead><tr><th>#</th><th>გუნდი</th><th>მ</th><th>სხ</th><th>ქ</th></tr></thead>
                        <tbody>
                            <?php foreach ($neighbours as $row): ?>
                                <tr class="<?= $isAliasOf($row['team']) ? 'is-current' : '' ?>">
                                    <td><?= (int) $row['position'] ?></td>
                                    <td><a href="/sport_blog/clubs/<?= $h(rawurlencode(canonical_club_name($row['team']) ?: $row['team'])) ?>"><?= $h($row['team']) ?></a></td>
                                    <td><?= (int) $row['played'] ?></td>
                                    <td><?= (int) $row['goal_diff'] > 0 ? '+' : '' ?><?= (int) $row['goal_diff'] ?></td>
                                    <td><strong><?= (int) $row['points'] ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a class="club-link" href="/sport_blog/#standings">სრული ცხრილი →</a>
                </section>
            <?php endif; ?>

            <section class="club-section">
                <h2>დღის მატჩები</h2>
                <?php if (!$matches): ?>
                    <p class="club-empty">დღეს მატჩი არ აქვს.</p>
                <?php else: ?>
                    <ul class="club-matches">
                        <?php foreach ($matches as $m):
                            $status = strtolower(trim((string) $m['match_status']));
                            $finished = in_array($status, ['finished', 'after pen.'], true);
                            $live = $status !== '' && !$finished;
                        ?>
                            <li>
                                <a href="/sport_blog/matches/<?= $h(rawurlencode($m['slug'])) ?>">
                                    <span class="club-matches__status<?= $live ? ' is-live' : '' ?>"><?= $finished ? 'დასრ.' : ($live ? $h($m['match_status']) : $h($m['match_time'] ?: 'TBD')) ?></span>
                                    <span class="club-matches__teams">
                                        <span><?= $h($m['home_team']) ?></span>
                                        <span><?= $h($m['away_team']) ?></span>
                                    </span>
                                    <?php if ($finished || $live): ?>
                                        <span class="club-matches__score"><b><?= (int) $m['home_goals'] ?></b><b><?= (int) $m['away_goals'] ?></b></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
