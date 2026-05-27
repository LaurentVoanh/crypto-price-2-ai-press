<?php
// stats.php - Historique des prix, market cap, volume avec évaluation des tendances IA et trades virtuels
$dbFile = 'crypto_cache.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$coinId = $_GET['coin'] ?? 'bitcoin';
$days = (int)($_GET['days'] ?? 30);
$days = in_array($days, [7,30,60]) ? $days : 30;

$stmtInfo = $pdo->prepare("SELECT name, symbol, image FROM coins WHERE id = ?");
$stmtInfo->execute([$coinId]);
$info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
if (!$info) die("Crypto non trouvée.");

$since = time() - ($days * 86400);
$stmt = $pdo->prepare("SELECT snapshot_time, current_price, market_cap, total_volume 
                       FROM historical_snapshots 
                       WHERE coin_id = ? AND snapshot_time >= ?
                       ORDER BY snapshot_time ASC");
$stmt->execute([$coinId, $since]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$timestamps = []; $prices = []; $marketCaps = []; $volumes = [];
foreach ($rows as $row) {
    $timestamps[] = date('Y-m-d H:i', $row['snapshot_time']);
    $prices[] = $row['current_price'];
    $marketCaps[] = $row['market_cap'];
    $volumes[] = $row['total_volume'];
}

// Récupérer l'historique des analyses avec précision
$analysisHistory = $pdo->prepare("SELECT timestamp, advice, score, predicted_change_pct, actual_change_pct, accuracy_score 
                                  FROM coin_analysis_history WHERE coin_id = ? ORDER BY timestamp DESC LIMIT 20");
$analysisHistory->execute([$coinId]);
$oldAnalyses = $analysisHistory->fetchAll(PDO::FETCH_ASSOC);

// Trades virtuels sur cette crypto
$trades = $pdo->prepare("SELECT type, amount, price, timestamp FROM trades WHERE coin_id = ? ORDER BY timestamp DESC LIMIT 10");
$trades->execute([$coinId]);
$tradeList = $trades->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique <?= htmlspecialchars($info['name']) ?> · NEO CRYPTO DASH</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@300..700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { background: #f9fafb; font-family: 'Inter', sans-serif; }
        .card-glass { background: white; border-radius: 28px; border: 1px solid #e5e7eb; padding: 1.5rem; margin-bottom: 1.5rem; }
        .badge-correct { background: #d1fae5; color: #065f46; }
        .badge-wrong { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-3 align-items-center">
            <img src="<?= htmlspecialchars($info['image']) ?>" width="52" class="rounded-circle">
            <h2><?= htmlspecialchars($info['name']) ?> <span class="text-muted fs-4">(<?= strtoupper($info['symbol']) ?>)</span></h2>
        </div>
        <a href="index.php" class="btn btn-dark"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($oldAnalyses)): ?>
    <div class="card-glass">
        <h5><i class="fas fa-chart-line"></i> Évaluation des analyses IA (apprentissage par renforcement)</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Date</th><th>Conseil</th><th>Score</th><th>Prédiction %</th><th>Réalisation %</th><th>Précision</th></tr></thead>
                <tbody>
                    <?php foreach ($oldAnalyses as $a): ?>
                    <tr>
                        <td><?= date('Y-m-d H:i', $a['timestamp']) ?></td>
                        <td><?= htmlspecialchars(substr($a['advice'],0,80)) ?></td>
                        <td><?= $a['score'] ?></td>
                        <td><?= $a['predicted_change_pct'] ?>%</td>
                        <td><?= $a['actual_change_pct'] !== null ? $a['actual_change_pct'].'%' : 'en attente' ?></td>
                        <td><?= $a['accuracy_score'] !== null ? '<span class="badge '.($a['accuracy_score']>=70?'badge-correct':'badge-wrong').'">'.$a['accuracy_score'].'%</span>' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($tradeList)): ?>
    <div class="card-glass">
        <h5><i class="fas fa-exchange-alt"></i> Trades virtuels sur cette crypto</h5>
        <table class="table table-sm">
            <thead><tr><th>Date</th><th>Type</th><th>Quantité</th><th>Prix (€)</th></tr></thead>
            <tbody>
                <?php foreach ($tradeList as $t): ?>
                <tr>
                    <td><?= date('Y-m-d H:i', $t['timestamp']) ?></td>
                    <td class="<?= $t['type']=='buy'?'text-success':'text-danger' ?>"><?= strtoupper($t['type']) ?></td>
                    <td><?= number_format($t['amount'], 8) ?></td>
                    <td><?= number_format($t['price'], 2) ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="alert alert-info">Aucune donnée historique pour cette période.</div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-6"><div class="card-glass"><canvas id="priceChart"></canvas></div></div>
            <div class="col-lg-6"><div class="card-glass"><canvas id="marketCapChart"></canvas></div></div>
            <div class="col-12"><div class="card-glass"><canvas id="volumeChart"></canvas></div></div>
        </div>
    <?php endif; ?>

    <script>
        const timestamps = <?= json_encode($timestamps) ?>;
        const prices = <?= json_encode($prices) ?>;
        const marketCaps = <?= json_encode($marketCaps) ?>;
        const volumes = <?= json_encode($volumes) ?>;
        if (timestamps.length) {
            new Chart(document.getElementById('priceChart'), { type: 'line', data: { labels: timestamps, datasets: [{ label: 'Prix (€)', data: prices, borderColor: '#3b82f6', fill: true }] } });
            new Chart(document.getElementById('marketCapChart'), { type: 'line', data: { labels: timestamps, datasets: [{ label: 'Market Cap (€)', data: marketCaps, borderColor: '#10b981', fill: true }] } });
            new Chart(document.getElementById('volumeChart'), { type: 'bar', data: { labels: timestamps, datasets: [{ label: 'Volume 24h (€)', data: volumes, backgroundColor: '#ef4444' }] } });
        }
    </script>
</body>
</html>