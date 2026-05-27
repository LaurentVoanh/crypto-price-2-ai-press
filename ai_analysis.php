<?php
/**
 * ai_analysis.php
 * Endpoint AJAX pour analyses IA individuelles et globales
 * Utilise MistralAPIRotator avec prompts enrichis
 * Compatible Hostinger Mutualisé
 */

// Gestion des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
ensureDatabaseInitialized();

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $type = $_POST['type'] ?? $_GET['type'] ?? '';
    
    if ($type === 'individual') {
        $coinId = $_POST['coin_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $symbol = $_POST['symbol'] ?? strtoupper($coinId);
        $price = floatval($_POST['price'] ?? 0);
        $change = floatval($_POST['change'] ?? 0);
        $rank = intval($_POST['rank'] ?? 0);
        $sparklineJson = $_POST['sparkline'] ?? '[]';
        $marketCap = floatval($_POST['market_cap'] ?? 0);
        $volume = floatval($_POST['volume'] ?? 0);
        
        if (empty($name)) {
            echo json_encode(['error' => 'Nom manquant']);
            exit;
        }
        
        $sparkline = json_decode($sparklineJson, true);
        
        // Calcul avancé des indicateurs techniques
        function calculateAdvancedIndicators($sparkline, $price, $change, $volume, $marketCap) {
            if (!is_array($sparkline) || count($sparkline) < 7) {
                return ['trend_pct' => $change, 'volatility' => 0, 'rsi' => 50, 'score' => 50, 'macd' => 0, 'signal' => 0];
            }
            
            $n = count($sparkline);
            $last = $sparkline[$n-1];
            $first = $sparkline[0];
            $trendPct = ($last - $first) / $first * 100;
            
            // Volatilité
            $returns = [];
            for ($i=1; $i<$n; $i++) {
                $returns[] = ($sparkline[$i] - $sparkline[$i-1]) / $sparkline[$i-1];
            }
            $meanReturn = array_sum($returns) / count($returns);
            $variance = array_sum(array_map(fn($r) => pow($r - $meanReturn, 2), $returns)) / count($returns);
            $volatility = sqrt($variance) * 100;
            
            // RSI sur 14 périodes
            $gains = $losses = [];
            $start = max(1, $n-14);
            for ($i=$start; $i<$n; $i++) {
                $diff = $sparkline[$i] - $sparkline[$i-1];
                if ($diff >= 0) { $gains[] = $diff; $losses[] = 0; }
                else { $gains[] = 0; $losses[] = -$diff; }
            }
            $avgGain = count($gains) > 0 ? array_sum($gains)/count($gains) : 0;
            $avgLoss = count($losses) > 0 ? array_sum($losses)/count($losses) : 0;
            $rs = ($avgLoss == 0) ? 100 : $avgGain / $avgLoss;
            $rsi = 100 - (100 / (1 + $rs));
            
            // MACD simple (12, 26, 9)
            $ema12 = $ema26 = $sparkline[0];
            for ($i=1; $i<$n; $i++) {
                $ema12 = $ema12 * (11/13) + $sparkline[$i] * (2/13);
                $ema26 = $ema26 * (25/27) + $sparkline[$i] * (2/27);
            }
            $macd = $ema12 - $ema26;
            $signal = $macd * 0.8;
            
            // Score composite
            $trendScore = min(100, max(0, 50 + $trendPct * 2));
            $volScore = $volatility > 5 ? 20 : ($volatility > 2 ? 50 : 80);
            $rsiScore = $rsi > 70 ? 20 : ($rsi < 30 ? 80 : 60);
            $momentumScore = $macd > $signal ? 70 : 30;
            
            $score = round($trendScore * 0.3 + $volScore * 0.15 + $rsiScore * 0.25 + $momentumScore * 0.3);
            
            return [
                'trend_pct' => round($trendPct, 2),
                'volatility' => round($volatility, 2),
                'rsi' => round($rsi, 1),
                'macd' => round($macd, 4),
                'signal' => round($signal, 4),
                'score' => $score
            ];
        }
        
        $indic = calculateAdvancedIndicators($sparkline, $price, $change, $volume, $marketCap);
        $trend = $indic['score'] >= 75 ? 'forte hausse' : 
                 ($indic['score'] >= 60 ? 'hausse' : 
                 ($indic['score'] >= 40 ? 'neutre' : 
                 ($indic['score'] >= 25 ? 'baisse' : 'forte baisse')));
        
        // Construction dynamique de l'analyse technique
        $techAnalysis = "";
        if ($indic['rsi'] < 30) $techAnalysis .= "- RSI en zone de survente (<30): opportunité d'achat potentielle\n";
        if ($indic['rsi'] > 70) $techAnalysis .= "- RSI en zone de surachat (>70): risque de correction\n";
        if ($indic['macd'] > $indic['signal']) $techAnalysis .= "- MACD au-dessus du signal: momentum haussier confirmé\n";
        if ($indic['macd'] < $indic['signal']) $techAnalysis .= "- MACD sous le signal: momentum baissier\n";
        if ($indic['volatility'] < 2) $techAnalysis .= "- Faible volatilité: environnement stable, bon pour accumulation\n";
        if ($indic['volatility'] > 5) $techAnalysis .= "- Forte volatilité: risque élevé, positionner avec prudence\n";
        
        $momentumText = $indic['macd'] > $indic['signal'] ? 'Haussier' : 'Baissier';
        
        // Prompt enrichi et détaillé pour décision précise
        $prompt = "Tu es un analyste financier crypto expert avec 15 ans d'expérience chez Goldman Sachs et Binance Capital. Ta mission est de fournir une recommandation d'investissement précise et argumentée.

CONTEXTE DE MARCHÉ ACTUEL:
- Cryptomonnaie: $name ($symbol)
- Rang Market Cap: #$rank
- Prix actuel: {$price}€
- Variation 24h: {$change}%
- Tendance 7 jours: $trend
- Indicateurs Techniques:
  * RSI (14): {$indic['rsi']}
  * MACD: {$indic['macd']} | Signal: {$indic['signal']}
  * Volatilité: {$indic['volatility']}%
  * Momentum: $momentumText

ANALYSE TECHNIQUE DÉTAILLÉE:
$techAnalysis

TA MISSION:
Fournir une recommandation claire parmi: ACHAT FORT, ACHAT, NEUTRE, VENTE, VENTE FORTE
Justifier en 2-3 phrases maximum avec des arguments techniques précis.
Inclure un niveau de confiance (élevé/moyen/faible).
Terminer par un conseil d'action concret.

FORMAT DE RÉPONSE ATTENDU:
[RECOMMANDATION] - [Justification technique concise]. Confiance: [niveau]. Action: [conseil pratique].";

        $messages = [
            ['role' => 'system', 'content' => 'Tu es un trader professionnel spécialisé en analyse technique crypto. Tu fournis des recommandations précises, factuelles et actionnables. Tu utilises un langage clair et direct. Tes analyses sont basées sur des données concrètes, pas sur des spéculations.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        // Utilisation de MistralAPIRotator avec modèle optimal
        require_once ROOT_DIR . '/MistralAPIRotator.php';
        $rotator = new MistralAPIRotator();
        $result = $rotator->call($messages, 'mistral-medium-2508', 400, 0.3);
        
        if ($result['success'] && !empty($result['content'])) {
            $advice = trim($result['content']);
            $tokensUsed = $result['usage']['total_tokens'] ?? 0;
            $modelUsed = $result['model'] ?? 'mistral-medium-2508';
        } else {
            $advice = "Neutre : analyse temporairement indisponible. Vérifiez vos clés API Mistral dans config.php.";
            $tokensUsed = 0;
            $modelUsed = 'fallback';
            appLog("Individual analysis failed for $name: " . ($result['error'] ?? 'Unknown error'), 'ERROR');
        }
        
        // Sauvegarde en base avec tous les détails
        $now = time();
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO individual_analysis 
            (coin_id, advice, trend, analysis_text, generated_at, score, sentiment_score, model_used, tokens_used, technical_summary) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $sentimentScore = $indic['score'];
        $technicalSummary = "RSI:{$indic['rsi']} | MACD:{$indic['macd']} | Vol:{$indic['volatility']}% | Trend:{$indic['trend_pct']}%";
        
        $stmt->execute([
            $coinId, 
            $advice, 
            $trend, 
            $advice, 
            $now, 
            $indic['score'],
            $sentimentScore,
            $modelUsed,
            $tokensUsed,
            $technicalSummary
        ]);
        
        echo json_encode([
            'success' => true,
            'advice' => $advice,
            'score' => $indic['score'],
            'trend' => $trend,
            'indicators' => $indic,
            'model' => $modelUsed,
            'tokens' => $tokensUsed
        ]);
        
    } elseif ($type === 'force_global') {
        // Forcer la génération de la revue de presse globale
        require_once ROOT_DIR . '/generate_global_press.php';
        $result = generateGlobalAnalysis($pdo);
        echo json_encode([
            'success' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Analyse globale générée',
            'error' => $result['error'] ?? null
        ]);
        
    } else {
        echo json_encode(['error' => "Type d'analyse non reconnu. Types valides: individual, force_global"]);
    }
    
} catch (Exception $e) {
    appLog("AI Analysis error: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'error' => 'Erreur serveur interne: ' . $e->getMessage(),
        'success' => false
    ]);
}
?>
