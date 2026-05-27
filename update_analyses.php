<?php
/**
 * update_analyses.php
 * - Calcule des indicateurs techniques (score 0-100, RSI, volatilité, tendance)
 * - Appelle Mistral pour un conseil professionnel
 * - Évalue les analyses de la veille (apprentissage par renforcement)
 * - Stocke l'historique des analyses
 * - Rotation de clés API, retries, respect du rate limiting
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('LOG_FILE', ROOT_DIR . '/analysis_log.txt');
define('MISTRAL_API_KEYS', [
    '5qaRTj Rake',
    'o3rG tu',
    'vEzQM kF'
]);

function logMessage($message) {
    file_put_contents(LOG_FILE, '['.date('Y-m-d H:i:s')."] $message\n", FILE_APPEND);
}

function callMistral($messages, $model = 'mistral-small-2603', $maxTokens = 250) {
    $keys = MISTRAL_API_KEYS;
    foreach ($keys as $apiKey) {
        for ($retry = 0; $retry < 3; $retry++) {
            $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => $maxTokens
            ]));
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['choices'][0]['message']['content'] ?? null;
            }
            logMessage("Échec clé " . substr($apiKey,0,8) . " HTTP $httpCode, tentative ".($retry+1));
            sleep(1);
        }
    }
    return null;
}

// Calcul des indicateurs techniques avancés depuis la sparkline
function computeIndicators($sparkline) {
    if (!is_array($sparkline) || count($sparkline) < 7) return null;
    $n = count($sparkline);
    $last = $sparkline[$n-1];
    $first = $sparkline[0];
    $trendPct = ($last - $first) / $first * 100;
    
    // Volatilité (écart-type des rendements journaliers)
    $returns = [];
    for ($i=1; $i<$n; $i++) {
        $returns[] = ($sparkline[$i] - $sparkline[$i-1]) / $sparkline[$i-1];
    }
    $meanReturn = array_sum($returns) / count($returns);
    $variance = array_sum(array_map(function($r) use ($meanReturn) {
        return pow($r - $meanReturn, 2);
    }, $returns)) / count($returns);
    $volatility = sqrt($variance) * 100;
    
    // RSI simplifié sur les 14 dernières périodes
    $gains = $losses = [];
    $start = max(1, $n - 14);
    for ($i = $start; $i < $n; $i++) {
        $diff = $sparkline[$i] - $sparkline[$i-1];
        if ($diff >= 0) {
            $gains[] = $diff;
            $losses[] = 0;
        } else {
            $gains[] = 0;
            $losses[] = -$diff;
        }
    }
    $avgGain = array_sum($gains) / count($gains);
    $avgLoss = array_sum($losses) / count($losses);
    $rs = ($avgLoss == 0) ? 100 : $avgGain / $avgLoss;
    $rsi = 100 - (100 / (1 + $rs));
    
    // Score technique maison (0-100)
    $trendScore = min(100, max(0, 50 + $trendPct * 2));
    $volScore = $volatility > 5 ? 20 : ($volatility > 2 ? 50 : 80);
    $rsiScore = $rsi > 70 ? 20 : ($rsi < 30 ? 80 : 60);
    $finalScore = round($trendScore * 0.4 + $volScore * 0.2 + $rsiScore * 0.4);
    
    return [
        'trend_pct' => round($trendPct, 2),
        'volatility' => round($volatility, 2),
        'rsi' => round($rsi, 1),
        'score' => $finalScore,
        'advice_text' => $finalScore >= 75 ? 'Achat fort' : ($finalScore >= 60 ? 'Achat' : ($finalScore >= 40 ? 'Neutre' : ($finalScore >= 25 ? 'Vente' : 'Vente forte')))
    ];
}

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Table d'historique des analyses (pour RL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS coin_analysis_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coin_id TEXT,
        timestamp INTEGER,
        price_at_analysis REAL,
        advice TEXT,
        score INTEGER,
        trend_pct REAL,
        volatility REAL,
        rsi REAL,
        predicted_change_pct REAL,
        actual_change_pct REAL,
        accuracy_score REAL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coin_time ON coin_analysis_history(coin_id, timestamp)");
    
    // Table pour les analyses individuelles (affichage rapide)
    $pdo->exec("CREATE TABLE IF NOT EXISTS individual_analysis (
        coin_id TEXT PRIMARY KEY,
        advice TEXT,
        trend TEXT,
        analysis_text TEXT,
        generated_at INTEGER
    )");
    
    // 1) Apprentissage par renforcement : évaluer les analyses de plus de 24h
    $evaluation = $pdo->query("SELECT id, coin_id, timestamp, price_at_analysis, advice, score 
                               FROM coin_analysis_history 
                               WHERE actual_change_pct IS NULL AND timestamp < ".(time() - 86400)." 
                               LIMIT 500");
    foreach ($evaluation as $old) {
        $current = $pdo->prepare("SELECT current_price FROM coins WHERE id = ?");
        $current->execute([$old['coin_id']]);
        $priceNow = $current->fetchColumn();
        if ($priceNow) {
            $change = ($priceNow - $old['price_at_analysis']) / $old['price_at_analysis'] * 100;
            $accuracy = null;
            $advice = $old['advice'];
            if ((stripos($advice, 'achat') !== false && $change > 2) || (stripos($advice, 'vente') !== false && $change < -2))
                $accuracy = 100;
            elseif ((stripos($advice, 'achat') !== false && $change < -2) || (stripos($advice, 'vente') !== false && $change > 2))
                $accuracy = 0;
            else $accuracy = 50;
            $upd = $pdo->prepare("UPDATE coin_analysis_history SET actual_change_pct = ?, accuracy_score = ? WHERE id = ?");
            $upd->execute([$change, $accuracy, $old['id']]);
            logMessage("RL évaluation: {$old['coin_id']} évolution $change%, précision $accuracy");
        }
    }
    
    // 2) Analyser les cryptos dont la dernière analyse > 1h
    $stmt = $pdo->query("SELECT id, name, symbol, current_price, price_change_percentage_24h, market_cap_rank, sparkline 
                         FROM coins ORDER BY market_cap_rank ASC LIMIT 100");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $now = time();
    $oneHourAgo = $now - 3600;
    
    foreach ($coins as $coin) {
        $lastAnalysis = $pdo->prepare("SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = ?");
        $lastAnalysis->execute([$coin['id']]);
        $lastTs = $lastAnalysis->fetchColumn();
        if ($lastTs && $lastTs > $oneHourAgo) {
            logMessage("{$coin['name']} analysé il y a moins d'1h, sauté");
            continue;
        }
        
        $sparkline = json_decode($coin['sparkline'], true);
        $indic = computeIndicators($sparkline);
        if (!$indic) continue;
        
        // Prompt professionnel pour conseil d'investissement
        $prompt = "Tu es un analyste financier spécialisé en cryptomonnaies. Voici les données pour {$coin['name']} (symbole {$coin['symbol']}) :
- Prix actuel : {$coin['current_price']} €
- Variation 24h : {$coin['price_change_percentage_24h']}%
- Tendance 7j (prix final vs initial) : {$indic['trend_pct']}%
- Volatilité 7j : {$indic['volatility']}%
- RSI approximé : {$indic['rsi']}
- Score technique (0-100) : {$indic['score']}

Donne un conseil d'investissement PRÉCIS sous la forme \"Achat fort / Achat / Neutre / Vente / Vente forte\" puis en une phrase (max 25 mots) justifie avec chiffres clés. Sois professionnel et factuel.";
        
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un trader crypto expérimenté. Réponds en français.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $advice = callMistral($messages, 'mistral-small-2603', 150);
        if (!$advice) $advice = $indic['advice_text']." : analyse technique automatique.";
        
        // Prédiction de variation sur 24h (basée sur score et tendance)
        $predictedChange = round($indic['trend_pct'] * 0.6 + ($indic['score']-50)/5, 2);
        
        // Sauvegarde dans l'historique
        $insert = $pdo->prepare("INSERT INTO coin_analysis_history 
            (coin_id, timestamp, price_at_analysis, advice, score, trend_pct, volatility, rsi, predicted_change_pct)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([
            $coin['id'], $now, $coin['current_price'], $advice, $indic['score'],
            $indic['trend_pct'], $indic['volatility'], $indic['rsi'], $predictedChange
        ]);
        
        // Mise à jour de la table individuelle pour affichage rapide
        $updInd = $pdo->prepare("INSERT OR REPLACE INTO individual_analysis (coin_id, advice, trend, analysis_text, generated_at) VALUES (?, ?, ?, ?, ?)");
        $updInd->execute([$coin['id'], $advice, $indic['advice_text'], $advice, $now]);
        
        logMessage("Analyse OK: {$coin['name']} -> score {$indic['score']} / conseil: $advice");
        usleep(400000); // 0.4s entre chaque crypto pour éviter rate limiting
    }
    
    echo "Analyses mises à jour avec RL.";
} catch (Exception $e) {
    logMessage("ERREUR FATALE update_analyses: " . $e->getMessage());
    echo "ERREUR";
}
?>