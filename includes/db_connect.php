<?php
require_once __DIR__ . '/site_config.php';
$host = 'localhost';
$db   = 'auth_system';
$user = 'root'; 
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     require_once __DIR__ . '/scheduler.php';
     run_due_scheduler($pdo);
     // echo "კავშირი წარმატებულია!"; // ამის ჩაწერა შეგიძლია დასატესტად
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}
?>
