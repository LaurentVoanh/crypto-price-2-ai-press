<?php
// portfolio.php - État du portefeuille virtuel 1M€
$dbFile = 'crypto_cache.db';
$pdo = new PDO("sqlite:$dbFile");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$cash = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetchColumn();
$holdings = $pdo->query("SELECT h.coin_id, h.amount, h.avg_buy_price, c.current_price, c.name, c.symbol 
                         FROM holdings h JOIN coins c ON h.coin_id = c.id")->fetchAll();
$totalHoldings = 0;
foreach ($holdings as $h) $totalHoldings += $h['amount'] * $h['current_price'];
$totalPortfolio = $cash + $totalHoldings;
$perf = round(($totalPortfolio - 1000000) / 1000000 * 100, 2);
$thresholds = $pdo->query("SELECT param, value FROM rl_thresholds")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Portefeuille virtuel · NEO CRYPTO DASH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-wallet"></i> Portefeuille IA – 1 000 000 € virtuels</h2>
        <a href="index.php" class="btn btn-outline-secondary">Tableau de bord</a>
    </div>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-white bg-primary">
                <div class="card-body"><h5>Cash disponible</h5><h3><?= number_format($cash, 2) ?> €</h3></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success">
                <div class="card-body"><h5>Valeur des holdings</h5><h3><?= number_format($totalHoldings, 2) ?> €</h3></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-dark">
                <div class="card-body"><h5>Performance globale</h5><h3><?= $perf ?> %</h3></div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Seuils d’achat/vente (auto‑apprentissage)</div>
        <div class="card-body">Achat si score >= <?= $thresholds['buy_score'] ?> | Vente si score <= <?= $thresholds['sell_score'] ?></div>
    </div>
    <?php if ($holdings): ?>
    <div class="card">
        <div class="card-header">Détentions actuelles</div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Crypto</th><th>Quantité</th><th>Prix d'achat moyen</th><th>Prix actuel</th><th>Valeur</th><th>P&L</th></tr></thead>
                <tbody>
                    <?php foreach ($holdings as $h): 
                        $value = $h['amount'] * $h['current_price'];
                        $cost = $h['amount'] * $h['avg_buy_price'];
                        $pl = $value - $cost;
                        $plPercent = ($pl / $cost) * 100;
                    ?>
                    <tr>
                        <td><img src="https://www.coingecko.com/coins/<?= $h['coin_id'] ?>/thumb" width="24" class="me-2"> <?= $h['name'] ?> (<?= strtoupper($h['symbol']) ?>)</td>
                        <td><?= number_format($h['amount'], 6) ?></td>
                        <td><?= number_format($h['avg_buy_price'], 2) ?> €</td>
                        <td><?= number_format($h['current_price'], 2) ?> €</td>
                        <td><?= number_format($value, 2) ?> €</td>
                        <td class="<?= $pl >=0 ? 'text-success' : 'text-danger' ?>"><?= number_format($pl, 2) ?> € (<?= number_format($plPercent,2) ?>%)</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">Aucune crypto détenue pour le moment. L’IA achètera quand les scores dépasseront le seuil.</div>
    <?php endif; ?>
</div>
</body>
</html>