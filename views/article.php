<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/header.php';

// ვამოწმებთ თუ სტატიის id არის GET-ში
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: home.php');
    exit;
}

$article_id = intval($_GET['id']);

// სტატიის წამოღება
$stmt = $conn->prepare("SELECT a.*, u.first_name, u.last_name FROM articles a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo "<div class='container'><p>სტატია ვერ მოიძებნა.</p></div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="container">
    <div class="full-article">
        <?php if (!empty($article['image'])): ?>
            <img src="../uploads/<?php echo htmlspecialchars($article['image']); ?>" alt="სტატიის სურათი" class="full-article-image">
        <?php endif; ?>

        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
        <p class="category">კატეგორია: <?php echo htmlspecialchars($article['category']); ?></p>
        <p class="author">ავტორი: <?php echo htmlspecialchars($article['first_name']) . ' ' . htmlspecialchars($article['last_name']); ?></p>
        <p class="date">გამოქვეყნდა: <?php echo date('d.m.Y', strtotime($article['created_at'])); ?></p>

        <div class="content">
            <?php echo nl2br(htmlspecialchars($article['content'])); ?>
        </div>

        <a href="home.php" class="btn-back">⬅️ უკან დაბრუნება</a>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
