<?php
require_once __DIR__ . '/../includes/db_connect.php';

function fixed_default_story_slug(string $clubName): string
{
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clubName);
    $slug = strtolower($slug);
    $slug = trim(preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
    return 'default-story-' . ($slug !== '' ? $slug : substr(md5($clubName), 0, 8));
}

$articles = $pdo->query("
    SELECT a.id, a.slug, t.name AS club_name
    FROM articles a
    INNER JOIN article_tags at ON at.article_id = a.id
    INNER JOIN tags t ON t.id = at.tag_id
    INNER JOIN club_logos c ON c.club_name = t.name
    WHERE a.title = CONCAT(t.name, ' - კლუბის სთორი')
")->fetchAll(PDO::FETCH_ASSOC);

$exists = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ? AND id != ?');
$update = $pdo->prepare('UPDATE articles SET slug = ? WHERE id = ?');
$updated = 0;

foreach ($articles as $article) {
    $baseSlug = fixed_default_story_slug($article['club_name']);
    $slug = $baseSlug;
    $suffix = 2;

    while (true) {
        $exists->execute([$slug, $article['id']]);
        if ((int) $exists->fetchColumn() === 0) {
            break;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }

    if ($slug !== $article['slug']) {
        $update->execute([$slug, $article['id']]);
        $updated++;
    }
}

echo "Updated slugs: {$updated}\n";
