<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/media_helpers.php';

require_admin_user();

$mediaDirs = [
    'uploads' => __DIR__ . '/../uploads',
    'uploads/second_images' => __DIR__ . '/../uploads/second_images',
    'uploads/story_images' => __DIR__ . '/../uploads/story_images',
    'uploads/quizzes' => __DIR__ . '/../uploads/quizzes',
    'uploads/ufc_f1' => __DIR__ . '/../uploads/ufc_f1',
];

function normalize_media_name(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^https?:\/\//i', $value)) {
        return '';
    }

    return basename($value);
}

function collect_used_media(PDO $pdo): array
{
    $used = [];

    foreach ($pdo->query('SELECT id, title, image, second_image, story_image FROM articles') as $row) {
        foreach (['image' => 'uploads', 'second_image' => 'uploads/second_images', 'story_image' => 'uploads/story_images'] as $field => $dir) {
            $name = normalize_media_name($row[$field] ?? '');
            if ($name !== '') {
                $used[$dir . '/' . $name][] = 'სტატია #' . $row['id'] . ': ' . $row['title'];
            }
        }
    }

    foreach ($pdo->query('SELECT id, title, quiz_image FROM quizzes') as $row) {
        $name = normalize_media_name($row['quiz_image'] ?? '');
        if ($name !== '') {
            $used['uploads/quizzes/' . $name][] = 'ქვიზი #' . $row['id'] . ': ' . $row['title'];
            $used['uploads/' . $name][] = 'ქვიზი #' . $row['id'] . ': ' . $row['title'];
        }
    }

    foreach ($pdo->query('SELECT id, event_title, event_image FROM sports_events') as $row) {
        $name = normalize_media_name($row['event_image'] ?? '');
        if ($name !== '') {
            $used['uploads/ufc_f1/' . $name][] = 'ივენთი #' . $row['id'] . ': ' . $row['event_title'];
        }
    }

    return $used;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $relative = trim((string)($_POST['file'] ?? ''));
    $used = collect_used_media($pdo);
    $absolute = realpath(__DIR__ . '/../' . $relative);
    $base = realpath(__DIR__ . '/../uploads');

    if ($absolute && $base && strpos($absolute, $base) === 0 && is_file($absolute) && empty($used[$relative])) {
        unlink($absolute);
        $_SESSION['success_message'] = 'ფაილი წაიშალა.';
    } else {
        $_SESSION['error_message'] = 'ფაილი ვერ წაიშალა, რადგან გამოიყენება ან არასწორი ბილიკია.';
    }

    header('Location: media.php');
    exit;
}

$used = collect_used_media($pdo);
$items = [];
foreach ($mediaDirs as $relativeDir => $absoluteDir) {
    if (!is_dir($absoluteDir)) {
        continue;
    }

    foreach (new DirectoryIterator($absoluteDir) as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif', 'mp4', 'webm'], true)) {
            continue;
        }

        $relative = $relativeDir . '/' . $file->getFilename();
        $items[] = [
            'relative' => $relative,
            'url' => '/sport_blog/' . $relative,
            'name' => $file->getFilename(),
            'dir' => $relativeDir,
            'size' => $file->getSize(),
            'mtime' => $file->getMTime(),
            'used_by' => $used[$relative] ?? [],
            'is_video' => in_array($extension, ['mp4', 'webm'], true),
        ];
    }
}

usort($items, static fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$filter = $_GET['filter'] ?? 'all';
if ($filter === 'unused') {
    $items = array_values(array_filter($items, static fn($item) => empty($item['used_by'])));
} elseif ($filter === 'used') {
    $items = array_values(array_filter($items, static fn($item) => !empty($item['used_by'])));
}

admin_page_start('მედია ბიბლიოთეკა');
?>

<section class="admin-dashboard">
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="admin-alert admin-alert--success"><?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <section class="admin-card">
        <div class="admin-card__header">
            <div>
                <h2>მედია ფაილები</h2>
                <p class="admin-muted">ნახე რომელი ფოტო გამოიყენება და უსაფრთხოდ წაშალე გამოუყენებელი ფაილები.</p>
            </div>
            <div class="admin-actions">
                <a class="admin-button admin-button--neutral" href="media.php">ყველა</a>
                <a class="admin-button admin-button--neutral" href="media.php?filter=used">გამოყენებული</a>
                <a class="admin-button admin-button--neutral" href="media.php?filter=unused">გამოუყენებელი</a>
            </div>
        </div>

        <div class="admin-media-grid">
            <?php foreach ($items as $item): ?>
                <article class="admin-media-card">
                    <?php if ($item['is_video']): ?>
                        <video src="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" controls muted></video>
                    <?php else: ?>
                        <img src="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                    <?php endif; ?>
                    <div class="admin-media-card__body">
                        <strong><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <span><?= htmlspecialchars($item['dir'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format($item['size'] / 1024, 1) ?> KB</span>
                        <?php if (!empty($item['used_by'])): ?>
                            <span class="admin-badge">გამოიყენება</span>
                            <small><?= htmlspecialchars(implode(' | ', array_slice($item['used_by'], 0, 2)), ENT_QUOTES, 'UTF-8') ?></small>
                        <?php else: ?>
                            <span class="admin-badge admin-badge--warning">გამოუყენებელი</span>
                            <form class="admin-inline-form" method="post" onsubmit="return confirm('წავშალო ეს გამოუყენებელი ფაილი?');">
                                <?php admin_csrf_field(); ?>
                                <input type="hidden" name="file" value="<?= htmlspecialchars($item['relative'], ENT_QUOTES, 'UTF-8') ?>">
                                <button class="admin-button admin-button--danger" type="submit">წაშლა</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<?php admin_page_end(); ?>
