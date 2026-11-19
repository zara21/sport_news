<?php
/**
 * uploads/ საქაღალდეში პოულობს ფაილებს, რომლებსაც ბაზაში აღარაფერი მიუთითებს
 * (წარუმატებელი შენახვის ნარჩენები, ხელახლა ატვირთული ასლები, წაშლილი ჩანაწერების ფაილები).
 *
 * გამოყენება (მხოლოდ CLI):
 *   php scripts/cleanup_orphan_uploads.php            — მხოლოდ სია (არაფერი იშლება)
 *   php scripts/cleanup_orphan_uploads.php --delete   — ფაილები გადადის storage/orphan_uploads/-ში
 *
 * ფაილები პირდაპირ არ იშლება: გადაიტანება storage/orphan_uploads/<თარიღი>/-ში,
 * რომ საჭიროების შემთხვევაში აღდგენა შეიძლებოდეს. შემოწმების შემდეგ ეს საქაღალდე ხელით წაშალეთ.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

$uploadsDir = realpath(__DIR__ . '/../uploads');
$move = in_array('--delete', $argv, true);
if ($uploadsDir === false) {
    fwrite(STDERR, "uploads/ ვერ მოიძებნა.\n");
    exit(1);
}

// ბაზის ყველა სვეტი, რომელიც მედია ფაილს შეიძლება შეიცავდეს
$columns = [
    ['articles', 'image'], ['articles', 'second_image'], ['articles', 'story_image'], ['articles', 'video'],
    ['articles', 'content'], ['articles', 'summary'],
    ['club_logos', 'logo_url'], ['matches', 'home_logo'], ['matches', 'away_logo'],
    ['matches', 'live_link'], ['matches', 'iframe_live'], ['match_iframes', 'iframe_live'],
    ['questions', 'quiz_image'], ['quizzes', 'quiz_image'], ['quiz_games', 'image'],
    ['sports_events', 'event_image'], ['sports_events', 'live_link'], ['users', 'profile_image'],
    ['notifications', 'link'],
];

$referenced = '';
$scanFailed = false;
foreach ($columns as [$table, $column]) {
    try {
        foreach ($pdo->query("SELECT `{$column}` FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` <> ''")->fetchAll(PDO::FETCH_COLUMN) as $value) {
            $referenced .= "\n" . rawurldecode((string) $value);
        }
    } catch (PDOException $e) {
        $scanFailed = true;
        fwrite(STDERR, "გამოტოვდა {$table}.{$column}: {$e->getMessage()}\n");
    }
}

// კოდში პირდაპირ ჩაწერილი ფაილებიც არ უნდა წაიშალოს
$codeRoots = ['includes', 'templates', 'views', 'css', 'js', 'admin', 'category', 'api',
    'auth_system', 'clubs', 'events', 'matches', 'media', 'news', 'quizzes', 'index.php'];
foreach ($codeRoots as $entry) {
    $path = __DIR__ . '/../' . $entry;
    $files = is_dir($path)
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
        : [new SplFileInfo($path)];
    foreach ($files as $file) {
        if ($file->isFile() && preg_match('/\.(php|css|js|csv|py)$/', $file->getFilename())) {
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                $scanFailed = true;
                continue;
            }
            $referenced .= "\n" . rawurldecode($content);
        }
    }
}

$protectedDirs = ['defaults', 'banner', 'logos'];
$orphans = [];
$totalBytes = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadsDir, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink() || str_starts_with($file->getFilename(), ".")) continue;
    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($uploadsDir) + 1));
    if (in_array(explode('/', $relative)[0], $protectedDirs, true)) continue;
    if (str_contains($referenced, $file->getFilename())) continue;
    $orphans[] = $relative;
    $totalBytes += $file->getSize();
}

sort($orphans);
foreach ($orphans as $relative) {
    printf("%8.1f KB  %s\n", filesize($uploadsDir . '/' . $relative) / 1024, $relative);
}
printf("\nსულ: %d ფაილი, %.1f MB\n", count($orphans), $totalBytes / 1048576);

if ($scanFailed) {
    fwrite(STDERR, "შემოწმება ვერ დასრულდა. ფაილები არ გადატანილა.\n");
    exit(1);
}

if (!$move) {
    echo "არაფერი წაშლილა. გადასატანად გაუშვით --delete პარამეტრით.\n";
    exit;
}

if (!$orphans) {
    exit;
}

$target = __DIR__ . '/../storage/orphan_uploads/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
$moved = 0;
foreach ($orphans as $relative) {
    $destination = $target . '/' . $relative;
    $directory = dirname($destination);
    if ((!is_dir($directory) && !mkdir($directory, 0775, true))
        || !rename($uploadsDir . '/' . $relative, $destination)) {
        fwrite(STDERR, "ვერ გადაიტანა: {$relative}\n");
        continue;
    }
    $moved++;
}
printf("გადატანილია %d/%d: %s\n", $moved, count($orphans), realpath($target) ?: $target);
exit($moved === count($orphans) ? 0 : 1);
