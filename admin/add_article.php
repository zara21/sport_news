<?php
session_start();
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/media_helpers.php';

require_admin_user();

$clubTags = $pdo->query("SELECT club_name FROM club_logos ORDER BY club_name ASC")->fetchAll(PDO::FETCH_COLUMN);
$imageMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$videoMimes = ['video/mp4', 'video/webm'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $newUploads = [];

    try {
        $title = trim($_POST['title'] ?? '');
        $content = sanitize_article_html((string)($_POST['content'] ?? ''));
        $summary = trim($_POST['summary'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $status = $_POST['status'] ?? 'published';
        $scheduledAt = !empty($_POST['scheduled_at']) ? str_replace('T',' ',(string)$_POST['scheduled_at']).':00' : null;
        if ($scheduledAt && strtotime($scheduledAt) > time()) $status = 'draft';
        $type = $_POST['type'] ?? 'news';
        if (!in_array($status, ['published', 'draft'], true)) $status = 'draft';
        if (!in_array($type, ['news', 'opinion', 'analysis', 'live', 'video'], true)) $type = 'news';
        $popular = isset($_POST['popular']) ? 1 : 0;
        $hasVideo = isset($_POST['has_video']) ? 1 : 0;
        $showInStory = isset($_POST['show_in_story']) ? 1 : 0;
        $storyExpiresAt = $showInStory && !empty($_POST['story_expires_at']) ? str_replace('T', ' ', $_POST['story_expires_at']) . ':00' : null;

        if ($title === '' || $content === '' || $category === '') {
            throw new RuntimeException('სათაური, ტექსტი და კატეგორია სავალდებულოა.');
        }

        $tags = parse_tagify_values($_POST['tags_text'] ?? '[]');
        $subcategories = parse_tagify_values($_POST['subcategory'] ?? '[]');
        if ($showInStory && !array_intersect($tags, $clubTags)) {
            throw new RuntimeException('სთორისთვის თეგებში აირჩიეთ მინიმუმ ერთი ტოპ კლუბი.');
        }

        $slugInput = trim($_POST['slug'] ?? '');
        $slug = unique_slug($pdo, 'articles', safe_slug($slugInput !== '' ? $slugInput : $title, 'article'));

        $uploadsDir = __DIR__ . '/../uploads';
        // ახალი ფაილები ჩაიწერება სიაში, რომ შეცდომისას წაიშალოს და uploads-ში ნაგავი არ დარჩეს
        if ($image = upload_media_file('image', $uploadsDir, $imageMimes, 5 * 1024 * 1024)) $newUploads[] = [$image, $uploadsDir];
        if ($secondImage = upload_media_file('second_image', $uploadsDir . '/second_images', $imageMimes, 5 * 1024 * 1024)) $newUploads[] = [$secondImage, $uploadsDir . '/second_images'];
        if ($storyImage = upload_media_file('story_image', $uploadsDir . '/story_images', $imageMimes, 5 * 1024 * 1024)) $newUploads[] = [$storyImage, $uploadsDir . '/story_images'];
        $video = $hasVideo ? upload_media_file('video', $uploadsDir . '/videos', $videoMimes, 80 * 1024 * 1024) : null;
        if ($video) $newUploads[] = [$video, $uploadsDir . '/videos'];

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO articles
                (user_id, title, content, image, second_image, story_image, show_in_story, story_expires_at,
                 category, subcategory, summary, slug, has_video, video, tags, popular, status, scheduled_at, type)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user']['id'] ?? $_SESSION['user_id'],
            $title,
            $content,
            $image,
            $secondImage,
            $storyImage,
            $showInStory,
            $storyExpiresAt,
            $category,
            implode(',', $subcategories),
            $summary,
            $slug,
            $hasVideo,
            $video,
            implode(',', $tags),
            $popular,
            $status,
            $scheduledAt,
            $type,
        ]);

        $articleId = (int)$pdo->lastInsertId();
        $selectTag = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
        $createTag = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
        $attachTag = $pdo->prepare("INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)");

        foreach ($tags as $tag) {
            $selectTag->execute([$tag]);
            $tagId = $selectTag->fetchColumn();
            if (!$tagId) {
                $createTag->execute([$tag]);
                $tagId = $pdo->lastInsertId();
            }
            $attachTag->execute([$articleId, $tagId]);
        }

        if ($status === 'published') {
            $pdo->prepare('INSERT INTO notifications (user_id,title,body,link) VALUES (NULL,?,?,?)')
                ->execute(['ახალი სტატია', $title, site_path('/news/' . rawurlencode($slug))]);
        }

        $pdo->commit();

        $_SESSION['success_message'] = 'სტატია წარმატებით დაემატა.';
        header('Location: ' . ($status === 'published' ? '../news/' . rawurlencode($slug) : 'preview_article.php?id=' . $articleId));
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newUploads as [$newFile, $newDir]) {
            delete_local_media($newFile, $newDir);
        }
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: add_article.php');
        exit;
    }
}

admin_page_start('სტატიის დამატება', [
    '/sport_blog/css/add_article.css?v=20261119c',
    'https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css',
]);
?>

<section class="admin-editor">
    <div class="admin-editor__header">
        <div>
            <p class="admin-eyebrow">კონტენტი</p>
            <h2>ახალი სტატია</h2>
        </div>
        <a class="admin-button admin-button--neutral" href="admin_articles.php">სტატიები</a>
    </div>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="admin-alert admin-alert--error"><?= htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <form class="article-form" action="add_article.php" method="POST" enctype="multipart/form-data">
        <?php admin_csrf_field(); ?>

        <section class="form-section">
            <h2>ძირითადი ინფორმაცია</h2>
            <label>სათაური
                <input type="text" name="title" required>
            </label>
            <label>Slug
                <input type="text" name="slug" placeholder="my-first-article">
            </label>
            <label>რეზიუმე
                <textarea name="summary" rows="3" placeholder="მოკლე აღწერა..."></textarea>
            </label>
            <label>ტექსტი
                <textarea class="rich-editor" name="content" rows="12"></textarea>
            </label>
        </section>

        <section class="form-section form-grid">
            <label>კატეგორია
                <select name="category" required>
                    <option value="ფეხბურთი">ფეხბურთი</option>
                    <option value="კალათბურთი">კალათბურთი</option>
                    <option value="ჩოგბურთი">ჩოგბურთი</option>
                    <option value="ფორმულა 1">ფორმულა 1</option>
                    <option value="UFC">MMA</option>
                    <option value="რაგბი">რაგბი</option>
                    <option value="სხვა">სხვა</option>
                </select>
            </label>
            <label>ტიპი
                <select name="type">
                    <option value="news">სიახლე</option>
                    <option value="opinion">მოსაზრება</option>
                    <option value="analysis">ანალიზი</option>
                    <option value="live">ლაივი</option>
                    <option value="video">ვიდეო</option>
                </select>
            </label>
            <label>სტატუსი
                <select name="status">
                    <option value="published">გამოქვეყნებული</option>
                    <option value="draft">დრაფტი</option>
                </select>
            </label>
            <label>დაგეგმილი გამოქვეყნება
                <input type="datetime-local" name="scheduled_at">
            </label>
            <label>ქვეკატეგორიები
                <input name="subcategory" id="subcategory-input" placeholder="პრემიერ ლიგა, ლა ლიგა">
            </label>
            <label class="form-grid__wide">თეგები / კლუბები
                <input name="tags_text" id="tags-input" placeholder="Real Madrid, FC Barcelona">
            </label>
        </section>

        <section class="form-section">
            <h2>მედია</h2>
            <div class="media-preview-grid">
                <label>მთავარი ფოტო
                    <input class="media-input" type="file" name="image" accept="image/*" data-preview="image-preview">
                    <img id="image-preview" class="media-preview" src="<?= SPORT_BLOG_DEFAULT_IMAGE ?>" alt="">
                </label>
                <label>მეორე ფოტო
                    <input class="media-input" type="file" name="second_image" accept="image/*" data-preview="second-image-preview">
                    <img id="second-image-preview" class="media-preview" src="<?= SPORT_BLOG_DEFAULT_IMAGE ?>" alt="">
                </label>
            </div>
            <label class="checkbox-row">
                <input type="checkbox" name="has_video" value="1" data-toggle="video-fields">
                <span>აქვს ვიდეო</span>
            </label>
            <div id="video-fields" class="conditional-fields" hidden>
                <label>ვიდეო ფაილი
                    <input type="file" name="video" accept="video/mp4,video/webm">
                </label>
            </div>
        </section>

        <section class="form-section">
            <label class="checkbox-row">
                <input type="checkbox" name="show_in_story" value="1" data-toggle="story-fields">
                <span>გამოჩნდეს სთორიში</span>
            </label>
            <div id="story-fields" class="conditional-fields" hidden>
                <div class="form-grid">
                    <label>სთორის ვადა
                        <input type="datetime-local" name="story_expires_at" value="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>">
                    </label>
                    <label>სთორის ფოტო
                        <input class="media-input" type="file" name="story_image" accept="image/*" data-preview="story-image-preview">
                    </label>
                </div>
                <img id="story-image-preview" class="media-preview media-preview--story" src="<?= SPORT_BLOG_DEFAULT_IMAGE ?>" alt="">
            </div>
        </section>

        <div class="admin-form-actions">
            <label class="checkbox-row">
                <input type="checkbox" name="popular" value="1">
                <span>პოპულარული</span>
            </label>
            <button type="button" class="admin-button admin-button--neutral" id="draft-preview">Preview</button>
            <button type="submit" class="admin-button admin-button--primary">სტატიის დამატება</button>
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
