<?php
/**
 * index.php - NEO CRYPTO DASH v3.0
 * Dashboard Crypto IA Professionnel - Design Futuriste Avancé
 * 
 * Fonctionnalités complètes:
 * - 12+ options d'analyse IA avancées
 * - Prompts longs enrichis (800-1200 mots) pour décisions précises
 * - Revue de presse puissante et chiffrée
 * - Portefeuille virtuel 1M€ avec apprentissage par renforcement
 * - Blog IA automatique
 * - Alertes intelligentes
 * - Correlations, sentiments, whale detection
 * - Compatible Hostinger + Mistral API
 */

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
ensureDatabaseInitialized();

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Récupérer les 100 premières cryptos avec toutes les données
    $stmt = $pdo->query("SELECT id, symbol, name, image, current_price, market_cap, market_cap_rank, 
                         price_change_percentage_24h, total_volume, circulating_supply, sparkline,
                         ath, atl, high_24h, low_24h, market_cap_change_percentage_24h
                         FROM coins ORDER BY market_cap_rank ASC LIMIT 100");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les analyses individuelles récentes
    $stmtAnalyses = $pdo->query("SELECT coin_id, advice, trend, score, generated_at, sentiment_score,
                                 buy_signals, sell_signals, neutral_signals, technical_summary
                                 FROM individual_analysis ORDER BY generated_at DESC");
    $analysesMap = [];
    while ($row = $stmtAnalyses->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($analysesMap[$row['coin_id']])) {
            $analysesMap[$row['coin_id']] = $row;
        }
    }

    // Vérifier la fraîcheur des analyses
    $freshCount = 0;
    $needAnalysis = [];
    foreach ($coins as $coin) {
        $analysis = $analysesMap[$coin['id']] ?? null;
        if ($analysis && ($analysis['generated_at'] > time() - 3600)) {
            $freshCount++;
        } else {
            $needAnalysis[] = $coin['id'];
        }
    }
    $allFresh = ($freshCount >= count($coins) * 0.8);

    // Récupérer la dernière analyse globale (revue de presse)
    $globalAnalysis = null;
    $stmtGlobal = $pdo->query("SELECT analysis_text, global_advice, market_summary, market_sentiment,
                               fear_greed_index, top_opportunities, top_risks, generated_at, model_used
                               FROM global_analysis ORDER BY generated_at DESC LIMIT 1");
    $global = $stmtGlobal->fetch(PDO::FETCH_ASSOC);
    if ($global) {
        $globalAnalysis = $global;
    }

    // Statistiques du portefeuille
    $portfolioStats = [
        'cash' => 1000000,
        'holdings_value' => 0,
        'total_value' => 1000000,
        'performance' => 0,
        'positions_count' => 0
    ];
    
    $portData = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($portData) {
        $portfolioStats['cash'] = $portData['cash'];
    }
    
    $holdingsData = $pdo->query("SELECT SUM(h.amount * c.current_price) as value, COUNT(*) as count
                                  FROM holdings h JOIN coins c ON h.coin_id = c.id")->fetch(PDO::FETCH_ASSOC);
    if ($holdingsData) {
        $portfolioStats['holdings_value'] = $holdingsData['value'] ?? 0;
        $portfolioStats['positions_count'] = $holdingsData['count'] ?? 0;
    }
    
    $portfolioStats['total_value'] = $portfolioStats['cash'] + $portfolioStats['holdings_value'];
    $portfolioStats['performance'] = round(($portfolioStats['total_value'] - 1000000) / 1000000 * 100, 2);

    // Seuils RL
    $thresholds = $pdo->query("SELECT param, value FROM rl_thresholds")->fetchAll(PDO::FETCH_KEY_PAIR);
    $buyScore = $thresholds['buy_score'] ?? 65;
    $sellScore = $thresholds['sell_score'] ?? 35;

} catch (PDOException $e) {
    appLog("Index error: " . $e->getMessage(), 'ERROR');
    $coins = [];
    $analysesMap = [];
    $globalAnalysis = null;
    $portfolioStats = ['cash' => 1000000, 'holdings_value' => 0, 'total_value' => 1000000, 'performance' => 0, 'positions_count' => 0];
    $buyScore = 65;
    $sellScore = 35;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NEO CRYPTO DASH - Dashboard crypto professionnel avec IA Mistral, analyses RL, portefeuille virtuel 1M€">
    <title>NEO CRYPTO DASH v3.0 | IA Market Analyst Pro</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #1d4ed8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #111827;
            --light: #f9fafb;
            --border: #e5e7eb;
            --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --card-hover: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            color: var(--dark);
            min-height: 100vh;
        }
        
        /* Header Sticky Premium */
        .neo-header {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .brand-logo {
            font-weight: 900;
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }
        
        .brand-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        /* Cards Premium */
        .card-premium {
            background: white;
            border-radius: 24px;
            border: 1px solid var(--border);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .card-premium:hover {
            box-shadow: var(--card-hover);
            transform: translateY(-2px);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 20px;
            padding: 1.25rem;
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--dark);
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.25rem;
        }
        
        /* Table Styling */
        .table-crypto {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table-crypto thead th {
            background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
            font-weight: 700;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4b5563;
            padding: 1rem 0.75rem;
            border-bottom: 2px solid var(--border);
        }
        
        .table-crypto tbody tr {
            transition: all 0.2s ease;
        }
        
        .table-crypto tbody tr:hover {
            background: #f8fafc;
        }
        
        .table-crypto tbody td {
            padding: 0.875rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        
        .coin-img {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Price Changes */
        .positive { color: var(--success); font-weight: 600; }
        .negative { color: var(--danger); font-weight: 600; }
        .neutral { color: #6b7280; font-weight: 600; }
        
        /* Sparkline Canvas */
        .sparkline-canvas {
            width: 120px;
            height: 40px;
        }
        
        /* AI Buttons & Badges */
        .btn-ai-action {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            border: none;
            border-radius: 12px;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--dark);
            transition: all 0.2s;
        }
        
        .btn-ai-action:hover {
            background: linear-gradient(135deg, #e5e7eb, #d1d5db);
            transform: scale(1.05);
        }
        
        .ai-score-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        
        .score-excellent { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .score-good { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; }
        .score-neutral { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .score-bad { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
        .ai-result-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 0.5rem 0.75rem;
            font-size: 0.7rem;
            color: #4b5563;
            max-width: 250px;
            line-height: 1.4;
        }
        
        /* Global Analysis Section */
        .global-analysis-section {
            background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);
            border-radius: 28px;
            border: 1px solid var(--border);
            padding: 2rem;
            margin: 1.5rem 0;
            box-shadow: var(--card-shadow);
        }
        
        .section-title {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Navigation Menu */
        .nav-menu {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .nav-item-pro {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .nav-item-pro:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Loader */
        .loader-spin {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #e5e7eb;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Responsive */
        @media (max-width: 768px) {
            .brand-logo { font-size: 1.2rem; }
            .stat-value { font-size: 1.25rem; }
            .table-crypto { font-size: 0.75rem; }
        }
        
        /* Utility Classes */
        .text-gradient {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .shadow-soft {
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .rounded-xxl {
            border-radius: 24px;
        }
    </style>
</head>
<body>

<!-- Header Premium -->
<header class="neo-header">
    <div class="container-fluid px-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-logo">
                    <i class="fas fa-brain me-2"></i>NEO CRYPTO DASH
                    <span class="brand-badge">v3.0 PRO</span>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="index.php" class="nav-item-pro"><i class="fas fa-home me-1"></i>Dashboard</a>
                <a href="portfolio.php" class="nav-item-pro"><i class="fas fa-wallet me-1"></i>Portefeuille</a>
                <a href="blog.php" class="nav-item-pro"><i class="fas fa-blog me-1"></i>Blog IA</a>
                <a href="#" class="nav-item-pro" onclick="forceGlobalAnalysis()"><i class="fas fa-sync-alt me-1"></i>Analyse Globale</a>
                <a href="#" class="nav-item-pro" onclick="runAllUpdates()"><i class="fas fa-bolt me-1"></i>Mise à Jour</a>
            </nav>
        </div>
    </div>
</header>

<main class="container-fluid px-4 py-4">
    
    <!-- Stats Cards Row -->
    <div class="row g-4 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($portfolioStats['total_value'], 0, ',', ' ') ?> €</div>
                <div class="stat-label"><i class="fas fa-coins me-1"></i>Valeur Portfolio</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value <?= $portfolioStats['performance'] >= 0 ? 'positive' : 'negative' ?>">
                    <?= $portfolioStats['performance'] >= 0 ? '+' : '' ?><?= $portfolioStats['performance'] ?>%
                </div>
                <div class="stat-label"><i class="fas fa-chart-line me-1"></i>Performance</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= count($coins) ?></div>
                <div class="stat-label"><i class="fas fa-list me-1"></i>Cryptos Suivies</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?= $portfolioStats['positions_count'] ?></div>
                <div class="stat-label"><i class="fas fa-briefcase me-1"></i>Positions Actives</div>
            </div>
        </div>
    </div>
    
    <!-- Global Analysis Section -->
    <?php if ($globalAnalysis): ?>
    <div class="global-analysis-section">
        <div class="section-title">
            <i class="fas fa-newspaper text-primary"></i>
            Revue de Presse IA - Analyse Globale du Marché
            <span class="badge bg-primary ms-2"><?= htmlspecialchars($globalAnalysis['market_sentiment'] ?? 'Neutre') ?></span>
            <span class="text-muted small ms-auto">
                <i class="fas fa-clock me-1"></i><?= date('d/m H:i', $globalAnalysis['generated_at']) ?>
            </span>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-premium">
                    <h6 class="fw-bold mb-3"><i class="fas fa-quote-left text-primary me-2"></i>Analyse Détaillée</h6>
                    <div class="analysis-content" style="max-height: 400px; overflow-y: auto; line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($globalAnalysis['analysis_text'])) ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card-premium mb-3">
                    <h6 class="fw-bold mb-3"><i class="fas fa-lightbulb text-warning me-2"></i>Conseil Global</h6>
                    <p class="mb-0 fw-semibold text-primary"><?= htmlspecialchars($globalAnalysis['global_advice']) ?></p>
                </div>
                
                <div class="card-premium mb-3">
                    <h6 class="fw-bold mb-2"><i class="fas fa-arrow-trend-up text-success me-2"></i>Opportunités Top</h6>
                    <?php 
                    $opportunities = json_decode($globalAnalysis['top_opportunities'] ?? '[]', true);
                    if (!empty($opportunities)):
                    ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach(array_slice($opportunities, 0, 5) as $opp): ?>
                        <li class="mb-1"><i class="fas fa-check-circle text-success me-2"></i><?= htmlspecialchars($opp) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                
                <div class="card-premium">
                    <h6 class="fw-bold mb-2"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Risques Majeurs</h6>
                    <?php 
                    $risks = json_decode($globalAnalysis['top_risks'] ?? '[]', true);
                    if (!empty($risks)):
                    ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach(array_slice($risks, 0, 5) as $risk): ?>
                        <li class="mb-1"><i class="fas fa-times-circle text-danger me-2"></i><?= htmlspecialchars($risk) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Crypto Table -->
    <div class="card-premium">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="section-title mb-0">
                <i class="fas fa-table text-primary"></i>
                Top 100 Cryptomonnaies - Analyses IA en Temps Réel
            </h5>
            <div class="d-flex gap-2">
                <span id="analysisProgress" class="badge bg-light text-dark">
                    <i class="fas fa-sync-alt me-1"></i><?= $freshCount ?>/<?= count($coins) ?> analyses fraîches
                </span>
                <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                    <i class="fas fa-redo me-1"></i>Rafraîchir
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="cryptoTable" class="table table-crypto">
                <thead>
                    <tr>
                        <th>#</th>
                        <th></th>
                        <th>Nom</th>
                        <th>Symbole</th>
                        <th>Prix (€)</th>
                        <th>Market Cap</th>
                        <th>Volume 24h</th>
                        <th>Var. 24h</th>
                        <th>Tendance 7j</th>
                        <th>Score IA</th>
                        <th>Analyse IA</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coins as $coin): 
                        $analysis = $analysesMap[$coin['id']] ?? null;
                        $priceChange = $coin['price_change_percentage_24h'] ?? 0;
                        $changeClass = $priceChange >= 0 ? 'positive' : 'negative';
                        $changeIcon = $priceChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                        $sparklineJson = htmlspecialchars($coin['sparkline'] ?? '[]');
                        
                        $scoreClass = 'score-neutral';
                        if ($analysis) {
                            $score = $analysis['score'] ?? 50;
                            if ($score >= 75) $scoreClass = 'score-excellent';
                            elseif ($score >= 60) $scoreClass = 'score-good';
                            elseif ($score < 40) $scoreClass = 'score-bad';
                        }
                    ?>
                    <tr data-coin-id="<?= htmlspecialchars($coin['id']) ?>"
                        data-coin-name="<?= htmlspecialchars($coin['name']) ?>"
                        data-price="<?= htmlspecialchars($coin['current_price']) ?>"
                        data-change="<?= $priceChange ?>"
                        data-rank="<?= $coin['market_cap_rank'] ?>"
                        data-sparkline='<?= $sparklineJson ?>'>
                        
                        <td class="fw-bold">#<?= $coin['market_cap_rank'] ?></td>
                        <td>
                            <img src="<?= htmlspecialchars($coin['image']) ?>" 
                                 class="coin-img" 
                                 alt="<?= htmlspecialchars($coin['name']) ?>"
                                 loading="lazy">
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($coin['name']) ?></td>
                        <td class="text-uppercase fw-medium"><?= htmlspecialchars($coin['symbol']) ?></td>
                        <td class="fw-bold"><?= number_format($coin['current_price'], 2, ',', ' ') ?> €</td>
                        <td><?= formatLargeNumber($coin['market_cap'] ?? 0) ?></td>
                        <td><?= formatLargeNumber($coin['total_volume'] ?? 0) ?></td>
                        <td class="<?= $changeClass ?>">
                            <i class="fas <?= $changeIcon ?> me-1"></i><?= number_format($priceChange, 2) ?>%
                        </td>
                        <td>
                            <canvas class="sparkline-canvas" width="120" height="40"></canvas>
                        </td>
                        <td>
                            <?php if ($analysis && isset($analysis['score'])): ?>
                            <div class="ai-score-badge <?= $scoreClass ?>">
                                <?= $analysis['score'] ?>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($analysis): ?>
                            <div class="ai-result-box">
                                <i class="fas fa-microchip me-1"></i>
                                <?= htmlspecialchars(substr($analysis['advice'] ?? '', 0, 60)) ?><?= strlen($analysis['advice'] ?? '') > 60 ? '...' : '' ?>
                                <div class="text-muted mt-1">
                                    <small><?= date('H:i', $analysis['generated_at']) ?></small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-muted small">En attente...</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn-ai-action trigger-individual" 
                                        data-id="<?= htmlspecialchars($coin['id']) ?>">
                                    <i class="fas fa-robot me-1"></i>Analyser
                                </button>
                                <a href="stats.php?coin=<?= urlencode($coin['id']) ?>" 
                                   class="btn btn-sm btn-outline-secondary"
                                   title="Historique complet">
                                    <i class="fas fa-chart-simple"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="text-center py-5 mt-5 border-top">
        <div class="row g-3 justify-content-center">
            <div class="col-auto">
                <span class="text-muted small">
                    <i class="fas fa-database me-1"></i>Données CoinGecko · 
                    <i class="fas fa-brain me-1"></i>IA Mistral 20 modèles ·
                    <i class="fas fa-shield-halved me-1"></i>Sécurisé Hostinger
                </span>
            </div>
        </div>
        <div class="mt-3">
            <a href="portfolio.php" class="text-muted small mx-2"><i class="fas fa-wallet me-1"></i>Portefeuille</a>
            <a href="blog.php" class="text-muted small mx-2"><i class="fas fa-blog me-1"></i>Blog IA</a>
            <a href="#" class="text-muted small mx-2" onclick="showAPIStats()"><i class="fas fa-chart-bar me-1"></i>Stats API</a>
        </div>
    </footer>
    
</main>

<!-- Hidden iframe for background tasks -->
<iframe id="autoUpdater" style="display:none;" src="about:blank"></iframe>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

<script>
// Draw sparkline chart
function drawSparkline(canvas, prices) {
    if (!canvas || !prices || prices.length < 2) return;
    
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    
    ctx.clearRect(0, 0, w, h);
    
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    const range = max - min || 1;
    
    const stepX = w / (prices.length - 1);
    
    // Gradient fill
    const gradient = ctx.createLinearGradient(0, 0, 0, h);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.01)');
    
    ctx.beginPath();
    ctx.moveTo(0, h - ((prices[0] - min) / range) * h);
    
    for (let i = 1; i < prices.length; i++) {
        const x = i * stepX;
        const y = h - ((prices[i] - min) / range) * h;
        ctx.lineTo(x, y);
    }
    
    ctx.lineTo(w, h);
    ctx.lineTo(0, h);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();
    
    // Line stroke
    ctx.beginPath();
    ctx.moveTo(0, h - ((prices[0] - min) / range) * h);
    
    for (let i = 1; i < prices.length; i++) {
        const x = i * stepX;
        const y = h - ((prices[i] - min) / range) * h;
        ctx.lineTo(x, y);
    }
    
    ctx.strokeStyle = '#3b82f6';
    ctx.lineWidth = 2;
    ctx.stroke();
}

// Initialize DataTable and charts
$(document).ready(function() {
    // Initialize DataTable
    if ($('#cryptoTable tbody tr').length > 0) {
        $('#cryptoTable').DataTable({
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
            order: [[0, 'asc']],
            responsive: true,
            drawCallback: function() {
                $('tbody tr:visible').each(function() {
                    const canvas = $(this).find('.sparkline-canvas')[0];
                    if (canvas && !canvas._drawn) {
                        const sparkJson = $(this).attr('data-sparkline');
                        if (sparkJson && sparkJson !== '[]') {
                            try {
                                const prices = JSON.parse(sparkJson);
                                if (prices.length) {
                                    drawSparkline(canvas, prices);
                                    canvas._drawn = true;
                                }
                            } catch(e) { console.warn('Sparkline error:', e); }
                        }
                    }
                });
            }
        });
    }
    
    // Individual analysis trigger
    $(document).on('click', '.trigger-individual', function() {
        const $btn = $(this);
        const coinId = $btn.data('id');
        const $row = $btn.closest('tr');
        const coinName = $row.data('coin-name');
        const price = $row.data('price');
        const change = $row.data('change');
        const rank = $row.data('rank');
        const sparkline = $row.attr('data-sparkline');
        const $resultCell = $btn.closest('td').prev();
        
        $btn.prop('disabled', true).html('<span class="loader-spin me-1"></span>Analyse...');
        
        $.ajax({
            url: 'ai_analysis.php',
            method: 'POST',
            data: {
                type: 'individual',
                coin_id: coinId,
                name: coinName,
                price: price,
                change: change,
                rank: rank,
                sparkline: sparkline
            },
            dataType: 'json',
            timeout: 30000,
            success: function(resp) {
                if (resp && resp.advice) {
                    $resultCell.html('<div class="ai-result-box"><i class="fas fa-microchip me-1"></i>' + 
                                     escapeHtml(resp.advice) + '</div>');
                } else {
                    $resultCell.html('<span class="text-muted small">⚠️ Échec</span>');
                }
            },
            error: function() {
                $resultCell.html('<span class="text-danger small">❌ Erreur IA</span>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fas fa-robot me-1"></i>Analyser');
            }
        });
    });
});

// Utility functions
function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function forceGlobalAnalysis() {
    if (!confirm('Générer une nouvelle analyse globale ? Cela peut prendre 30-60 secondes.')) return;
    
    $.ajax({
        url: 'generate_global_press.php',
        method: 'GET',
        timeout: 120000,
        success: function(resp) {
            alert('Analyse générée ! Rechargement...');
            location.reload();
        },
        error: function() {
            alert('Erreur lors de la génération');
        }
    });
}

function runAllUpdates() {
    if (!confirm('Lancer toutes les mises à jour (prix + analyses + portfolio) ?')) return;
    
    const frames = ['update.php', 'update_analyses.php', 'portfolio_manager.php'];
    let completed = 0;
    
    frames.forEach(url => {
        $.ajax({
            url: url,
            timeout: 60000,
            complete: function() {
                completed++;
                if (completed === frames.length) {
                    alert('Toutes les mises à jour sont terminées !');
                    location.reload();
                }
            }
        });
    });
}

function showAPIStats() {
    alert('Statistiques API disponibles dans le fichier logs/api_usage.log');
}

// Auto-refresh intervals
setInterval(function() {
    // Check for updates every 10 minutes
    $.get('update.php').done(function() {
        console.log('Prices updated');
    });
}, 600000);

setInterval(function() {
    // Run analyses every hour
    $.get('update_analyses.php').done(function() {
        console.log('Analyses updated');
    });
}, 3600000);
</script>

</body>
</html>
