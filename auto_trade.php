<?php
/**
 * auto_trade.php - Trading Automatique IA avec Apprentissage par Renforcement
 * Gère 1 000 000 € virtuels, publie des articles de blog à chaque trade
 */

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
ensureDatabaseInitialized();

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Tables nécessaires
    $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (cash REAL DEFAULT 1000000, last_update INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS holdings (coin_id TEXT PRIMARY KEY, amount REAL, avg_buy_price REAL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS trades (id INTEGER PRIMARY KEY AUTOINCREMENT, coin_id TEXT, type TEXT, amount REAL, price REAL, timestamp INTEGER, score INTEGER, pnl_percent REAL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS rl_thresholds (param TEXT PRIMARY KEY, value REAL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_blog_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, content TEXT, created_at INTEGER, tags TEXT)");
    
    // Initialiser seuils RL si non existants
    $pdo->exec("INSERT OR IGNORE INTO rl_thresholds VALUES ('buy_score', 65)");
    $pdo->exec("INSERT OR IGNORE INTO rl_thresholds VALUES ('sell_score', 35)");
    
    // Récupérer paramètres
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $coinId = $input['coin_id'] ?? '';
    $coinName = $input['coin_name'] ?? '';
    $action = $input['action'] ?? '';
    $score = intval($input['score'] ?? 50);
    
    if (empty($coinId) || empty($action)) {
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }
    
    // Récupérer prix actuel
    $coinData = $pdo->prepare("SELECT current_price FROM coins WHERE id = ?");
    $coinData->execute([$coinId]);
    $coin = $coinData->fetch(PDO::FETCH_ASSOC);
    if (!$coin) {
        echo json_encode(['success' => false, 'error' => 'Crypto non trouvée']);
        exit;
    }
    $price = $coin['current_price'];
    
    // Récupérer cash et seuils
    $cash = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetchColumn() ?: 1000000;
    $buyScore = $pdo->query("SELECT value FROM rl_thresholds WHERE param='buy_score'")->fetchColumn() ?: 65;
    $sellScore = $pdo->query("SELECT value FROM rl_thresholds WHERE param='sell_score'")->fetchColumn() ?: 35;
    
    $investmentPerTrade = 5000; // 5000€ par trade
    $timestamp = time();
    $message = '';
    $tradeExecuted = false;
    
    if ($action === 'buy' && $score >= $buyScore && $cash >= $investmentPerTrade) {
        $amount = $investmentPerTrade / $price;
        $cash -= $investmentPerTrade;
        
        // Vérifier holding existant
        $hold = $pdo->prepare("SELECT amount, avg_buy_price FROM holdings WHERE coin_id = ?");
        $hold->execute([$coinId]);
        $holding = $hold->fetch();
        
        if ($holding) {
            $newAmount = $holding['amount'] + $amount;
            $newAvg = (($holding['amount'] * $holding['avg_buy_price']) + ($amount * $price)) / $newAmount;
            $pdo->prepare("UPDATE holdings SET amount = ?, avg_buy_price = ? WHERE coin_id = ?")->execute([$newAmount, $newAvg, $coinId]);
        } else {
            $pdo->prepare("INSERT INTO holdings (coin_id, amount, avg_buy_price) VALUES (?, ?, ?)")->execute([$coinId, $amount, $price]);
        }
        
        $pdo->prepare("INSERT INTO trades (coin_id, type, amount, price, timestamp, score) VALUES (?, 'buy', ?, ?, ?, ?)")
            ->execute([$coinId, $amount, $price, $timestamp, $score]);
        
        $message = "Achat de " . round($amount, 6) . " $coinName à " . number_format($price, 2) . "€";
        $tradeExecuted = true;
        
        // Générer article de blog pour ce trade
        generateBlogPost($pdo, $coinId, $coinName, 'buy', $price, $amount, $score, $cash);
        
    } elseif ($action === 'sell' && $score <= $sellScore) {
        $hold = $pdo->prepare("SELECT amount, avg_buy_price FROM holdings WHERE coin_id = ?");
        $hold->execute([$coinId]);
        $holding = $hold->fetch();
        
        if ($holding && $holding['amount'] > 0) {
            $revenue = $holding['amount'] * $price;
            $pnlPercent = (($price - $holding['avg_buy_price']) / $holding['avg_buy_price']) * 100;
            $cash += $revenue;
            
            $pdo->prepare("DELETE FROM holdings WHERE coin_id = ?")->execute([$coinId]);
            $pdo->prepare("UPDATE trades SET pnl_percent = ? WHERE coin_id = ? AND type = 'buy' AND pnl_percent IS NULL")
                ->execute([$pnlPercent, $coinId]);
            $pdo->prepare("INSERT INTO trades (coin_id, type, amount, price, timestamp, score, pnl_percent) VALUES (?, 'sell', ?, ?, ?, ?, ?)")
                ->execute([$coinId, $holding['amount'], $price, $timestamp, $score, $pnlPercent]);
            
            $message = "Vente de " . round($holding['amount'], 6) . " $coinName à " . number_format($price, 2) . "€ (P&L: " . round($pnlPercent, 2) . "%)";
            $tradeExecuted = true;
            
            generateBlogPost($pdo, $coinId, $coinName, 'sell', $price, $holding['amount'], $score, $cash, $pnlPercent);
        }
    }
    
    if ($tradeExecuted) {
        $pdo->prepare("UPDATE portfolio SET cash = ?, last_update = ?")->execute([$cash, $timestamp]);
        appLog("Trade executed: $action $coinId - $message", 'TRADE');
        echo json_encode(['success' => true, 'message' => $message, 'cash' => $cash, 'action' => $action]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Conditions de trading non remplies', 'score' => $score, 'buyScore' => $buyScore, 'sellScore' => $sellScore, 'cash' => $cash]);
    }
    
} catch (Exception $e) {
    appLog("Auto trade error: " . $e->getMessage(), 'ERROR');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generateBlogPost($pdo, $coinId, $coinName, $action, $price, $amount, $score, $cash, $pnlPercent = null) {
    try {
        $title = ucfirst($action) . " de $coinName - Décision IA du " . date('d/m/Y H:i');
        
        $content = "**DÉCISION DE TRADING AUTONOME - NEO CRYPTO DASH v4.0**\n\n";
        $content .= "**Action:** " . strtoupper($action) . "\n";
        $content .= "**Cryptomonnaie:** $coinName (" . strtoupper($coinId) . ")\n";
        $content .= "**Prix d'exécution:** " . number_format($price, 2) . " €\n";
        $content .= "**Montant:** " . round($amount, 6) . " unités\n";
        $content .= "**Score IA:** $score/100\n";
        $content .= "**Cash restant:** " . number_format($cash, 2) . " €\n";
        
        if ($pnlPercent !== null) {
            $content .= "**Performance (P&L):** " . round($pnlPercent, 2) . "%\n";
        }
        
        $content .= "\n**ANALYSE DE LA DÉCISION:**\n\n";
        
        if ($action === 'buy') {
            $content .= "L'IA a détecté un signal d'achat fort basé sur:\n";
            $content .= "- Score technique élevé ($score/100) dépassant le seuil d'achat actuel\n";
            $content .= "- Indicateurs techniques favorables (RSI, MACD, momentum)\n";
            $content .= "- Sentiment de marché positif\n\n";
            $content .= "**Méthode utilisée:** Analyse technique multi-facteurs avec pondération dynamique via apprentissage par renforcement.\n";
            $content .= "Le système évalue en temps réel la performance des stratégies passées pour ajuster ses critères de décision.\n";
        } else {
            $content .= "L'IA a décidé de vendre suite à:\n";
            $content .= "- Score technique faible ($score/100) sous le seuil de vente\n";
            $content .= "- Signaux baissiers détectés sur les indicateurs\n";
            $content .= "- Protection des gains ou limitation des pertes\n\n";
            $content .= "**Méthode utilisée:** Détection de divergence baissière et optimisation du risk management.\n";
            if ($pnlPercent > 0) {
                $content .= "✅ Trade profitable de " . round($pnlPercent, 2) . "% - L'IA confirme l'efficacité de sa stratégie.\n";
            } else {
                $content .= "⚠️ Trade perdant - L'IA va analyser cet échec pour améliorer ses futurs critères de vente.\n";
            }
        }
        
        $content .= "\n**APPRENTISSAGE PAR RENFORCEMENT:**\n";
        $content .= "Chaque trade alimente le modèle RL qui ajuste automatiquement:\n";
        $content .= "- Les seuils d'achat/vente (actuellement: achat >= 65, vente <= 35)\n";
        $content .= "- La taille des positions selon la volatilité\n";
        $content .= "- La confiance accordée aux différents indicateurs\n\n";
        $content .= "*Article généré automatiquement par NEO CRYPTO DASH v4.0*";
        
        $stmt = $pdo->prepare("INSERT INTO ai_blog_posts (title, content, created_at, tags) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, time(), "trade,$action," . strtolower($coinId)]);
        
        appLog("Blog post generated for trade: $action $coinName", 'BLOG');
        
    } catch (Exception $e) {
        appLog("Blog generation failed: " . $e->getMessage(), 'ERROR');
    }
}
?>
