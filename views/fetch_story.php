<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/media_helpers.php';

$club = trim($_GET['club'] ?? '');
header('Content-Type: application/json; charset=utf-8');

$response = [
    'club_name' => $club,
    'club_logo' => SPORT_BLOG_DEFAULT_IMAGE,
    'stories' => [],
];

if ($club === '') {
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$logoStmt = $pdo->prepare('SELECT logo_url FROM club_logos WHERE club_name = ? LIMIT 1');
$logoStmt->execute([$club]);
$response['club_logo'] = cached_remote_image_url($logoStmt->fetchColumn() ?: SPORT_BLOG_DEFAULT_IMAGE);

$stmt = $pdo->prepare('
    SELECT a.id, a.title, a.created_at, a.story_image, a.image, a.slug
    FROM articles a
    INNER JOIN article_tags at ON a.id = at.article_id
    INNER JOIN tags t ON at.tag_id = t.id
    WHERE t.name = ?
      AND a.status = "published"
      AND (a.show_in_story = 0 OR a.story_expires_at IS NULL OR a.story_expires_at > NOW())
    ORDER BY
      CASE
        WHEN a.show_in_story = 1 AND a.slug NOT LIKE "default-story-%" THEN 0
        WHEN a.slug NOT LIKE "default-story-%" THEN 1
        ELSE 2
      END,
      a.created_at DESC
    LIMIT 5
');
$stmt->execute([$club]);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $imageUrl = article_story_image_url($row);
    $response['stories'][] = [
        'title' => $row['title'],
        'date' => $row['created_at'],
        'image_url' => $imageUrl,
        'image_fit' => $imageUrl === $response['club_logo'] ? 'contain' : 'cover',
        'link' => '/sport_blog/api/story_click.php?article_id=' . (int)$row['id'] . '&to=' . rawurlencode('/sport_blog/news/' . $row['slug']),
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
