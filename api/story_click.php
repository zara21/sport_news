<?php
if(session_status()===PHP_SESSION_NONE)session_start();require_once __DIR__.'/../includes/db_connect.php';
$articleId=(int)($_GET['article_id']??0);$to=(string)($_GET['to']??'/sport_blog/');if(!str_starts_with($to,'/sport_blog/'))$to='/sport_blog/';
if($articleId>0){$key=session_id()?:hash('sha256',($_SERVER['REMOTE_ADDR']??'').($_SERVER['HTTP_USER_AGENT']??''));$pdo->prepare('INSERT INTO story_clicks(article_id,user_id,session_key) SELECT id,?,? FROM articles WHERE id=?')->execute([$_SESSION['user_id']??null,$key,$articleId]);}
header('Location: '.$to);exit;
