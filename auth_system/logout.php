<?php
session_start();
session_unset();
session_destroy();

// დაბრუნება login-ზე ან მთავარ გვერდზე
header('Location: /sport_blog/index.php');
exit;
?>
