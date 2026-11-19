<?php
require_once __DIR__ . '/site_config.php';
require_once __DIR__ . '/app_config.php';
unset($_SESSION['user']['password']);

// პარამეტრები: config/database.php (ნიმუში: config/database.example.php)
$dbConfig = app_config('database', [
    'host' => 'localhost',
    'name' => 'auth_system',
    'user' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
]);

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password'], $options);
    $pdo->exec("SET time_zone = '" . (new DateTimeImmutable())->format('P') . "'");
    require_once __DIR__ . '/scheduler.php';
    run_due_scheduler($pdo);
} catch (\PDOException $e) {
    // დეტალები მხოლოდ ლოგში — ეკრანზე ბაზის მისამართი/მომხმარებელი არ ჩანს
    error_log('Database connection failed: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(503);
    exit('საიტი დროებით მიუწვდომელია. სცადეთ მოგვიანებით.');
}
unset($dbConfig);
