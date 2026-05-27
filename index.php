<?php
/**
 * index.php
 * Affiche les 100 premières cryptos, les analyses individuelles,
 * déclenche automatiquement les mises à jour (update, analyses, portfolio) via AJAX,
 * affiche une longue revue de presse IA lorsque toutes les analyses sont fraîches (< 1h).
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SELECT id, symbol, name, image, current_price, market_cap, market_cap_rank, price_change_percentage_24h, total_volume, sparkline FROM coins ORDER BY market_cap_rank ASC LIMIT 100");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtAnalyses = $pdo->query("SELECT coin_id, advice, generated_at FROM individual_analysis");
    $analysesMap = [];
    while ($row = $stmtAnalyses->fetch(PDO::FETCH_ASSOC)) {
        $analysesMap[$row['coin_id']] = $row;
    }
    
    // Vérifier la fraîcheur des analyses (les 100 premières cryptos ont-elles une analyse < 1h ?)
    $freshCount = 0;
    foreach ($coins as $coin) {
        $analysis = $analysesMap[$coin['id']] ?? null;
        if ($analysis && ($analysis['generated_at'] > time() - 3600)) $freshCount++;
    }
    $allFresh = ($freshCount >= 100);
    
    // Récupérer la dernière analyse globale (longue revue de presse)
    $globalAnalysisText = null;
    $globalAdvice = null;
    $globalDate = null;
    if ($allFresh) {
        $stmtGlobal = $pdo->query("SELECT analysis_text, global_advice, generated_at FROM global_analysis ORDER BY generated_at DESC LIMIT 1");
        $global = $stmtGlobal->fetch(PDO::FETCH_ASSOC);
        if ($global && $global['generated_at'] > time() - 7200) {
            $globalAnalysisText = $global['analysis_text'];
            $globalAdvice = $global['global_advice'];
            $globalDate = date('H:i', $global['generated_at']);
        } else {
            // Si pas d'analyse globale fraîche, on en génère une en AJAX plus tard
            $globalAnalysisText = null;
        }
    }
    
    // Liste des cryptos nécessitant une analyse (si analyse absente ou > 1h)
    $needAnalysis = [];
    foreach ($coins as $coin) {
        $analysis = $analysesMap[$coin['id']] ?? null;
        if (!$analysis || ($analysis['generated_at'] < time() - 3600)) {
            $needAnalysis[] = $coin['id'];
        }
    }
    
} catch (PDOException $e) {
    $coins = [];
    $analysesMap = [];
    $allFresh = false;
    $globalAnalysisText = null;
    $needAnalysis = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEO CRYPTO DASH | IA Market Analyst</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #ffffff; color: #111827; }
        .neo-header { background: rgba(255,255,255,0.98); backdrop-filter: blur(12px); border-bottom: 1px solid #e5e7eb; padding: 1.25rem 0; position: sticky; top: 0; z-index: 1000; }
        h1 { font-weight: 800; font-size: 1.8rem; background: linear-gradient(135deg, #111827, #3b82f6); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .global-analysis-card { background: #ffffff; border-radius: 32px; border: 1px solid #e5e7eb; padding: 1.5rem; margin: 1.5rem 0; box-shadow: 0 8px 20px rgba(0,0,0,0.02); }
        .table-crypto thead th { background: #f9fafb; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #4b5563; }
        .coin-img { width: 32px; height: 32px; object-fit: contain; }
        .positive { color: #10b981; font-weight: 600; }
        .negative { color: #ef4444; font-weight: 600; }
        .sparkline-canvas { width: 100px; height: 36px; }
        .btn-ai-indiv { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 40px; padding: 0.25rem 0.75rem; font-size: 0.7rem; transition: all 0.2s; }
        .btn-ai-indiv:hover { background: #e5e7eb; }
        .ai-result { font-size: 0.7rem; margin-top: 0.3rem; color: #4b5563; max-width: 220px; }
        .loader-sm { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #e5e7eb; border-top-color: #3b82f6; border-radius: 50%; animation: spin 0.5s linear infinite; margin-right: 5px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .analysis-progress { font-size: 0.7rem; background: #e5e7eb; border-radius: 30px; padding: 0.2rem 0.8rem; margin-left: 0.5rem; }
        iframe#autoUpdater { display: none; }
    </style>
</head>
<body>

<div class="neo-header">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-chart-network me-2"></i> NEO CRYPTO DASH</h1>
                <p class="text-muted small mb-0"><i class="fas fa-microchip"></i> IA Mistral · Analyses RL · Portefeuille virtuel 1M€</p>
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-secondary" id="updateStatus">Mise à jour auto...</span>
                <?php if (!empty($needAnalysis)): ?>
                <span class="analysis-progress" id="analysisProgress"><i class="fas fa-sync-alt fa-spin"></i> Analyse en cours...</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid px-3 px-lg-4">
    <!-- Zone de revue de presse longue (si analyses fraîches) -->
    <div class="global-analysis-card" id="globalAnalysisContainer">
        <?php if ($allFresh && $globalAnalysisText): ?>
            <div class="d-flex gap-3">
                <i class="fas fa-newspaper fa-3x text-primary"></i>
                <div class="w-100">
                    <h4 class="mb-2">📰 Revue de presse IA – Marché & investissements</h4>
                    <div style="font-size:1rem; line-height:1.5;"><?= nl2br(htmlspecialchars($globalAnalysisText)) ?></div>
                    <hr>
                    <div class="fw-bold"><i class="fas fa-gem"></i> Conseil global :</div>
                    <div class="bg-light p-2 rounded"><?= htmlspecialchars($globalAdvice) ?></div>
                    <div class="text-muted small mt-2">Mise à jour : <?= $globalDate ?></div>
                </div>
            </div>
        <?php elseif ($allFresh && !$globalAnalysisText): ?>
            <div class="text-center py-3" id="globalLoader">
                <div class="spinner-border text-primary"></div> <span>Génération de la revue de presse...</span>
            </div>
        <?php else: ?>
            <div class="text-center py-3">
                <div class="spinner-border text-primary"></div> <span>Attente des analyses individuelles fraîches (<?= $freshCount ?>/100) ...</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tableau des cryptos -->
    <?php if (empty($coins)): ?>
        <div class="alert alert-warning">Aucune donnée. Initialisation en cours...</div>
    <?php else: ?>
    <div class="table-responsive">
        <table id="cryptoTable" class="table table-hover table-crypto w-100">
            <thead>
                <tr><th>#</th><th>Icône</th><th>Nom</th><th>Symbole</th><th>Prix (EUR)</th>
                <th>Market Cap</th><th>Volume 24h</th><th>Variation 24h</th>
                <th>Sparkline 7j</th><th>Conseil IA</th><th>Graphique</th></tr>
            </thead>
            <tbody>
                <?php foreach ($coins as $coin): 
                    $sparkline = json_decode($coin['sparkline'], true) ?: [];
                    $sparklineJson = htmlspecialchars(json_encode($sparkline), ENT_QUOTES);
                    $priceChange = (float)($coin['price_change_percentage_24h'] ?? 0);
                    $changeClass = $priceChange >= 0 ? 'positive' : 'negative';
                    $changeIcon = $priceChange >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                    $analysis = $analysesMap[$coin['id']] ?? null;
                    $adviceText = $analysis ? $analysis['advice'] : "En attente...";
                    $adviceTime = $analysis ? date('H:i', $analysis['generated_at']) : '';
                    $needThis = in_array($coin['id'], $needAnalysis);
                ?>
                <tr data-coin-id="<?= htmlspecialchars($coin['id']) ?>"
                    data-coin-name="<?= htmlspecialchars($coin['name']) ?>"
                    data-price="<?= htmlspecialchars($coin['current_price']) ?>"
                    data-change="<?= $priceChange ?>"
                    data-rank="<?= $coin['market_cap_rank'] ?>"
                    data-sparkline='<?= $sparklineJson ?>'>
                    <td><?= $coin['market_cap_rank'] ?></td>
                    <td><img src="<?= htmlspecialchars($coin['image']) ?>" class="coin-img rounded-circle" loading="lazy"></td>
                    <td class="fw-semibold"><?= htmlspecialchars($coin['name']) ?></td>
                    <td class="text-uppercase"><?= htmlspecialchars($coin['symbol']) ?></td>
                    <td class="fw-bold"><?= number_format($coin['current_price'], 2, ',', ' ') ?> €</td>
                    <td><?= $coin['market_cap'] >= 1e9 ? number_format($coin['market_cap']/1e9, 2).' Md €' : number_format($coin['market_cap']/1e6, 2).' M €' ?></td>
                    <td><?= $coin['total_volume'] >= 1e9 ? number_format($coin['total_volume']/1e9, 2).' Md €' : number_format($coin['total_volume']/1e6, 2).' M €' ?></td>
                    <td class="<?= $changeClass ?>"><i class="fas <?= $changeIcon ?> me-1"></i> <?= number_format($priceChange, 2) ?>%</td>
                    <td><canvas class="sparkline-canvas" width="100" height="36"></canvas></td>
                    <td>
                        <button class="btn-ai-indiv trigger-individual" data-id="<?= $coin['id'] ?>">
                            <i class="fas fa-robot"></i> Forcer analyse
                        </button>
                        <div class="ai-result" id="ai-<?= $coin['id'] ?>">
                            <?php if ($analysis): ?>
                                <i class="fas fa-microchip me-1"></i> <?= htmlspecialchars($adviceText) ?>
                                <span class="text-muted ms-1">(<?= $adviceTime ?>)</span>
                                <?php if ($needThis): ?><span class="badge bg-warning text-dark ms-1">obs.</span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Analyse auto...</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><a href="stats.php?coin=<?= urlencode($coin['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-chart-simple"></i> Historique</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <footer class="text-center py-4 text-muted small">
        <i class="fas fa-database"></i> Données CoinGecko mises à jour toutes les 10 min | Analyses IA + RL toutes les heures | Portefeuille virtuel 1M€ |
        <a href="blog.php" class="text-muted"><i class="fas fa-blog"></i> Blog AI</a> |
        <a href="portfolio.php" class="text-muted"><i class="fas fa-wallet"></i> Portefeuille</a>
    </footer>
</div>

<!-- iframe invisible pour exécuter les scripts d’arrière-plan -->
<iframe id="autoUpdater" src="about:blank" style="display:none;"></iframe>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    function drawSparkline(canvas, prices) {
        if (!canvas || !prices || prices.length < 2) return;
        const ctx = canvas.getContext('2d');
        const w = canvas.width, h = canvas.height;
        ctx.clearRect(0, 0, w, h);
        const min = Math.min(...prices);
        const max = Math.max(...prices);
        const range = max - min;
        if (range === 0) return;
        const stepX = w / (prices.length - 1);
        ctx.beginPath();
        ctx.strokeStyle = '#3b82f6';
        ctx.lineWidth = 1.5;
        let y0 = h - ((prices[0] - min) / range) * h;
        ctx.moveTo(0, y0);
        for (let i = 1; i < prices.length; i++) {
            let x = i * stepX;
            let y = h - ((prices[i] - min) / range) * h;
            ctx.lineTo(x, y);
        }
        ctx.stroke();
        ctx.fillStyle = 'rgba(59,130,246,0.08)';
        ctx.fill();
    }

    $(document).ready(function() {
        if ($('#cryptoTable tbody tr').length > 0) {
            $('#cryptoTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10,25,50,100],[10,25,50,100]],
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json" },
                order: [[0,'asc']],
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
                                } catch(e) { console.warn(e); }
                            }
                        }
                    });
                }
            });
        }

        // Lancement automatique des tâches en arrière-plan via iframe
        function runBackgroundTask(url, callback) {
            $.ajax({
                url: url,
                method: 'GET',
                timeout: 60000,
                success: function(resp) { if (callback) callback(resp); },
                error: function() { console.warn('Erreur sur '+url); }
            });
        }

        // 1) Mise à jour des prix toutes les 10 minutes
        setInterval(function() {
            runBackgroundTask('update.php', function(resp) {
                console.log('Update data: '+resp);
                $('#updateStatus').text('Données mises à jour');
                setTimeout(() => $('#updateStatus').text('Mise à jour auto...'), 3000);
                location.reload(); // rafraîchir pour afficher les nouveaux prix
            });
        }, 600000); // 10 min

        // 2) Analyses toutes les heures (si besoin)
        setInterval(function() {
            runBackgroundTask('update_analyses.php', function(resp) {
                console.log('Analyses: '+resp);
                $('#analysisProgress').html('<i class="fas fa-sync-alt fa-spin"></i> Analyses mises à jour');
                setTimeout(() => location.reload(), 2000);
            });
        }, 3600000); // 1 heure

        // 3) Portfolio manager toutes les heures (après analyses)
        setInterval(function() {
            runBackgroundTask('portfolio_manager.php', function(resp) {
                console.log('Portefeuille: '+resp);
            });
        }, 3600000);

        // 4) Si analyses fraîches mais pas de revue de presse globale, la générer
        if ($('#globalLoader').length) {
            runBackgroundTask('generate_global_press.php', function(resp) {
                location.reload();
            });
        }

        // Forcer analyse individuelle (bouton)
        $(document).on('click', '.trigger-individual', function() {
            const $btn = $(this);
            const coinId = $btn.data('id');
            const $row = $btn.closest('tr');
            const coinName = $row.data('coin-name');
            const price = $row.data('price');
            const change = $row.data('change');
            const rank = $row.data('rank');
            const sparkline = $row.attr('data-sparkline');
            const $resultDiv = $('#ai-' + coinId);
            $resultDiv.html('<span class="loader-sm"></span> Analyse...');
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
                timeout: 20000,
                success: function(resp) {
                    if (resp && resp.advice) {
                        $resultDiv.html('<i class="fas fa-microchip me-1"></i> ' + escapeHtml(resp.advice) + ' <span class="text-muted">(manuel)</span>');
                    } else {
                        $resultDiv.html('<span class="text-muted">⚠️ Échec</span>');
                    }
                },
                error: function() {
                    $resultDiv.html('<span class="text-danger">❌ Erreur IA</span>');
                }
            });
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    });
</script>
</body>
</html>