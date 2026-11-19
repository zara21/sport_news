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

    $relative = trim($directory, '/') . '/' . ltrim($image, '/');
    // ფაილი დისკზე აღარ არსებობს — გატეხილი სურათის ნაცვლად ნაგულისხმევი
    if (!is_file(__DIR__ . '/../' . $relative)) {
        return SPORT_BLOG_DEFAULT_IMAGE;
    }

    return '/sport_blog/' . $relative;
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

/* ------------------------------------------------------------------
   Responsive სურათები (srcset). მუშაობს მხოლოდ GD + WebP მხარდაჭერით;
   მის გარეშე srcset არ ემატება და ბრაუზერი ორიგინალს ტვირთავს.
   ------------------------------------------------------------------ */
const SB_THUMB_WIDTHS = [320, 640, 960, 1280];

function sb_thumbs_supported(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagewebp');
}

/**
 * srcset/sizes ატრიბუტები ადგილობრივი ატვირთული სურათისთვის (/sport_blog/uploads/...).
 * გარე ან SVG სურათებზე აბრუნებს ცარიელ სტრიქონს.
 */
function sb_srcset(string $url, string $sizes = '100vw', int $maxWidth = 1280): string
{
    if (!sb_thumbs_supported() || !preg_match('#^/sport_blog/(uploads/[^?\#]+\.(?:jpe?g|png|webp|gif))$#i', $url, $match)) {
        return '';
    }
    $candidates = [];
    foreach (SB_THUMB_WIDTHS as $width) {
        if ($width > $maxWidth) break;
        $candidates[] = '/sport_blog/media/thumb.php?src=' . rawurlencode($match[1]) . '&w=' . $width . ' ' . $width . 'w';
    }
    return ' srcset="' . htmlspecialchars(implode(', ', $candidates), ENT_QUOTES, 'UTF-8') . '" sizes="' . htmlspecialchars($sizes, ENT_QUOTES, 'UTF-8') . '"';
}
