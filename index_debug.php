<?php
/**
 * index.php - NEO CRYPTO DASH v4.0 ULTIMATE
 * Dashboard Crypto IA Autonome - Trading Automatique par Renforcement
 * 
 * VERSION AVEC ERROR LOGGING ULTRA-COMPLET POUR HOSTINGER
 * 
 * Instructions pour déboguer l'erreur 500:
 * 1. Accédez à /workspace/logs/error.log via FTP/cPanel
 * 2. Cherchez les lignes avec [CRITICAL] ou [ERROR]
 * 3. Les logs montrent le fichier et la ligne exacte de l'erreur
 */

// ============================================================================
// ÉTAPE 1: CONFIGURATION MINIMALE AVANT TOUT LE RESTE
// ============================================================================

define('ROOT_DIR', dirname(__FILE__));
define('LOG_FILE', ROOT_DIR . '/logs/app.log');
define('ERROR_LOG', ROOT_DIR . '/logs/error.log');
define('DEBUG_MODE', true); // Mettre à false en production

// Créer dossier logs immédiatement
if (!is_dir(ROOT_DIR . '/logs')) {
    @mkdir(ROOT_DIR . '/logs', 0755, true);
}

// ============================================================================
// ÉTAPE 2: SYSTEME DE LOGGING ROBUSTE (NE PEUT PAS PLANTER)
// ============================================================================

/**
 * Log une erreur ou information dans les fichiers de log
 * Cette fonction est conçue pour ne jamais planter elle-même
 */
function debugLog($message, $level = 'INFO') {
    static $logHandle = null;
    
    try {
        $timestamp = date('Y-m-d H:i:s');
        
        // Obtenir l'appelant (fichier:ligne)
        $caller = 'unknown';
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (isset($trace[1])) {
            $caller = basename($trace[1]['file']) . ':' . $trace[1]['line'];
        }
        
        $logLine = "[$timestamp] [$level] [$caller] $message\n";
        
        // Déterminer le fichier de log
        $targetFile = ($level === 'ERROR' || $level === 'CRITICAL') ? $GLOBALS['ERROR_LOG'] : $GLOBALS['LOG_FILE'];
        
        // Écriture atomique avec verrouillage
        file_put_contents($targetFile, $logLine, FILE_APPEND | LOCK_EX);
        
    } catch (Exception $e) {
        // Si même le logging plante, on essaie d'écrire directement
        $fallback = ROOT_DIR . '/logs/fallback_error.log';
        @file_put_contents($fallback, "[FALLBACK] " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// ============================================================================
// ÉTAPE 3: HANDLERS D'ERREURS PERSONNALISÉS
// ============================================================================

// Handler pour erreurs fatales (exécute au shutdown)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $msg = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
        debugLog($msg, 'CRITICAL');
        
        // Afficher message utilisateur-friendly si headers pas encore envoyés
        if (!headers_sent()) {
            http_response_code(500);
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erreur 500</title></head><body>';
            echo '<h1>⚠️ Erreur Serveur (500)</h1>';
            echo '<p>Une erreur critique est survenue.</p>';
            echo '<p style="background:#ffe0e0;padding:10px;border-radius:5px;">';
            echo '<strong>Fichier:</strong> ' . htmlspecialchars($error['file']) . '<br>';
            echo '<strong>Ligne:</strong> ' . $error['line'] . '<br>';
            echo '<strong>Message:</strong> ' . htmlspecialchars($error['message']);
            echo '</p>';
            echo '<p>Voir <code>logs/error.log</code> pour plus de détails.</p>';
            echo '</body></html>';
        }
    }
});

// Handler pour exceptions non attrapées
set_exception_handler(function($e) {
    debugLog("UNCAUGHT EXCEPTION: " . $e->getMessage(), 'CRITICAL');
    debugLog("Location: " . $e->getFile() . ":" . $e->getLine(), 'CRITICAL');
    debugLog("Stack trace:\n" . $e->getTraceAsString(), 'CRITICAL');
    
    if (!headers_sent()) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Exception</title></head><body>';
        echo '<h1>🔥 Exception Non Gérée</h1>';
        echo '<pre style="background:#ffe0e0;padding:10px;overflow:auto;">';
        echo htmlspecialchars($e->getMessage() . "\n\n" . $e->getTraceAsString());
        echo '</pre>';
        echo '</body></html>';
    }
});

// Handler pour erreurs PHP standards
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorNames = [
        E_ERROR => 'ERROR', E_WARNING => 'WARNING', E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE', E_CORE_ERROR => 'CORE_ERROR', E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_USER_ERROR => 'USER_ERROR', E_USER_WARNING => 'USER_WARNING', E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT', E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR', 
        E_DEPRECATED => 'DEPRECATED', E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    $level = $errorNames[$errno] ?? 'UNKNOWN';
    
    debugLog("PHP $level: $errstr", 'ERROR');
    debugLog("Location: " . basename($errfile) . ":$errline", 'ERROR');
    
    return false; // Laisser PHP afficher aussi
});

// Configuration PHP pour erreurs
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOG);

// ============================================================================
// ÉTAPE 4: DÉMARRAGE DU LOGGING
// ============================================================================

debugLog('=== INDEX.PHP DEMARRAGE ===', 'INFO');
debugLog('PHP Version: ' . phpversion(), 'INFO');
debugLog('Root Dir: ' . ROOT_DIR, 'INFO');
debugLog('Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'), 'INFO');

// ============================================================================
// ÉTAPE 5: CHARGEMENT DE LA CONFIGURATION
// ============================================================================

try {
    debugLog('Chargement de config.php...', 'INFO');
    
    if (!file_exists(ROOT_DIR . '/config.php')) {
        throw new Exception('config.php introuvable dans ' . ROOT_DIR);
    }
    
    require_once ROOT_DIR . '/config.php';
    debugLog('config.php chargé avec succès', 'INFO');
    
    // Vérifier constantes critiques
    if (!defined('DB_FILE')) {
        throw new Exception('Constante DB_FILE non définie dans config.php');
    }
    debugLog('DB_FILE = ' . DB_FILE, 'INFO');
    
    if (!defined('DEFAULT_MISTRAL_API_KEYS')) {
        debugLog('ATTENTION: DEFAULT_MISTRAL_API_KEYS non défini', 'WARNING');
    } else {
        $keyCount = count(DEFAULT_MISTRAL_API_KEYS);
        debugLog('Nombre de clés API Mistral: ' . $keyCount, 'INFO');
    }
    
} catch (Exception $e) {
    debugLog('ECHEC chargement config: ' . $e->getMessage(), 'CRITICAL');
    die('<h1>Erreur Configuration</h1><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Voir logs/error.log</p>');
}

// ============================================================================
// ÉTAPE 6: INITIALISATION BASE DE DONNÉES
// ============================================================================

try {
    debugLog('Vérification initialisation database...', 'INFO');
    ensureDatabaseInitialized();
    debugLog('Database initialisée/vérifiée', 'INFO');
    
} catch (Exception $e) {
    debugLog('ECHEC init database: ' . $e->getMessage(), 'CRITICAL');
    debugLog('Stack: ' . $e->getTraceAsString(), 'CRITICAL');
    die('<h1>Erreur Database</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

// ============================================================================
// ÉTAPE 7: CONNEXION SQLITE ET RECUPERATION DONNÉES
// ============================================================================

$coins = [];
$analysesMap = [];
$globalAnalysis = null;
$portfolioStats = ['cash' => 1000000, 'holdings_value' => 0, 'total_value' => 1000000, 'performance' => 0, 'positions_count' => 0];
$buyScore = 65;
$sellScore = 35;
$recentTrades = [];

try {
    debugLog('Connexion à SQLite: ' . DB_FILE, 'INFO');
    
    // Vérifier que le fichier DB existe
    if (!file_exists(DB_FILE)) {
        throw new Exception('Fichier database inexistant: ' . DB_FILE);
    }
    
    // Vérifier permissions
    if (!is_readable(DB_FILE)) {
        throw new Exception('Fichier database non lisible: ' . DB_FILE);
    }
    
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    debugLog('Connexion SQLite réussie', 'INFO');
    
    // Récupérer cryptos
    debugLog('Requête SELECT coins...', 'INFO');
    $stmt = $pdo->query("SELECT id, symbol, name, image, current_price, market_cap, market_cap_rank, 
                         price_change_percentage_24h, total_volume, circulating_supply, sparkline,
                         ath, atl, high_24h, low_24h, market_cap_change_percentage_24h
                         FROM coins ORDER BY market_cap_rank ASC LIMIT 100");
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    debugLog('Coins récupérés: ' . count($coins), 'INFO');
    
    // Récupérer analyses
    debugLog('Requête SELECT individual_analysis...', 'INFO');
    $stmtAnalyses = $pdo->query("SELECT coin_id, advice, trend, score, generated_at, sentiment_score,
                                 buy_signals, sell_signals, neutral_signals, technical_summary
                                 FROM individual_analysis ORDER BY generated_at DESC");
    $analysesMap = [];
    while ($row = $stmtAnalyses->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($analysesMap[$row['coin_id']])) {
            $analysesMap[$row['coin_id']] = $row;
        }
    }
    debugLog('Analyses individuelles: ' . count($analysesMap), 'INFO');
    
    // Fraîcheur analyses
    $freshCount = 0;
    foreach ($coins as $coin) {
        $analysis = $analysesMap[$coin['id']] ?? null;
        if ($analysis && ($analysis['generated_at'] > time() - 3600)) {
            $freshCount++;
        }
    }
    
    // Analyse globale
    debugLog('Requête SELECT global_analysis...', 'INFO');
    $stmtGlobal = $pdo->query("SELECT analysis_text, global_advice, market_summary, market_sentiment,
                               fear_greed_index, top_opportunities, top_risks, generated_at, model_used
                               FROM global_analysis ORDER BY generated_at DESC LIMIT 1");
    $global = $stmtGlobal->fetch(PDO::FETCH_ASSOC);
    if ($global) {
        $globalAnalysis = $global;
        debugLog('Analyse globale trouvée du ' . date('d/m H:i', $global['generated_at']), 'INFO');
    } else {
        debugLog('Aucune analyse globale', 'WARNING');
    }
    
    // Stats portfolio
    debugLog('Calcul stats portfolio...', 'INFO');
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
    debugLog('Requête SELECT rl_thresholds...', 'INFO');
    $thresholds = $pdo->query("SELECT param, value FROM rl_thresholds")->fetchAll(PDO::FETCH_KEY_PAIR);
    $buyScore = $thresholds['buy_score'] ?? 65;
    $sellScore = $thresholds['sell_score'] ?? 35;
    
    // Derniers trades
    $recentTrades = $pdo->query("SELECT t.*, c.name, c.symbol FROM trades t 
                                 JOIN coins c ON t.coin_id = c.id 
                                 ORDER BY t.timestamp DESC LIMIT 10")
                        ->fetchAll(PDO::FETCH_ASSOC);
    debugLog('Recent trades: ' . count($recentTrades), 'INFO');
    
    debugLog('Toutes les données récupérées avec succès', 'INFO');
    
} catch (PDOException $e) {
    debugLog('PDO EXCEPTION: ' . $e->getMessage(), 'CRITICAL');
    debugLog('SQL State: ' . ($e->getCode() ?? 'N/A'), 'CRITICAL');
    debugLog('Stack: ' . $e->getTraceAsString(), 'CRITICAL');
    // Garder valeurs par défaut pour afficher page quand même
} catch (Exception $e) {
    debugLog('GENERAL EXCEPTION: ' . $e->getMessage(), 'CRITICAL');
    debugLog('Stack: ' . $e->getTraceAsString(), 'CRITICAL');
}

// ============================================================================
// ÉTAPE 8: FONCTION UTILITAIRE FORMATAGE
// ============================================================================

function formatLargeNumber($number) {
    if ($number >= 1e12) return round($number / 1e12, 2) . ' B€';
    if ($number >= 1e9) return round($number / 1e9, 2) . ' Md€';
    if ($number >= 1e6) return round($number / 1e6, 2) . ' M€';
    if ($number >= 1e3) return round($number / 1e3, 2) . ' K€';
    return round($number, 2) . '€';
}

debugLog('Page prête pour rendu HTML', 'INFO');
?>
