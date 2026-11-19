<?php
require_once __DIR__ . '/../includes/db_connect.php';

$userId = (int) $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($userId <= 0) {
    throw new RuntimeException('No user found for default story articles.');
}

function default_story_slug(string $clubName): string
{
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clubName);
    $slug = strtolower($slug);
    $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
    return 'default-story-' . ($slug !== '' ? $slug : substr(md5($clubName), 0, 8));
}

$clubs = $pdo->query('SELECT club_name, logo_url FROM club_logos ORDER BY club_name ASC')->fetchAll(PDO::FETCH_ASSOC);

$findTag = $pdo->prepare('SELECT id FROM tags WHERE name = ? LIMIT 1');
$createTag = $pdo->prepare('INSERT INTO tags (name) VALUES (?)');
$hasStory = $pdo->prepare('
    SELECT COUNT(*)
    FROM articles a
    INNER JOIN article_tags at ON a.id = at.article_id
    INNER JOIN tags t ON t.id = at.tag_id
    WHERE t.name = ?
      AND a.status = "published"
      AND a.show_in_story = 1
');
$slugExists = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ?');
$insertArticle = $pdo->prepare('
    INSERT INTO articles
        (user_id, title, content, image, story_image, show_in_story, category, summary, slug, tags, popular, status, type)
    VALUES
        (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, 0, "published", "news")
');
$insertRelation = $pdo->prepare('INSERT INTO article_tags (article_id, tag_id) VALUES (?, ?)');

$created = 0;
$skipped = 0;

$pdo->beginTransaction();
try {
    foreach ($clubs as $club) {
        $clubName = trim($club['club_name']);
        $logoUrl = trim($club['logo_url'] ?? '');
        if ($clubName === '') {
            continue;
        }

        $hasStory->execute([$clubName]);
        if ((int) $hasStory->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        $findTag->execute([$clubName]);
        $tagId = $findTag->fetchColumn();
        if (!$tagId) {
            $createTag->execute([$clubName]);
            $tagId = $pdo->lastInsertId();
        }

        $baseSlug = default_story_slug($clubName);
        $slug = $baseSlug;
        $suffix = 2;
        while (true) {
            $slugExists->execute([$slug]);
            if ((int) $slugExists->fetchColumn() === 0) {
                break;
            }
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $title = $clubName . ' - კლუბის სთორი';
        $summary = $clubName . ' Sport Blog-ის ტოპ კლუბების სთორებში.';
        $content = $clubName . " არის Sport Blog-ის ტოპ კლუბების სიაში. ეს default story სტატია გამოიყენება მაშინ, როცა კლუბს ჯერ ცალკე სთორი არ აქვს დამატებული.";

        $insertArticle->execute([
            $userId,
            $title,
            $content,
            $logoUrl,
            $logoUrl,
            'ფეხბურთი',
            $summary,
            $slug,
            $clubName,
        ]);

        $articleId = $pdo->lastInsertId();
        $insertRelation->execute([$articleId, $tagId]);
        $created++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "Created: {$created}\nSkipped existing stories: {$skipped}\n";
