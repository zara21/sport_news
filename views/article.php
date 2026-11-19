<?php
$legacyArticleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
require __DIR__ . '/../templates/articles-template.php';
