<?php

function site_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/sport_blog';
}

function absolute_site_url(string $path): string
{
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return rtrim(site_base_url(), '/') . '/' . ltrim(preg_replace('#^/sport_blog/#', '', $path), '/');
}

function seo_description(?string $text, int $limit = 160): string
{
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
    if ($clean === '') {
        return 'Sport Blog - სპორტული სიახლეები, მატჩები, ცხრილები, ვიდეოები და ქვიზები.';
    }

    return mb_strimwidth($clean, 0, $limit, '...', 'UTF-8');
}

function article_json_ld(array $article, string $canonicalUrl, string $imageUrl): string
{
    $author = trim((string)($article['first_name'] ?? '') . ' ' . (string)($article['last_name'] ?? ''));
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $article['title'] ?? 'Sport Blog',
        'description' => seo_description($article['summary'] ?? ($article['content'] ?? '')),
        'image' => [$imageUrl],
        'datePublished' => !empty($article['created_at']) ? date(DATE_ATOM, strtotime($article['created_at'])) : null,
        'dateModified' => !empty($article['updated_at']) ? date(DATE_ATOM, strtotime($article['updated_at'])) : null,
        'author' => [
            '@type' => 'Person',
            'name' => $author !== '' ? $author : 'Sport Blog',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'Sport Blog',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => absolute_site_url('/sport_blog/assets/brand/sportblog-logo.svg'),
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonicalUrl,
        ],
    ];

    return json_encode(array_filter($data, static fn($value) => $value !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}
