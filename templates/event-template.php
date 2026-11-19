<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/seo_helpers.php';

function event_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_event_id(): int
{
    if (isset($_GET['id'])) {
        return max(0, (int) $_GET['id']);
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $last = basename(trim($path, '/'));
    return ctype_digit($last) ? (int) $last : 0;
}

function safe_event_embed_url(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : '';
}

$eventId = current_event_id();
$event = null;
if ($eventId > 0) {
    $stmt = $pdo->prepare('SELECT id, sport_type, event_title, event_date, event_time, location, live_link, event_image, status FROM sports_events WHERE id = ? LIMIT 1');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$event) {
    http_response_code(404);
}

$pageTitle = $event ? $event['event_title'] : 'ივენთი ვერ მოიძებნა';
$bannerUrl = $event ? article_image_url($event['event_image'] ?? null) : SPORT_BLOG_DEFAULT_IMAGE;
$embedUrl = $event ? safe_event_embed_url($event['live_link'] ?? '') : '';
$canonicalUrl = absolute_site_url('/sport_blog/events/' . $eventId);
$description = $event
    ? trim($event['sport_type'] . ' · ' . ($event['location'] ?: 'Sport Blog') . ' · ' . $event['event_date'])
    : 'Sport Blog-ის სპორტული ივენთი.';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= event_h($pageTitle) ?> - Sport Blog</title>
    <meta name="description" content="<?= event_h($description) ?>">
    <link rel="canonical" href="<?= event_h($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= event_h($pageTitle) ?>">
    <meta property="og:description" content="<?= event_h($description) ?>">
    <meta property="og:image" content="<?= event_h(absolute_site_url($bannerUrl)) ?>">
    <meta property="og:url" content="<?= event_h($canonicalUrl) ?>">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119f">
    <link rel="stylesheet" href="/sport_blog/css/event.css?v=20261119f">
</head>
<body>
<?php require __DIR__ . '/../includes/header.php'; ?>

<main class="event-page">
    <?php if (!$event): ?>
        <section class="event-not-found sb-card">
            <span>404</span>
            <h1>ივენთი ვერ მოიძებნა</h1>
            <p>მოთხოვნილი UFC ან Formula 1 ივენთი არ არსებობს.</p>
            <a href="/sport_blog/" class="event-button">მთავარზე დაბრუნება</a>
        </section>
    <?php else: ?>
        <article class="event-shell">
            <header class="event-hero">
                <img src="<?= event_h($bannerUrl) ?>" alt="<?= event_h($event['event_title']) ?>" fetchpriority="high">
                <div class="event-hero__overlay">
                    <span class="event-hero__sport"><?= event_h($event['sport_type']) ?></span>
                    <h1><?= event_h($event['event_title']) ?></h1>
                </div>
            </header>

            <section class="event-meta sb-card" aria-label="ივენთის ინფორმაცია">
                <div><span>თარიღი</span><strong><?= date('d.m.Y', strtotime($event['event_date'])) ?></strong></div>
                <div><span>დრო</span><strong><?= event_h(substr((string) $event['event_time'], 0, 5)) ?></strong></div>
                <div><span>ლოკაცია</span><strong><?= event_h($event['location'] ?: 'დაზუსტდება') ?></strong></div>
                <div><span>სტატუსი</span><strong><?= event_h($event['status']) ?></strong></div>
            </section>

            <section class="event-live" aria-labelledby="eventLiveTitle">
                <div class="event-live__heading">
                    <div>
                        <span><?= event_h($event['sport_type']) ?></span>
                        <h2 id="eventLiveTitle">პირდაპირი ეთერი</h2>
                    </div>
                    <?php if ($embedUrl !== ''): ?>
                        <a href="<?= event_h($embedUrl) ?>" target="_blank" rel="noopener noreferrer" class="event-button event-button--secondary">ცალკე გახსნა</a>
                    <?php endif; ?>
                </div>

                <?php if ($embedUrl !== ''): ?>
                    <div class="event-live__frame">
                        <iframe
                            src="<?= event_h($embedUrl) ?>"
                            title="<?= event_h($event['event_title']) ?> - პირდაპირი ეთერი"
                            loading="lazy"
                            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <div class="event-live__empty sb-card">ამ ივენთისთვის პირდაპირი ეთერის ბმული ჯერ არ არის დამატებული.</div>
                <?php endif; ?>
            </section>
        </article>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
