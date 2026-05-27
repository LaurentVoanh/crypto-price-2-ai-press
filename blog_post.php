<?php
$id = (int)($_GET['id'] ?? 0);
$dbFile = 'crypto_cache.db';
$pdo = new PDO("sqlite:$dbFile");
$stmt = $pdo->prepare("SELECT title, content, created_at FROM ai_blog_posts WHERE id = ?");
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) die("Article non trouvé.");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-white">
<div class="container py-5">
    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p class="text-muted">Publié le <?= date('d/m/Y H:i', $post['created_at']) ?></p>
    <div class="mt-4" style="font-size:1.1rem; line-height:1.6;"><?= nl2br(htmlspecialchars($post['content'])) ?></div>
    <a href="blog.php" class="btn btn-secondary mt-4">← Retour au blog</a>
</div>
</body>
</html>