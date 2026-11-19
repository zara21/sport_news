<?php

$root = dirname(__DIR__);
$cssFiles = glob($root . '/css/*.css') ?: [];
$errors = [];

foreach ($cssFiles as $file) {
    $css = (string) file_get_contents($file);
    $name = basename($file);

    if (substr_count($css, '{') !== substr_count($css, '}')) {
        $errors[] = "{$name}: unbalanced braces";
    }
    if (preg_match('/font-size\s*:\s*clamp\s*\(/i', $css)) {
        $errors[] = "{$name}: viewport-scaled font size";
    }
    if (preg_match('/\b100vw\b/i', $css)) {
        $errors[] = "{$name}: 100vw can overflow the page";
    }
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || str_contains($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $php = (string) file_get_contents($path);
    if (preg_match('/<style\b|\sstyle\s*=\s*["\']/i', $php)) {
        $errors[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path) . ': inline CSS';
    }
}

$requiredResponsiveFiles = ['home.css', 'navbar.css', 'category.css', 'article.css', 'quiz.css', 'match.css', 'event.css', 'profile.css', 'admin.css'];
foreach ($requiredResponsiveFiles as $name) {
    $css = (string) file_get_contents($root . '/css/' . $name);
    if (!str_contains($css, '@media')) {
        $errors[] = "{$name}: missing responsive rules";
    }
}

if ($errors) {
    foreach ($errors as $error) {
        echo "FAIL {$error}\n";
    }
    exit(1);
}

echo 'PASS CSS files: ' . count($cssFiles) . "\n";
echo "PASS balanced blocks\n";
echo "PASS responsive typography\n";
echo "PASS no inline PHP styles\n";
exit(0);
