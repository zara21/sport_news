<?php
require_once __DIR__ . '/../includes/media_helpers.php';
$status = strtolower(trim((string) $match['match_status']));
$isLive = $status !== '' && !in_array($status, ['finished', 'after pen.'], true);
$isFinished = in_array($status, ['finished', 'after pen.'], true);
$displayStatus = $isFinished ? 'დასრულდა' : ($isLive ? $match['match_status'] : ($match['match_time'] ?: 'TBD'));
?>
<a href="/sport_blog/matches/<?= htmlspecialchars($match['slug'], ENT_QUOTES, 'UTF-8') ?>" class="home-match-card sb-card">
    <span class="home-match-card__league"><?= htmlspecialchars($match['tournament_name'], ENT_QUOTES, 'UTF-8') ?></span>
    <span class="home-match-card__team"><img src="<?= htmlspecialchars(cached_remote_image_url($match['home_logo']), ENT_QUOTES, 'UTF-8') ?>" alt="" width="28" height="28" loading="lazy" decoding="async"><strong><?= htmlspecialchars($match['home_team'], ENT_QUOTES, 'UTF-8') ?></strong><b><?= is_numeric($match['home_goals']) ? (int) $match['home_goals'] : '' ?></b></span>
    <span class="home-match-card__team"><img src="<?= htmlspecialchars(cached_remote_image_url($match['away_logo']), ENT_QUOTES, 'UTF-8') ?>" alt="" width="28" height="28" loading="lazy" decoding="async"><strong><?= htmlspecialchars($match['away_team'], ENT_QUOTES, 'UTF-8') ?></strong><b><?= is_numeric($match['away_goals']) ? (int) $match['away_goals'] : '' ?></b></span>
    <span class="home-match-card__status <?= $isLive ? 'is-live' : '' ?>"><?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?></span>
</a>
