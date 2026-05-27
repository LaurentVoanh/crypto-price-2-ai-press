<?php
/**
 * generate_global_press.php
 * Génère une longue revue de presse IA (Mistral Large) à partir
 * des 10 meilleurs scores du moment, et la stocke dans global_analysis.
 * Appelé automatiquement par AJAX depuis index.php.
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('MISTRAL_API_KEYS', [
    '5qaRT Rake',
    'o3rG1zvd hytu',
    'vEzQ uXkF'
]);

function callMistral($messages, $model='mistral-large-2512', $maxTokens=800) {
    $keys = MISTRAL_API_KEYS;
    foreach ($keys as $apiKey) {
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['model'=>$model,'messages'=>$messages,'temperature'=>0.4,'max_tokens'=>$maxTokens]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 35);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http === 200) {
            $data = json_decode($resp, true);
            return $data['choices'][0]['message']['content'] ?? null;
        }
    }
    return null;
}

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer les 10 cryptos avec les meilleurs scores récents
    $top = $pdo->query("SELECT c.name, c.symbol, a.score, a.trend_pct, a.volatility, a.rsi, a.advice
                        FROM coin_analysis_history a
                        JOIN coins c ON a.coin_id = c.id
                        WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = c.id)
                        ORDER BY a.score DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    $summary = "Analyse fraîche des 100 premières cryptos. Top 10 scores :\n";
    foreach ($top as $t) {
        $summary .= "- {$t['name']} ({$t['symbol']}) : score {$t['score']}, tendance {$t['trend_pct']}%, volatilité {$t['volatility']}%, RSI {$t['rsi']}\n";
    }
    $summary .= "Génère un long article de presse financière (400-500 mots) sur l'état du marché crypto actuel, les meilleures opportunités d'achat selon ces données, les risques (volatilité, RSI), et conclus par un conseil d'investissement global précis en une phrase.";
    
    $messages = [
        ['role' => 'system', 'content' => 'Tu es un analyste financier reconnu. Réponds en français, style professionnel et factuel. Termine par un conseil clair.'],
        ['role' => 'user', 'content' => $summary]
    ];
    
    $globalAnalysis = callMistral($messages, 'mistral-large-2512', 800);
    if (!$globalAnalysis) $globalAnalysis = "Analyse globale indisponible momentanément. Veuillez réessayer plus tard.";
    
    // Extraire la dernière phrase comme conseil global
    $sentences = preg_split('/(?<=[.!?])\s+/', $globalAnalysis);
    $globalAdvice = end($sentences);
    if (strlen($globalAdvice) < 10) $globalAdvice = $globalAnalysis;
    
    $now = time();
    $stmt = $pdo->prepare("INSERT INTO global_analysis (analysis_text, global_advice, market_summary, generated_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$globalAnalysis, $globalAdvice, $summary, $now]);
    
    // Nettoyer les anciennes (garder 20 dernières)
    $pdo->exec("DELETE FROM global_analysis WHERE id NOT IN (SELECT id FROM global_analysis ORDER BY generated_at DESC LIMIT 20)");
    
    echo "OK";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage();
}
?>