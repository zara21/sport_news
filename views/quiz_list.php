<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';
require_once __DIR__ . '/../includes/cache_helpers.php';

$homeInteractive = cache_remember('home.interactive.v3', 180, function () use ($pdo): array {
    $classic = $pdo->query("SELECT title, slug, quiz_image FROM quizzes ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    $squad = $pdo->query("SELECT title, slug, image FROM quiz_games ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;

    $eventStmt = $pdo->prepare("SELECT id, sport_type, event_title, event_date, event_time, location, live_link, event_image, status
        FROM sports_events
        WHERE sport_type = ?
        ORDER BY
            CASE WHEN event_date >= CURDATE() THEN 0 ELSE 1 END ASC,
            CASE WHEN event_date >= CURDATE() THEN event_date END ASC,
            CASE WHEN event_date < CURDATE() THEN event_date END DESC,
            event_time ASC
        LIMIT 1");

    $events = [];
    foreach (['UFC', 'F1'] as $sportType) {
        $eventStmt->execute([$sportType]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);
        if (!$event) {
            $event = [
                'id' => 0,
                'sport_type' => $sportType,
                'event_title' => $sportType . ' ივენთი მალე დაემატება',
                'event_date' => date('Y-m-d'),
                'event_time' => '00:00:00',
                'location' => 'ლოკაცია დაზუსტდება',
                'live_link' => '',
                'event_image' => '',
                'status' => 'მალე',
            ];
        }
        $events[] = $event;
    }

    return ['classic' => $classic, 'squad' => $squad, 'events' => $events];
});

$quizCards = [];
if ($homeInteractive['classic']) {
    $quizCards[] = [
        'title' => $homeInteractive['classic']['title'],
        'slug' => $homeInteractive['classic']['slug'],
        'image' => article_image_url($homeInteractive['classic']['quiz_image'] ?? null, 'uploads/quizzes'),
        'label' => 'კლასიკური ქვიზი',
    ];
}
if ($homeInteractive['squad']) {
    $quizCards[] = [
        'title' => $homeInteractive['squad']['title'],
        'slug' => $homeInteractive['squad']['slug'],
        'image' => article_image_url($homeInteractive['squad']['image'] ?? null),
        'label' => 'შემადგენლობის ქვიზი',
    ];
}
?>

<section class="home-interactive" aria-label="ივენთები და ქვიზები">
    <div class="home-interactive__grid">
        <section class="home-interactive__block home-interactive__block--events" aria-labelledby="eventsTitle">
            <div class="home-section-heading home-section-heading--compact">
                <h2 id="eventsTitle">ივენთები</h2>
            </div>
            <div class="home-event-grid">
            <?php foreach ($homeInteractive['events'] as $event):
                $eventImage = article_image_url($event['event_image'] ?? null);
                $eventDate = date('d.m.Y', strtotime($event['event_date']));
                $eventUrl = '/sport_blog/events/' . (int) ($event['id'] ?? 0);
            ?>
                <a href="<?= $eventUrl ?>" class="home-event-card sb-card">
                    <img src="<?= htmlspecialchars($eventImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?>" width="800" height="450" loading="lazy" decoding="async">
                    <div class="home-event-card__body">
                        <div class="home-event-card__meta">
                            <span><?= htmlspecialchars($event['sport_type'], ENT_QUOTES, 'UTF-8') ?></span>
                            <time datetime="<?= htmlspecialchars($event['event_date'], ENT_QUOTES, 'UTF-8') ?>"><?= $eventDate ?></time>
                        </div>
                        <h3><?= htmlspecialchars($event['event_title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($event['location'] ?: 'ლოკაცია დაზუსტდება', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(substr((string) $event['event_time'], 0, 5), ENT_QUOTES, 'UTF-8') ?></p>
                        <span class="home-event-card__action">ივენთის ნახვა &#8594;</span>
                    </div>
                </a>
            <?php endforeach; ?>
            </div>
        </section>

        <section class="home-interactive__block home-interactive__block--quizzes" aria-labelledby="quizzesTitle">
            <div class="home-section-heading home-section-heading--compact">
                <h2 id="quizzesTitle">ქვიზები</h2>
                <a href="/sport_blog/views/all_quizzes" class="home-section-link">ყველა ქვიზი</a>
            </div>
            <div class="home-quiz-grid">
            <?php foreach ($quizCards as $quiz): ?>
                <a class="home-quiz-card sb-card" href="/sport_blog/quizzes/<?= htmlspecialchars($quiz['slug'], ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= htmlspecialchars($quiz['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?>" width="480" height="270" loading="lazy" decoding="async">
                    <span class="home-quiz-card__body">
                        <small><?= htmlspecialchars($quiz['label'], ENT_QUOTES, 'UTF-8') ?></small>
                        <strong><?= htmlspecialchars($quiz['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>დაწყება &#8594;</span>
                    </span>
                </a>
            <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
