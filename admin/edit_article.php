<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/media_helpers.php';

require_admin_user();

$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($articleId <= 0) {
    http_response_code(400);
    exit('არასწორი სტატიის ID.');
}

$stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$articleId]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$article) {
    http_response_code(404);
    exit('სტატია ვერ მოიძებნა.');
}

$clubTags = $pdo->query('SELECT club_name FROM club_logos ORDER BY club_name ASC')->fetchAll(PDO::FETCH_COLUMN);
$imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$videoMimes = ['video/mp4', 'video/webm'];

$tagStmt = $pdo->prepare('
    SELECT t.name
    FROM tags t
    INNER JOIN article_tags at ON t.id = at.tag_id
    WHERE at.article_id = ?
');
$tagStmt->execute([$articleId]);
$tagNames = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $newUploads = [];
    $replacedMedia = [];

    try {
        $title = trim((string)($_POST['title'] ?? ''));
        $content = sanitize_article_html((string)($_POST['content'] ?? ''));
        $summary = trim((string)($_POST['summary'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $status = $_POST['status'] ?? 'published';
        $scheduledAt = !empty($_POST['scheduled_at']) ? str_replace('T',' ',(string)$_POST['scheduled_at']).':00' : null;
        if ($scheduledAt && strtotime($scheduledAt) > time()) $status = 'draft';
        $type = $_POST['type'] ?? ($article['type'] ?? 'news');
        if (!in_array($status, ['published', 'draft'], true)) $status = 'draft';
        if (!in_array($type, ['news', 'opinion', 'analysis', 'live', 'video'], true)) $type = 'news';
        $popular = isset($_POST['popular']) ? 1 : 0;
        $hasVideo = isset($_POST['has_video']) ? 1 : 0;
        $showInStory = isset($_POST['show_in_story']) ? 1 : 0;
        $storyExpiresAt = $showInStory && !empty($_POST['story_expires_at']) ? str_replace('T', ' ', $_POST['story_expires_at']) . ':00' : null;

        if ($title === '' || $content === '' || $category === '') {
            throw new RuntimeException('სათაური, ტექსტი და კატეგორია სავალდებულოა.');
        }

        $tags = parse_tagify_values($_POST['tags'] ?? '[]');
        $subcategories = parse_tagify_values($_POST['subcategory'] ?? '[]');
        if ($showInStory && !array_intersect($tags, $clubTags)) {
            throw new RuntimeException('სთორისთვის თეგებში აირჩიე მინიმუმ ერთი ტოპ კლუბი.');
        }

        $slugInput = trim((string)($_POST['slug'] ?? ''));
        $slug = unique_slug($pdo, 'articles', safe_slug($slugInput !== '' ? $slugInput : $title, 'article-' . $articleId), $articleId);

        // ახალი ფაილები შეცდომისას იშლება; ძველი ფაილები იშლება მხოლოდ წარმატებული შენახვის შემდეგ.
        $uploadsDir = __DIR__ . '/../uploads';
        $image = $article['image'];
        $secondImage = $article['second_image'];
        $storyImage = $article['story_image'];
        $video = $hasVideo ? $article['video'] : null;
        if (!$hasVideo && !empty($article['video'])) {
            $replacedMedia[] = [$article['video'], $uploadsDir . '/videos'];
        }

        if ($newImage = upload_media_file('image', $uploadsDir, $imageMimes, 5 * 1024 * 1024)) {
            $newUploads[] = [$newImage, $uploadsDir];
            $replacedMedia[] = [$image, $uploadsDir];
            $image = $newImage;
        }
        if ($newSecondImage = upload_media_file('second_image', $uploadsDir . '/second_images', $imageMimes, 5 * 1024 * 1024)) {
            $newUploads[] = [$newSecondImage, $uploadsDir . '/second_images'];
            $replacedMedia[] = [$secondImage, $uploadsDir . '/second_images'];
            $secondImage = $newSecondImage;
        }
        if ($newStoryImage = upload_media_file('story_image', $uploadsDir . '/story_images', $imageMimes, 5 * 1024 * 1024)) {
            $newUploads[] = [$newStoryImage, $uploadsDir . '/story_images'];
            $replacedMedia[] = [$storyImage, $uploadsDir . '/story_images'];
            $storyImage = $newStoryImage;
        }
        if ($hasVideo && ($newVideo = upload_media_file('video', $uploadsDir . '/videos', $videoMimes, 80 * 1024 * 1024))) {
            $newUploads[] = [$newVideo, $uploadsDir . '/videos'];
            $replacedMedia[] = [$video, $uploadsDir . '/videos'];
            $video = $newVideo;
        }

        $pdo->beginTransaction();
        $update = $pdo->prepare('
            UPDATE articles
            SET title = ?, content = ?, category = ?, subcategory = ?, summary = ?, slug = ?,
                image = ?, second_image = ?, story_image = ?, show_in_story = ?, story_expires_at = ?,
                has_video = ?, video = ?, tags = ?, popular = ?, status = ?, scheduled_at = ?, type = ?, updated_at = NOW()
            WHERE id = ?
        ');
        $update->execute([
            $title,
            $content,
            $category,
            implode(',', $subcategories),
            $summary,
            $slug,
            $image,
            $secondImage,
            $storyImage,
            $showInStory,
            $storyExpiresAt,
            $hasVideo,
            $video,
            implode(',', $tags),
            $popular,
            $status,
            $scheduledAt,
            $type,
            $articleId,
        ]);

        $pdo->prepare('DELETE FROM article_tags WHERE article_id = ?')->execute([$articleId]);
        $selectTag = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $createTag = $pdo->prepare('INSERT INTO tags (name) VALUES (?)');
        $attachTag = $pdo->prepare('INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)');
        foreach ($tags as $tag) {
            $selectTag->execute([$tag]);
            $tagId = $selectTag->fetchColumn();
            if (!$tagId) {
                $createTag->execute([$tag]);
                $tagId = $pdo->lastInsertId();
            }
            $attachTag->execute([$articleId, $tagId]);
        }

        if (($article['status'] ?? 'draft') !== 'published' && $status === 'published') {
            $pdo->prepare('INSERT INTO notifications (user_id,title,body,link) VALUES (NULL,?,?,?)')
                ->execute(['ახალი სტატია', $title, site_path('/news/' . rawurlencode($slug))]);
        }

        $pdo->commit();
        foreach ($replacedMedia as [$oldFile, $oldDir]) {
            delete_local_media($oldFile, $oldDir);
        }

        $_SESSION['success_message'] = 'სტატია წარმატებით განახლდა.';
        header('Location: ' . ($status === 'published' ? '../news/' . rawurlencode($slug) : 'preview_article.php?id=' . $articleId));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newUploads as [$newFile, $newDir]) {
            delete_local_media($newFile, $newDir);
        }
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: edit_article.php?id=' . $articleId);
        exit;
    }
}

$subcategoryValues = array_filter(array_map('trim', explode(',', (string)($article['subcategory'] ?? ''))));
$storyExpiresValue = !empty($article['story_expires_at']) ? date('Y-m-d\TH:i', strtotime($article['story_expires_at'])) : date('Y-m-d\TH:i', strtotime('+24 hours'));
$categories = [
    'ფეხბურთი' => 'ფეხბურთი',
    'კალათბურთი' => 'კალათბურთი',
    'ჩოგბურთი' => 'ჩოგბურთი',
    'ფორმულა 1' => 'ფორმულა 1',
    'UFC' => 'MMA',
    'რაგბი' => 'რაგბი',
    'სხვა' => 'სხვა',
];
$types = [
    'news' => 'სიახლე',
    'opinion' => 'მოსაზრება',
    'analysis' => 'ანალიზი',
    'live' => 'ლაივი',
    'video' => 'ვიდეო',
];

admin_page_start('სტატიის რედაქტირება', [
    '/sport_blog/css/add_article.css?v=20261119i',
    'https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css',
]);
?>

<section class="admin-editor">
    <div class="admin-editor__header">
        <div>
            <p class="admin-eyebrow">კონტენტი</p>
            <h2>სტატიის რედაქტირება</h2>
        </div>
        <a class="admin-button admin-button--neutral" href="admin_articles.php">სტატიები</a>
    </div>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form class="article-form" method="POST" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>

        <section class="form-section">
            <h2>ძირითადი ინფორმაცია</h2>
            <label>სათაური
                <input type="text" name="title" value="<?= htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>Slug
                <input type="text" name="slug" value="<?= htmlspecialchars($article['slug'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>
            <label>რეზიუმე
                <textarea name="summary" rows="3"><?= htmlspecialchars($article['summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <label>ტექსტი
                <textarea class="rich-editor" name="content" rows="12"><?= htmlspecialchars($article['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
        </section>

        <section class="form-section form-grid">
            <label>კატეგორია
                <select name="category" required>
                    <?php foreach ($categories as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $article['category'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>ტიპი
                <select name="type">
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($article['type'] ?? 'news') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>სტატუსი
                <select name="status">
                    <option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>გამოქვეყნებული</option>
                    <option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>დრაფტი</option>
                </select>
            </label>
            <label>დაგეგმილი გამოქვეყნება
                <input type="datetime-local" name="scheduled_at" value="<?= !empty($article['scheduled_at']) ? date('Y-m-d\TH:i',strtotime($article['scheduled_at'])) : '' ?>">
            </label>
            <label>ქვეკატეგორიები
                <input name="subcategory" id="subcategory-input" value='<?= htmlspecialchars(json_encode(array_map(fn($t) => ['value' => $t], $subcategoryValues), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
            </label>
            <label class="form-grid__wide">თეგები / კლუბები
                <input name="tags" id="tags-input" value='<?= htmlspecialchars(json_encode(array_map(fn($t) => ['value' => $t], $tagNames), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>
            </label>
        </section>

        <section class="form-section">
            <h2>მედია</h2>
            <div class="media-preview-grid">
                <label>მთავარი ფოტო
                    <input class="media-input" type="file" name="image" accept="image/*" data-preview="image-preview">
                    <img id="image-preview" class="media-preview" src="<?= htmlspecialchars(article_image_url($article['image'] ?? null), ENT_QUOTES, 'UTF-8') ?>" alt="">
                </label>
                <label>მეორე ფოტო
                    <input class="media-input" type="file" name="second_image" accept="image/*" data-preview="second-image-preview">
                    <img id="second-image-preview" class="media-preview" src="<?= htmlspecialchars(article_image_url($article['second_image'] ?? null, 'uploads/second_images'), ENT_QUOTES, 'UTF-8') ?>" alt="">
                </label>
            </div>
            <label class="checkbox-row">
                <input type="checkbox" name="has_video" value="1" data-toggle="video-fields" <?= !empty($article['has_video']) ? 'checked' : '' ?>>
                <span>აქვს ვიდეო</span>
            </label>
            <div id="video-fields" class="conditional-fields" <?= !empty($article['has_video']) ? '' : 'hidden' ?>>
                <label>ვიდეო ფაილი
                    <input type="file" name="video" accept="video/mp4,video/webm">
                </label>
            </div>
        </section>

        <section class="form-section">
            <label class="checkbox-row">
                <input type="checkbox" name="show_in_story" value="1" data-toggle="story-fields" <?= !empty($article['show_in_story']) ? 'checked' : '' ?>>
                <span>გამოჩნდეს სთორიში</span>
            </label>
            <div id="story-fields" class="conditional-fields" <?= !empty($article['show_in_story']) ? '' : 'hidden' ?>>
                <div class="form-grid">
                    <label>სთორის ვადა
                        <input type="datetime-local" name="story_expires_at" value="<?= htmlspecialchars($storyExpiresValue, ENT_QUOTES, 'UTF-8') ?>">
                    </label>
                    <label>სთორის ფოტო
                        <input class="media-input" type="file" name="story_image" accept="image/*" data-preview="story-image-preview">
                    </label>
                </div>
                <img id="story-image-preview" class="media-preview media-preview--story" src="<?= htmlspecialchars(article_story_image_url($article), ENT_QUOTES, 'UTF-8') ?>" alt="">
            </div>
        </section>

        <div class="admin-form-actions">
            <label class="checkbox-row">
                <input type="checkbox" name="popular" value="1" <?= !empty($article['popular']) ? 'checked' : '' ?>>
                <span>პოპულარული</span>
            </label>
            <button type="button" class="admin-button admin-button--neutral" id="draft-preview">Preview</button>
            <button type="submit" class="admin-button admin-button--primary">შენახვა</button>
        </div>
    </form>
</section>

<div class="editor-preview-modal" id="preview-modal" hidden>
    <div class="editor-preview-modal__content">
        <button type="button" class="editor-preview-modal__close" aria-label="დახურვა">&times;</button>
        <h2 id="preview-title"></h2>
        <div id="preview-content"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    window.clubTags = <?= json_encode($clubTags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="../js/article_editor.js"></script>

<?php admin_page_end(); ?>
