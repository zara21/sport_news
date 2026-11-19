<?php

const SPORT_BLOG_DEFAULT_IMAGE = '/sport_blog/uploads/defaults/article-default.svg';

function cached_remote_image_url(?string $url): string
{
    $url = trim((string) $url);
    if (!preg_match('/^https:\/\//i', $url)) {
        return $url;
    }
    $allowedHosts = ['static.flashscore.com', 'crests.football-data.org', 'cdn.sportfeeds.io'];
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if (!in_array($host, $allowedHosts, true)) {
        return $url;
    }
    return '/sport_blog/media/image.php?src=' . rawurlencode($url);
}

function article_image_url(?string $image, string $directory = 'uploads'): string
{
    $image = trim((string) $image);
    if ($image === '') {
        return SPORT_BLOG_DEFAULT_IMAGE;
    }

    if (preg_match('/^https?:\/\//i', $image) || strpos($image, '/') === 0) {
        return $image;
    }

    return '/sport_blog/' . trim($directory, '/') . '/' . ltrim($image, '/');
}

function article_story_image_url(array $article): string
{
    if (!empty($article['story_image'])) {
        return article_image_url($article['story_image'], 'uploads/story_images');
    }

    return article_image_url($article['image'] ?? null);
}

function render_article_content(?string $html): string
{
    require_once __DIR__ . '/html_sanitizer.php';
    return nl2br(sanitize_rich_html($html));
}
