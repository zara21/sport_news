<?php

$base = rtrim($argv[1] ?? 'http://localhost/sport_blog', '/');

function smoke_request(string $url): array
{
    $context = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 8, 'follow_location' => 0]]);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
            $status = (int) $match[1];
            break;
        }
    }
    return ['status' => $status, 'body' => $body === false ? '' : $body, 'headers' => $headers];
}

function smoke_result(bool $ok, string $label, string $detail = ''): bool
{
    echo ($ok ? 'PASS' : 'FAIL') . " {$label}" . ($detail !== '' ? " {$detail}" : '') . "\n";
    return $ok;
}

function smoke_status(string $label, string $url, array $allowed): bool
{
    $response = smoke_request($url);
    return smoke_result(in_array($response['status'], $allowed, true), $label, (string) $response['status']);
}

$checks = [
    ['home', "{$base}/", [200]],
    ['football category', "{$base}/category/football", [200]],
    ['basketball category', "{$base}/category/basketball", [200]],
    ['tennis category', "{$base}/category/tennis", [200]],
    ['articles page 1', "{$base}/views/articles.php?page=1", [200]],
    ['articles page 2', "{$base}/views/articles.php?page=2", [200]],
    ['missing article', "{$base}/news/smoke-missing-article-420fe9", [404]],
    ['missing legacy article', "{$base}/views/article.php?id=0", [404]],
    ['quizzes', "{$base}/views/all_quizzes", [200]],
    ['login', "{$base}/auth_system/login", [200]],
    ['register', "{$base}/auth_system/register", [200]],
    ['profile redirect', "{$base}/views/profile", [302]],
    ['profile settings redirect', "{$base}/views/profile_edit", [302]],
    ['library redirect', "{$base}/views/library", [302]],
    ['notifications redirect', "{$base}/views/notifications", [302]],
    ['admin protected', "{$base}/admin/admin.php", [302]],
    ['admin quiz editor protected', "{$base}/admin/edit_quiz_game.php?id=1", [302]],
    ['admin events protected', "{$base}/admin/manage_UFC_F1_event.php", [302]],
    ['admin import protected', "{$base}/admin/import/admin_panel.php", [302]],
    ['import CSV private', "{$base}/admin/import/premier_league_table.csv", [403]],
    ['import Python private', "{$base}/admin/import/update_db.py", [403]],
    ['logout requires POST', "{$base}/auth_system/logout", [405]],
    ['token registration requires POST', "{$base}/api/save_token.php", [405]],
    ['admin aliases protected', "{$base}/admin/tag_aliases.php", [302]],
    ['admin analytics protected', "{$base}/admin/analytics.php", [302]],
    ['admin stories protected', "{$base}/admin/stories.php", [302]],
    ['admin media protected', "{$base}/admin/media.php", [302]],
    ['standings json', "{$base}/views/standings.php?league=premier_league", [200]],
    ['remaining matches json', "{$base}/views/matches_more.php?offset=12&limit=12", [200]],
    ['local font', "{$base}/assets/fonts/NotoSansGeorgian-Variable.ttf", [200]],
    ['local icons', "{$base}/assets/vendor/fontawesome/css/all.min.css", [200]],
    ['home css', "{$base}/css/home.css", [200]],
    ['event css', "{$base}/css/event.css", [200]],
    ['home javascript', "{$base}/js/home.js", [200]],
    ['sitemap', "{$base}/sitemap.xml", [200]],
    ['robots', "{$base}/robots.txt", [200]],
];

$ok = true;
foreach ($checks as [$label, $url, $allowed]) {
    $ok = smoke_status($label, $url, $allowed) && $ok;
}

require __DIR__ . '/../includes/db_connect.php';
$dynamicRoutes = [
    'article route' => '/news/' . $pdo->query("SELECT slug FROM articles WHERE status = 'published' AND slug NOT LIKE 'default-story-%' ORDER BY id DESC LIMIT 1")->fetchColumn(),
    'classic quiz route' => '/quizzes/' . $pdo->query('SELECT slug FROM quizzes ORDER BY id DESC LIMIT 1')->fetchColumn(),
    'game quiz route' => '/quizzes/' . $pdo->query('SELECT slug FROM quiz_games ORDER BY id DESC LIMIT 1')->fetchColumn(),
    'match route' => '/matches/' . $pdo->query("SELECT slug FROM matches WHERE slug IS NOT NULL AND slug != '' ORDER BY id DESC LIMIT 1")->fetchColumn(),
    'event route' => '/events/' . $pdo->query('SELECT id FROM sports_events ORDER BY id DESC LIMIT 1')->fetchColumn(),
    'club route' => '/clubs/Arsenal',
];
foreach ($dynamicRoutes as $label => $route) {
    if (!str_ends_with($route, '/')) {
        $ok = smoke_status($label, $base . $route, [200]) && $ok;
    }
}

$home = smoke_request("{$base}/");
$searchTerm = $pdo->query("SELECT title FROM articles WHERE status = 'published' AND slug <> ''
    AND slug NOT LIKE 'default-story-%' AND CHAR_LENGTH(title) BETWEEN 1 AND 120 ORDER BY id LIMIT 1")->fetchColumn();
if ($searchTerm !== false) {
    $search = smoke_request("{$base}/views/articles?q=" . rawurlencode($searchTerm));
    $ok = smoke_result($search['status'] === 200
        && str_contains($search['body'], '<meta name="robots" content="noindex,follow">')
        && str_contains($search['body'], 'sport-articles-article-card')
        && str_contains($search['body'], htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8')),
        'public article search') && $ok;
}
$homeScript = smoke_request("{$base}/js/home.js");
$imageCount = preg_match_all('/<img\b/i', $home['body']);
$lazyCount = preg_match_all('/loading=["\']lazy["\']/i', $home['body']);
$dimensionCount = preg_match_all('/<img\b(?=[^>]*\bwidth=["\'][0-9]+["\'])(?=[^>]*\bheight=["\'][0-9]+["\'])/i', $home['body']);
$ok = smoke_result(strlen($home['body']) < 100000, 'home html budget', strlen($home['body']) . ' bytes') && $ok;
$ok = smoke_result($imageCount <= 90, 'home image budget', (string) $imageCount) && $ok;
$ok = smoke_result($imageCount === 0 || ($lazyCount / $imageCount) >= 0.75, 'home lazy image ratio', "{$lazyCount}/{$imageCount}") && $ok;
$ok = smoke_result($imageCount === 0 || ($dimensionCount / $imageCount) >= 0.95, 'home image dimension ratio', "{$dimensionCount}/{$imageCount}") && $ok;
$ok = smoke_result(!str_contains($home['body'], '<style'), 'home has no inline style blocks') && $ok;
$ok = smoke_result(str_contains($home['body'], 'home-hero__grid') && str_contains($home['body'], 'data-scroll-target="matchGrid"'), 'home components rendered') && $ok;
$ok = smoke_result(str_contains($home['body'], 'role="search"'), 'public header search form') && $ok;
$ok = smoke_result(str_contains($homeScript['body'], 'enableDragScroll') && str_contains($homeScript['body'], 'suppressClick'), 'horizontal drag scroll enabled') && $ok;
$ok = smoke_result(!str_contains($home['body'], 'default-story-'), 'story-only articles excluded from home feeds') && $ok;
$ok = smoke_result(substr_count($home['body'], 'class="home-match-card sb-card"') <= 12, 'home initial match limit') && $ok;
$ok = smoke_result(str_contains($home['body'], 'id="loadMoreMatches"'), 'remaining matches control rendered') && $ok;

$securityHeaders = implode("\n", $home['headers']);
$ok = smoke_result(stripos($securityHeaders, 'X-Content-Type-Options: nosniff') !== false, 'nosniff header') && $ok;
$ok = smoke_result(stripos($securityHeaders, 'Content-Security-Policy:') !== false, 'content security policy header') && $ok;

$login = smoke_request("{$base}/auth_system/login");
$register = smoke_request("{$base}/auth_system/register");
$ok = smoke_result(str_contains($login['body'], 'name="csrf_token"'), 'login csrf field') && $ok;
$ok = smoke_result(str_contains($register['body'], 'name="csrf_token"'), 'register csrf field') && $ok;

$moreMatches = json_decode(smoke_request("{$base}/views/matches_more.php?offset=12&limit=12")['body'], true);
$ok = smoke_result(is_array($moreMatches) && isset($moreMatches['html'], $moreMatches['total']), 'remaining matches payload') && $ok;

$quizPage = smoke_request("{$base}/views/all_quizzes?page=1");
$ok = smoke_result(substr_count($quizPage['body'], 'class="quiz-card"') <= 12, 'quiz page size') && $ok;

$eventId = (int) $pdo->query('SELECT id FROM sports_events ORDER BY id DESC LIMIT 1')->fetchColumn();
if ($eventId > 0) {
    $eventPage = smoke_request("{$base}/events/{$eventId}");
    $ok = smoke_result(str_contains($home['body'], 'href="/sport_blog/events/' . $eventId . '"'), 'home event card is linked') && $ok;
    $ok = smoke_result(str_contains($eventPage['body'], '<iframe') && str_contains($eventPage['body'], 'event-live__frame'), 'event iframe rendered') && $ok;
}

$standings = smoke_request("{$base}/views/standings.php?league=premier_league");
$standingsData = json_decode($standings['body'], true);
$ok = smoke_result(is_array($standingsData), 'standings returns valid json') && $ok;
$firstStanding = is_array($standingsData) && !empty($standingsData[0]) ? $standingsData[0] : [];
$ok = smoke_result(
    (int) ($firstStanding['position'] ?? 0) === 1
        && trim((string) ($firstStanding['team'] ?? '')) !== ''
        && !is_numeric($firstStanding['team'] ?? null),
    'standings columns aligned'
) && $ok;
$ok = smoke_result(
    !empty($firstStanding['articles_url']) && str_contains((string) $firstStanding['articles_url'], '/clubs/'),
    'standings clubs are linked'
) && $ok;

$sitemap = smoke_request("{$base}/sitemap.xml");
$matchSlug = (string) $pdo->query("SELECT slug FROM matches WHERE slug IS NOT NULL AND slug != '' ORDER BY id DESC LIMIT 1")->fetchColumn();
$quizGameSlug = (string) $pdo->query("SELECT slug FROM quiz_games WHERE slug IS NOT NULL AND slug != '' ORDER BY id DESC LIMIT 1")->fetchColumn();
$ok = smoke_result($matchSlug === '' || str_contains($sitemap['body'], '/matches/' . $matchSlug), 'sitemap includes matches') && $ok;
$ok = smoke_result($quizGameSlug === '' || str_contains($sitemap['body'], '/quizzes/' . $quizGameSlug), 'sitemap includes game quizzes') && $ok;

exit($ok ? 0 : 1);
