<?php

/**
 * კონფიგურაციის წაკითხვა config/<name>.php-დან (git-ში არ ინახება).
 * თუ ფაილი არ არსებობს, გამოიყენება $defaults. გარემოს ცვლადები (SPORT_BLOG_<NAME>_<KEY>) ყველაფერზე მაღლა დგას.
 */
function app_config(string $name, array $defaults = []): array
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $file = __DIR__ . '/../config/' . basename($name) . '.php';
        $values = is_file($file) ? (array) require $file : [];
        $config = array_merge($defaults, $values);
        foreach ($config as $key => $value) {
            $env = getenv(strtoupper('SPORT_BLOG_' . $name . '_' . $key));
            if ($env !== false) {
                $config[$key] = $env;
            }
        }
        $cache[$name] = $config;
    }
    return $cache[$name];
}
