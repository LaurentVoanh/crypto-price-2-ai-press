<?php
/**
 * portfolio_manager.php
 * - Gère 1 000 000 € virtuels
 * - Achète/vend des cryptos selon le score technique (seuils ajustables par RL)
 * - Met à jour les performances et ajuste les seuils toutes les semaines
 * - Appelé automatiquement après update_analyses.php (via AJAX)
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('PORTFOLIO_LOG', ROOT_DIR . '/portfolio_log.txt');

function logPortfolio($msg) {
    file_put_contents(PORTFOLIO_LOG, '['.date('Y-m-d H:i:s')."] $msg\n", FILE_APPEND);
}

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tables nécessaires
    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
        cash REAL DEFAULT 1000000,
        last_update INTEGER
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS holdings (
        coin_id TEXT PRIMARY KEY,
        amount REAL,
        avg_buy_price REAL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS trades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coin_id TEXT,
        type TEXT,
        amount REAL,
        price REAL,
        timestamp INTEGER
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS rl_thresholds (
        param TEXT PRIMARY KEY,
        value REAL
    )");
    $pdo->exec("INSERT OR IGNORE INTO rl_thresholds VALUES ('buy_score', 65)");
    $pdo->exec("INSERT OR IGNORE INTO rl_thresholds VALUES ('sell_score', 35)");
    
    // Récupérer les dernières analyses (score le plus récent pour chaque crypto)
    $analyses = $pdo->query("SELECT c.id, c.current_price, a.score, a.advice 
                             FROM coins c 
                             JOIN coin_analysis_history a ON c.id = a.coin_id 
                             WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = c.id)
                             ORDER BY c.market_cap_rank ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    
    $buyThreshold = $pdo->query("SELECT value FROM rl_thresholds WHERE param='buy_score'")->fetchColumn();
    $sellThreshold = $pdo->query("SELECT value FROM rl_thresholds WHERE param='sell_score'")->fetchColumn();
    
    $cash = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetchColumn();
    if ($cash === false) {
        $pdo->exec("INSERT INTO portfolio (cash, last_update) VALUES (1000000, ".time().")");
        $cash = 1000000;
    }
    
    $investmentPerTrade = 5000; // 5000€ par décision
    
    foreach ($analyses as $coin) {
        $score = $coin['score'];
        $price = $coin['current_price'];
        $hold = $pdo->prepare("SELECT amount, avg_buy_price FROM holdings WHERE coin_id = ?");
        $hold->execute([$coin['id']]);
        $holding = $hold->fetch();
        
        // Vente si score < seuil vente et on possède la crypto
        if ($score < $sellThreshold && $holding && $holding['amount'] > 0) {
            $sellAmount = $holding['amount'];
            $revenue = $sellAmount * $price;
            $cash += $revenue;
            $pdo->prepare("DELETE FROM holdings WHERE coin_id = ?")->execute([$coin['id']]);
            $pdo->prepare("INSERT INTO trades (coin_id, type, amount, price, timestamp) VALUES (?, 'sell', ?, ?, ?)")
                ->execute([$coin['id'], $sellAmount, $price, time()]);
            logPortfolio("VENTE {$coin['id']} : $sellAmount parts à $price €, cash=$cash");
        }
        // Achat si score > seuil achat et cash dispo
        elseif ($score > $buyThreshold && $cash >= $investmentPerTrade) {
            $buyAmount = $investmentPerTrade / $price;
            $cash -= $investmentPerTrade;
            if ($holding) {
                $newAmount = $holding['amount'] + $buyAmount;
                $newAvg = (($holding['amount'] * $holding['avg_buy_price']) + ($buyAmount * $price)) / $newAmount;
                $pdo->prepare("UPDATE holdings SET amount = ?, avg_buy_price = ? WHERE coin_id = ?")->execute([$newAmount, $newAvg, $coin['id']]);
            } else {
                $pdo->prepare("INSERT INTO holdings (coin_id, amount, avg_buy_price) VALUES (?, ?, ?)")->execute([$coin['id'], $buyAmount, $price]);
            }
            $pdo->prepare("INSERT INTO trades (coin_id, type, amount, price, timestamp) VALUES (?, 'buy', ?, ?, ?)")
                ->execute([$coin['id'], $buyAmount, $price, time()]);
            logPortfolio("ACHAT {$coin['id']} : $buyAmount parts à $price €");
        }
    }
    $pdo->prepare("UPDATE portfolio SET cash = ?, last_update = ?")->execute([$cash, time()]);
    
    // --- Apprentissage par renforcement : ajuster les seuils tous les 7 jours si performance améliorable ---
    $lastAdjust = $pdo->query("SELECT last_update FROM portfolio LIMIT 1")->fetchColumn();
    if ($lastAdjust < time() - 7*86400) {
        // Calculer la valeur totale du portefeuille (cash + holdings)
        $totalValue = $cash;
        $holdingsVal = $pdo->query("SELECT SUM(h.amount * c.current_price) FROM holdings h JOIN coins c ON h.coin_id = c.id")->fetchColumn();
        if ($holdingsVal) $totalValue += $holdingsVal;
        $initial = 1000000;
        $perf = ($totalValue - $initial) / $initial * 100;
        
        $newBuy = $buyThreshold;
        $newSell = $sellThreshold;
        if ($perf < 0) {
            $newBuy = max(40, $buyThreshold - 5);
            $newSell = min(50, $sellThreshold + 3);
        } elseif ($perf > 10) {
            $newBuy = min(85, $buyThreshold + 3);
            $newSell = max(20, $sellThreshold - 2);
        }
        $pdo->prepare("UPDATE rl_thresholds SET value = ? WHERE param = 'buy_score'")->execute([$newBuy]);
        $pdo->prepare("UPDATE rl_thresholds SET value = ? WHERE param = 'sell_score'")->execute([$newSell]);
        logPortfolio("RL ajustement seuils : buy $buyThreshold -> $newBuy, sell $sellThreshold -> $newSell, perf=$perf%");
        $pdo->prepare("UPDATE portfolio SET last_update = ?")->execute([time()]);
    }
    
    echo "Portefeuille mis à jour, cash: $cash €";
} catch (Exception $e) {
    logPortfolio("ERREUR portfolio_manager: " . $e->getMessage());
    echo "ERREUR";
}
?>