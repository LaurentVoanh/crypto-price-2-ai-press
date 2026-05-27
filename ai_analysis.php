<?php
/**
 * ai_analysis.php
 * Endpoint AJAX pour :
 * - type=individual : analyse immédiate d’une crypto (bouton "Forcer analyse")
 * - type=force_global : régénération forcée de l’analyse globale
 */

header('Content-Type: application/json; charset=utf-8');

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('MISTRAL_API_KEYS', [
    '5qa ake',
    'o3r tu',
    'vEzQM XkF'
]);

function callMistral($messages, $model='mistral-small-2603', $maxTokens=200) {
    $keys = MISTRAL_API_KEYS;
    foreach ($keys as $apiKey) {
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['model'=>$model,'messages'=>$messages,'temperature'=>0.3,'max_tokens'=>$maxTokens]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
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
    
    $type = $_POST['type'] ?? $_GET['type'] ?? '';
    
    if ($type === 'individual') {
        $coinId = $_POST['coin_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $change = floatval($_POST['change'] ?? 0);
        $rank = intval($_POST['rank'] ?? 0);
        $sparklineJson = $_POST['sparkline'] ?? '[]';
        
        if (empty($name)) {
            echo json_encode(['error' => 'Nom manquant']);
            exit;
        }
        
        $sparkline = json_decode($sparklineJson, true);
        // Calcul rapide des indicateurs (identique à update_analyses.php)
        function quickIndicators($sparkline) {
            if (!is_array($sparkline) || count($sparkline) < 7) return null;
            $n = count($sparkline);
            $last = $sparkline[$n-1];
            $first = $sparkline[0];
            $trendPct = ($last - $first) / $first * 100;
            $returns = [];
            for ($i=1; $i<$n; $i++) $returns[] = ($sparkline[$i] - $sparkline[$i-1]) / $sparkline[$i-1];
            $volatility = sqrt(array_sum(array_map(function($r) use($returns) {
                $mean = array_sum($returns)/count($returns);
                return pow($r - $mean, 2);
            }, $returns)) / count($returns)) * 100;
            $gains = $losses = [];
            $start = max(1, $n-14);
            for ($i=$start; $i<$n; $i++) {
                $diff = $sparkline[$i] - $sparkline[$i-1];
                if ($diff >= 0) { $gains[] = $diff; $losses[] = 0; }
                else { $gains[] = 0; $losses[] = -$diff; }
            }
            $avgGain = array_sum($gains)/count($gains);
            $avgLoss = array_sum($losses)/count($losses);
            $rs = ($avgLoss == 0) ? 100 : $avgGain / $avgLoss;
            $rsi = 100 - (100 / (1 + $rs));
            $trendScore = min(100, max(0, 50 + $trendPct * 2));
            $volScore = $volatility > 5 ? 20 : ($volatility > 2 ? 50 : 80);
            $rsiScore = $rsi > 70 ? 20 : ($rsi < 30 ? 80 : 60);
            $score = round($trendScore * 0.4 + $volScore * 0.2 + $rsiScore * 0.4);
            return ['trend_pct'=>$trendPct, 'volatility'=>$volatility, 'rsi'=>$rsi, 'score'=>$score];
        }
        $indic = quickIndicators($sparkline);
        $trend = $indic ? ($indic['score']>=75?'forte hausse':($indic['score']>=60?'hausse':($indic['score']>=40?'neutre':($indic['score']>=25?'baisse':'forte baisse')))) : 'neutre';
        
        $prompt = "Tu es un analyste financier. Donne un conseil d'investissement (Achat fort/Achat/Neutre/Vente/Vente forte) pour $name (rang $rank). Prix: {$price}€, var24h: {$change}%, tendance 7j: $trend. Une phrase concise (max 20 mots) en français. Commence par le conseil.";
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un trader crypto expérimenté. Sois direct et factuel.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        $advice = callMistral($messages, 'mistral-small-2603', 100);
        if (!$advice) $advice = "Neutre : analyse temporairement indisponible.";
        
        $now = time();
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO individual_analysis (coin_id, advice, trend, analysis_text, generated_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$coinId, $advice, $trend, $advice, $now]);
        
        echo json_encode(['advice' => $advice]);
        
    } elseif ($type === 'force_global') {
        // Forcer la génération de la revue de presse
        include_once 'generate_global_press.php';
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Type d\'analyse non reconnu']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur serveur interne']);
}
?>