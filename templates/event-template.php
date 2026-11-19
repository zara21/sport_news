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
$eventStatusLabels = ['UPCOMING' => 'მალე', 'LIVE SOON' => 'იწყება მალე', 'LIVE NOW' => 'ახლა პირდაპირ'];
$otherEvents = [];
$eventStartIso = '';
if ($event) {
    $otherStmt = $pdo->prepare('SELECT id, sport_type, event_title, event_date, event_time, event_image, status FROM sports_events
        WHERE id <> ? AND event_date >= CURDATE() ORDER BY event_date ASC, event_time ASC LIMIT 4');
    $otherStmt->execute([(int) $event['id']]);
    $otherEvents = $otherStmt->fetchAll(PDO::FETCH_ASSOC);
    $startTs = strtotime($event['event_date'] . ' ' . ($event['event_time'] ?: '00:00:00'));
    $eventStartIso = $startTs ? date('c', $startTs) : '';
}
// ბაზაში სტატუსი ხელით იცვლება — თუ ივენთი 6 საათზე მეტია რაც დაიწყო, ვაჩვენებთ "დასრულდა"
$eventStatusKey = (string) ($event['status'] ?? '');
if ($event && !empty($startTs) && $startTs < time() - 6 * 3600) {
    $eventStatusKey = 'FINISHED';
}
$eventStatusLabels['FINISHED'] = 'დასრულდა';
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php require __DIR__ . '/../includes/head_meta.php'; ?>
    <title><?= event_h($pageTitle) ?> - Sport Blog</title>
    <meta name="description" content="<?= event_h($description) ?>">
    <link rel="canonical" href="<?= event_h($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= event_h($pageTitle) ?>">
    <meta property="og:description" content="<?= event_h($description) ?>">
    <meta property="og:image" content="<?= event_h(absolute_site_url($bannerUrl)) ?>">
    <meta property="og:url" content="<?= event_h($canonicalUrl) ?>">
    <link rel="stylesheet" href="/sport_blog/assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/sport_blog/css/styles.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/navbar.css?v=20261119i">
    <link rel="stylesheet" href="/sport_blog/css/event.css?v=20261119i">
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
                <div><span>სტატუსი</span><strong class="event-status event-status--<?= event_h(strtolower(str_replace(' ', '-', $eventStatusKey))) ?>"><?= event_h($eventStatusLabels[$eventStatusKey] ?? $eventStatusKey) ?></strong></div>
            </section>

            <?php if ($eventStartIso !== '' && strtotime($eventStartIso) > time()): ?>
                <section class="event-countdown" data-event-start="<?= event_h($eventStartIso) ?>" aria-label="დაწყებამდე">
                    <span class="event-countdown__label">დაწყებამდე</span>
                    <div class="event-countdown__grid">
                        <div><strong data-unit="days">0</strong><span>დღე</span></div>
                        <div><strong data-unit="hours">00</strong><span>საათი</span></div>
                        <div><strong data-unit="minutes">00</strong><span>წუთი</span></div>
                        <div><strong data-unit="seconds">00</strong><span>წამი</span></div>
                    </div>
                </section>
            <?php endif; ?>

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
            <?php if ($otherEvents): ?>
                <section class="event-others">
                    <h2>სხვა ივენთები</h2>
                    <div class="event-others__grid">
                        <?php foreach ($otherEvents as $other): ?>
                            <a class="event-other" href="/sport_blog/events/<?= (int) $other['id'] ?>">
                                <img src="<?= event_h(article_image_url($other['event_image'] ?? null)) ?>" alt="" loading="lazy" width="320" height="180">
                                <span class="event-other__sport"><?= event_h($other['sport_type']) ?></span>
                                <strong><?= event_h($other['event_title']) ?></strong>
                                <small><?= date('d.m.Y', strtotime($other['event_date'])) ?> · <?= event_h(substr((string) $other['event_time'], 0, 5)) ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
    <?php endif; ?>
</main>
<?php if ($event && $eventStartIso !== ''): ?>
<script>
  (() => {
    const box = document.querySelector('[data-event-start]');
    if (!box) return;
    const start = new Date(box.dataset.eventStart).getTime();
    const units = Object.fromEntries([...box.querySelectorAll('[data-unit]')].map((el) => [el.dataset.unit, el]));
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
      const diff = Math.max(0, Math.floor((start - Date.now()) / 1000));
      units.days.textContent = Math.floor(diff / 86400);
      units.hours.textContent = pad(Math.floor(diff % 86400 / 3600));
      units.minutes.textContent = pad(Math.floor(diff % 3600 / 60));
      units.seconds.textContent = pad(diff % 60);
      if (diff === 0) { clearInterval(timer); box.classList.add('is-started'); }
    };
    const timer = setInterval(tick, 1000);
    tick();
  })();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
