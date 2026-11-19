<?php

/**
 * სტატიის HTML-ის გაწმენდა whitelist-ით (DOM-ზე დაფუძნებული, regex-ის ნაცვლად).
 * დაშვებული ტეგების გარდა ყველაფერი იშლება ან "იხსნება" (ტექსტი რჩება),
 * ატრიბუტებიდან რჩება მხოლოდ უსაფრთხო ბმულები და ცხრილის colspan/rowspan.
 */

const SANITIZER_ALLOWED_TAGS = [
    'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
    'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [],
    'a' => ['href', 'title'],
    'h2' => [], 'h3' => [], 'h4' => [],
    'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
    'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
];

// ეს ტეგები შიგთავსიანად იშლება
const SANITIZER_DROP_TAGS = [
    'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template',
    'noscript', 'svg', 'math', 'head', 'title', 'meta', 'link', 'base', 'textarea', 'select',
    'button', 'input', 'form',
];

function sanitize_safe_href(string $href): ?string
{
    // კონტროლის სიმბოლოები და whitespace — ბრაუზერი მათ სქემაში აიგნორებს ("java\tscript:")
    $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', $href);
    if ($normalized === null || $normalized === '') {
        return null;
    }
    if (preg_match('#^(https?:|mailto:|/|\#|\?)#i', $normalized)) {
        return trim($href);
    }
    // სქემის გარეშე ფარდობითი ბმული (ორწერტილი არ უნდა იყოს პირველ "/"-მდე)
    $beforeSlash = strtok($normalized, '/?#');
    if ($beforeSlash !== false && strpos($beforeSlash, ':') === false) {
        return trim($href);
    }
    return null;
}

function sanitize_html_node(DOMNode $node): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            if (in_array($tag, SANITIZER_DROP_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }

            sanitize_html_node($child);

            if (!isset(SANITIZER_ALLOWED_TAGS[$tag])) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $allowedAttributes = SANITIZER_ALLOWED_TAGS[$tag];
            $attributeNames = [];
            foreach ($child->attributes as $attribute) {
                $attributeNames[] = $attribute->nodeName;
            }
            foreach ($attributeNames as $name) {
                $lower = strtolower($name);
                $value = $child->getAttribute($name);
                $child->removeAttribute($name);
                if (!in_array($lower, $allowedAttributes, true)) {
                    continue;
                }
                if ($lower === 'href') {
                    $value = sanitize_safe_href($value);
                    if ($value === null) {
                        continue;
                    }
                } elseif ($lower === 'colspan' || $lower === 'rowspan') {
                    if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 100) {
                        continue;
                    }
                }
                $child->setAttribute($lower, $value);
            }

            if ($tag === 'a' && $child->hasAttribute('href') && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        } elseif (!($child instanceof DOMText)) {
            // კომენტარები, processing instructions და ა.შ.
            $node->removeChild($child);
        }
    }
}

function sanitize_rich_html(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__sanitize_root">' . $html . '</div></body></html>',
        LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementById('__sanitize_root');
    if (!$root) {
        return '';
    }

    sanitize_html_node($root);

    $output = '';
    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }
    return trim($output);
}
