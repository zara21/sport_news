<?php
require_once __DIR__ . '/../includes/auth_helpers.php';
auth_start_session();
require_once __DIR__ . '/../includes/db_connect.php';
if (empty($_SESSION['user_id'])) site_redirect('/auth_system/login');
$id=(int)($_GET['id']??0); $userId=(int)$_SESSION['user_id'];
$stmt=$pdo->prepare('SELECT link FROM notifications WHERE id=? AND (user_id IS NULL OR user_id=?) LIMIT 1');
$stmt->execute([$id,$userId]); $link=(string)($stmt->fetchColumn()?:site_path('/views/notifications'));
if ($id) $pdo->prepare('INSERT IGNORE INTO notification_reads(notification_id,user_id) VALUES(?,?)')->execute([$id,$userId]);
$base=rtrim(SPORT_BLOG_BASE_PATH,'/');
if (!str_starts_with($link,'/') || ($base!=='' && $link!==$base && !str_starts_with($link,$base.'/'))) $link=site_path('/');
header('Location: '.$link); exit;
