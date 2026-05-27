<?php
// blog.php - Liste des articles automatiques générés par Mistral
$dbFile = 'crypto_cache.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$posts = $pdo->query("SELECT id, title, created_at, tags FROM ai_blog_posts ORDER BY created_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Blog AI · NEO CRYPTO DASH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-blog"></i> Blog IA – Apprentissage par renforcement</h2>
        <a href="index.php" class="btn btn-outline-secondary">Retour au dashboard</a>
    </div>
    <div class="row">
        <?php foreach ($posts as $post): ?>
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                    <p class="card-text text-muted small"><?= date('d/m/Y H:i', $post['created_at']) ?> · Tags: <?= htmlspecialchars($post['tags']) ?></p>
                    <a href="blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-primary">Lire l'article</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>