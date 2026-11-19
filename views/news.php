<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/sport_blog/views/articles.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 301);
exit;
