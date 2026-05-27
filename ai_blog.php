<?php
/**
 * ai_blog.php
 * - Génère un article de blog expliquant les performances du portefeuille
 *   et les ajustements des prompts / seuils d'achat/vente.
 * - Appelé manuellement depuis un bouton dans l'interface ou via AJAX quotidien.
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('MISTRAL_API_KEYS', [
    '5qa H8Rake',
    'o3rG1z ytu',
    'vEzQM FruXkF'
]);

function callMistral($messages, $model='mistral-small-2603', $maxTokens=600) {
    $keys = MISTRAL_API_KEYS;
    foreach ($keys as $apiKey) {
        $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['model'=>$model,'messages'=>$messages,'temperature'=>0.4,'max_tokens'=>$maxTokens]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_blog_posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT,
        content TEXT,
        created_at INTEGER,
        tags TEXT
    )");
    
    $cash = $pdo->query("SELECT cash FROM portfolio LIMIT 1")->fetchColumn();
    $holdings = $pdo->query("SELECT COUNT(*) FROM holdings")->fetchColumn();
    $totalPortfolio = $cash;
    $sumHold = $pdo->query("SELECT SUM(h.amount * c.current_price) FROM holdings h JOIN coins c ON h.coin_id = c.id")->fetchColumn();
    if ($sumHold) $totalPortfolio += $sumHold;
    $perf = round(($totalPortfolio - 1000000) / 1000000 * 100, 2);
    
    $thresholds = $pdo->query("SELECT param, value FROM rl_thresholds")->fetchAll(PDO::FETCH_KEY_PAIR);
    $buyScore = $thresholds['buy_score'] ?? 65;
    $sellScore = $thresholds['sell_score'] ?? 35;
    
    $prompt = "Rédige un article de blog pour investisseurs crypto. Sujet : 'Performances du portefeuille NEO DASH et évolution des stratégies IA'. 
Portefeuille actuel : $totalPortfolio € (performance $perf% depuis l'origine). 
Seuils d'achat/vente actuels : achat si score >= $buyScore, vente si score <= $sellScore. 
Explique comment l'auto‑apprentissage par renforcement a ajusté ces seuils, et donne des conseils pédagogiques. 
Style engageant, 300-400 mots. Titre accrocheur.";
    
    $messages = [
        ['role' => 'system', 'content' => 'Tu es un blogueur financier spécialisé IA et crypto. Écris en français.'],
        ['role' => 'user', 'content' => $prompt]
    ];
    
    $article = callMistral($messages, 'mistral-small-2603', 700);
    if ($article) {
        $lines = explode("\n", $article);
        $title = trim($lines[0]);
        if (strlen($title) > 100) $title = substr($title, 0, 100);
        $stmt = $pdo->prepare("INSERT INTO ai_blog_posts (title, content, created_at, tags) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $article, time(), 'auto, rl, portfolio']);
        echo "Article publié : $title";
    } else {
        echo "Erreur génération blog";
    }
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage();
}
?>