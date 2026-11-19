<?php

function admin_role_capabilities(string $status): array
{
    $status = strtolower(trim($status));
    if (in_array($status, ['admin', 'administrator', 'super administrator'], true)) {
        return ['*'];
    }
    if ($status === 'moderator') {
        return ['dashboard', 'articles.manage', 'stories.manage', 'media.manage', 'quizzes.manage', 'matches.manage', 'notifications.manage'];
    }
    if ($status === 'author/editor') {
        return ['dashboard', 'articles.manage', 'stories.manage', 'media.manage'];
    }
    return [];
}

function admin_required_capability(?string $script = null): string
{
    $script = $script ?: basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $map = [
        'admin.php' => 'dashboard',
        'admin_articles.php' => 'articles.manage', 'add_article.php' => 'articles.manage',
        'edit_article.php' => 'articles.manage', 'delete_article.php' => 'articles.manage', 'load_articles.php' => 'articles.manage',
        'stories.php' => 'stories.manage', 'media.php' => 'media.manage',
        'tag_aliases.php' => 'articles.manage', 'analytics.php' => 'dashboard',
        'quizzes.php' => 'quizzes.manage', 'add_quiz.php' => 'quizzes.manage', 'edit_quiz.php' => 'quizzes.manage',
        'delete_quiz.php' => 'quizzes.manage', 'quiz_games.php' => 'quizzes.manage', 'add_sec_quiz.php' => 'quizzes.manage',
        'edit_quiz_game.php' => 'quizzes.manage', 'delete_quiz_game.php' => 'quizzes.manage',
        'add_match.php' => 'matches.manage', 'edit_iframes.php' => 'matches.manage',
        'manage_UFC_F1_event.php' => 'matches.manage', 'add_UFC_F1_event.php' => 'matches.manage', 'edit_UFC_F1_event.php' => 'matches.manage',
        'send_notification.php' => 'notifications.manage',
        'user_list.php' => 'users.manage', 'edit_user.php' => 'users.manage', 'reset_password.php' => 'users.manage',
        'admin_panel.php' => 'system.import', 'import_csv.php' => 'system.import', 'import_matches.php' => 'system.import',
    ];
    return $map[$script] ?? 'dashboard';
}

function admin_can(string $capability): bool
{
    $capabilities = admin_role_capabilities((string) ($_SESSION['user']['status'] ?? ''));
    return in_array('*', $capabilities, true) || in_array($capability, $capabilities, true);
}

function require_admin_user(?string $capability = null): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['user_id']) || empty($_SESSION['user'])) {
        header('Location: /sport_blog/auth_system/login');
        exit;
    }

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$freshUser) {
            session_unset();
            session_destroy();
            header('Location: /sport_blog/auth_system/login');
            exit;
        }
        $_SESSION['user'] = array_merge($_SESSION['user'], $freshUser);
    }

    $capability = $capability ?: admin_required_capability();
    if (!admin_can($capability)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): void
{
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('Invalid CSRF token.');
    }
}

function safe_slug(string $value, string $fallback = 'item'): string
{
    // ქართული ასოების ლათინური ტრანსლიტერაცია (ეროვნული სისტემა), რომ ქართული სათაურიდანაც შეიქმნას slug
    static $georgian = [
        'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z', 'თ' => 't',
        'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o', 'პ' => 'p', 'ჟ' => 'zh',
        'რ' => 'r', 'ს' => 's', 'ტ' => 't', 'უ' => 'u', 'ფ' => 'p', 'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'q',
        'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz', 'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j',
        'ჰ' => 'h',
    ];
    $value = strtr($value, $georgian);
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower((string)$slug);
    $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
    return $slug !== '' ? $slug : $fallback;
}

function unique_slug(PDO $pdo, string $table, string $slug, ?int $ignoreId = null): string
{
    $base = $slug;
    $suffix = 2;

    while (true) {
        if ($ignoreId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $ignoreId]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
            $stmt->execute([$slug]);
        }

        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

function parse_tagify_values(?string $json): array
{
    $items = json_decode((string)$json, true);
    if (!is_array($items)) {
        return [];
    }

    $values = [];
    foreach ($items as $item) {
        if (isset($item['value'])) {
            $value = trim((string)$item['value']);
            if ($value !== '') {
                $values[] = $value;
            }
        }
    }

    return array_values(array_unique($values));
}

function upload_media_file(string $field, string $targetDir, array $allowedMimes, int $maxBytes): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('ფაილის ატვირთვა ვერ მოხერხდა.');
    }

    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('ფაილი დაშვებულ ზომაზე დიდია.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('ფაილის ტიპი არ არის დაშვებული.');
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];
    // გაფართოება მხოლოდ შემოწმებული MIME ტიპიდან (მომხმარებლის ფაილის სახელს არ ვენდობით)
    $extension = $extensions[$mime] ?? 'bin';

    // სახელი = შიგთავსის hash: ერთი და იგივე ფაილის ხელახლა ატვირთვა ახალ ასლს აღარ ქმნის
    $filename = substr(hash_file('sha256', $file['tmp_name']), 0, 32) . '.' . $extension;
    $destination = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (is_file($destination)) {
        return $filename;
    }

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('ფაილის შენახვა ვერ მოხერხდა.');
    }

    optimize_uploaded_image($destination, $mime);

    return $filename;
}

/**
 * ამოწმებს, მიუთითებს თუ არა ბაზაში რომელიმე ჩანაწერი ფაილზე.
 * ფაილები hash-ით ინახება და შეიძლება რამდენიმე ჩანაწერს ჰქონდეს საერთო,
 * ამიტომ წაშლამდე ეს შემოწმება აუცილებელია.
 */
function media_is_referenced(PDO $pdo, string $filename): bool
{
    $filename = basename($filename);
    if ($filename === '') {
        return false;
    }

    $columns = [
        ['articles', 'image'], ['articles', 'second_image'], ['articles', 'story_image'], ['articles', 'video'],
        ['users', 'profile_image'], ['quizzes', 'quiz_image'], ['questions', 'quiz_image'],
        ['quiz_games', 'image'], ['sports_events', 'event_image'],
    ];
    $like = '%' . addcslashes($filename, '%_\\');

    foreach ($columns as [$table, $column]) {
        try {
            $stmt = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? OR `{$column}` LIKE ? LIMIT 1");
            $stmt->execute([$filename, $like]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (PDOException $e) {
            // ცხრილი/სვეტი არ არსებობს — უსაფრთხოებისთვის ვთვლით, რომ გამოიყენება
            return true;
        }
    }

    return false;
}

function optimize_uploaded_image(string $path, string $mime, int $maxWidth = 1800, int $quality = 82): void
{
    if (!is_file($path) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return;
    }

    $size = getimagesize($path);
    if (!$size) {
        return;
    }

    [$width, $height] = $size;
    if ($width <= 0 || $height <= 0) {
        return;
    }

    $loader = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? 'imagecreatefromjpeg' : null,
        'image/png' => function_exists('imagecreatefrompng') ? 'imagecreatefrompng' : null,
        'image/webp' => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
        default => null,
    };
    if (!$loader) {
        return;
    }

    $source = $loader($path);
    if (!$source) {
        return;
    }

    $targetWidth = min($width, $maxWidth);
    $targetHeight = (int)round($height * ($targetWidth / $width));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);

    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($target, false);
        imagesavealpha($target, true);
    }

    imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

    if ($mime === 'image/jpeg') {
        imagejpeg($target, $path, $quality);
    } elseif ($mime === 'image/png') {
        imagepng($target, $path, 6);
    } elseif ($mime === 'image/webp') {
        imagewebp($target, $path, $quality);
    }

    imagedestroy($source);
    imagedestroy($target);
}

function delete_local_media(?string $filename, string $dir): void
{
    $filename = trim((string)$filename);
    if ($filename === '' || preg_match('/^https?:\/\//i', $filename) || strpos($filename, '/') === 0) {
        return;
    }

    $path = realpath(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename);
    $base = realpath($dir);
    if (!$path || !$base || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path)) {
        return;
    }

    // ფაილი შეიძლება სხვა ჩანაწერსაც ეკუთვნოდეს (hash-ით დედუპლიკაცია) — მაშინ არ ვშლით
    global $pdo;
    if ($pdo instanceof PDO && media_is_referenced($pdo, basename($path))) {
        return;
    }

    unlink($path);
}

function sanitize_article_html(string $html): string
{
    require_once __DIR__ . '/html_sanitizer.php';
    return sanitize_rich_html($html);
}
