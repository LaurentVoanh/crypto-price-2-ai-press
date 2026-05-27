<?php
/**
 * config.php
 * Configuration centralisée pour NEO CRYPTO DASH v3.0
 * Compatible Hostinger Mutualisé - Design Pro Futuriste
 * 
 * Fonctionnalités:
 * - Rotation intelligente des clés API Mistral
 * - 20 modèles optimisés par tâche
 * - Prompts enrichis (800-1200 mots)
 * - Logging complet
 * - Gestion d'erreurs robuste
 */

// Empêcher l'exécution directe
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

// ============================================================================
// CONFIGURATION GÉNÉRALE
// ============================================================================

define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('LOG_FILE', ROOT_DIR . '/logs/app.log');
define('ERROR_LOG', ROOT_DIR . '/logs/error.log');
define('API_LOG', ROOT_DIR . '/logs/api_usage.log');
define('CACHE_DIR', ROOT_DIR . '/cache');
define('DATA_DIR', ROOT_DIR . '/data');
define('EXPORTS_DIR', ROOT_DIR . '/exports');

// Créer les dossiers nécessaires avec permissions Hostinger-safe (0755)
$dirsToCreate = [CACHE_DIR, DATA_DIR, EXPORTS_DIR, ROOT_DIR . '/logs'];
foreach ($dirsToCreate as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================================================
// CLÉS API MISTRAL - À REMPLACER PAR VOS VRAIES CLÉS
// Format: Clés complètes du Mistral Free Tier Développeur
// Chaque clé offre 1 milliard de tokens/mois
// ============================================================================

define('DEFAULT_MISTRAL_API_KEYS', [
    // ⚠️ REMPLACEZ CES VALEURS PAR VOS VRAIES CLÉS API MISTRAL
    // Les clés actuelles sont des placeholders pour sécurité (repo public)
    'YOUR_FIRST_MISTRAL_API_KEY_COMPLETE_HERE',
    'YOUR_SECOND_MISTRAL_API_KEY_COMPLETE_HERE', 
    'YOUR_THIRD_MISTRAL_API_KEY_COMPLETE_HERE'
]);

// Endpoint API Mistral
define('MISTRAL_API_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');

// ============================================================================
// MODÈLES MISTRAL ET LEURS USAGES OPTIMAUX (20 MODÈLES)
// ============================================================================

define('MISTRAL_MODELS', [
    // 💻 Code & Développement
    'codestral-2508' => [
        'category' => 'code',
        'name' => 'Code Master Ultimate',
        'usage' => 'Auto-complétion code, FIM, syntaxes complexes',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.2
    ],
    'devstral-2512' => [
        'category' => 'dev_agent',
        'name' => 'Dev Agent Pro',
        'usage' => 'Architecture logicielle, DevOps, refactoring lourd',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.3
    ],
    'devstral-medium-2507' => [
        'category' => 'dev_agent',
        'name' => 'Dev Agent Medium',
        'usage' => 'Débogage quotidien, patterns complexes',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.3
    ],
    'devstral-small-2507' => [
        'category' => 'dev_agent',
        'name' => 'Dev Agent Light',
        'usage' => 'Tests unitaires, CI/CD, micro-tâches',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.2
    ],
    
    // 🧠 Flagships - Raisonnement & Haute Performance
    'mistral-large-2512' => [
        'category' => 'flagship',
        'name' => 'Mistral Brain Ultra',
        'usage' => 'Analyses globales, raisonnement complexe, function calling',
        'max_tokens' => 32000,
        'cost_tier' => 'high',
        'temperature_default' => 0.3
    ],
    'mistral-large-2411' => [
        'category' => 'flagship',
        'name' => 'Mistral Brain Legacy',
        'usage' => 'Workflows enterprise stables, contextes massifs',
        'max_tokens' => 32000,
        'cost_tier' => 'high',
        'temperature_default' => 0.3
    ],
    
    // ⚖️ Modèles Intermédiaires & Équilibrés
    'mistral-medium-2508' => [
        'category' => 'intermediate',
        'name' => 'Corporate Engine Pro',
        'usage' => 'Analyses individuelles, tâches administratives complexes',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.4
    ],
    'mistral-medium-2505' => [
        'category' => 'intermediate',
        'name' => 'Corporate Engine Standard',
        'usage' => 'RAG, synthèse documents, bases de connaissances',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.4
    ],
    
    // ⚡ Vitesse, Automatisation & Éco (Small)
    'mistral-small-2603' => [
        'category' => 'small',
        'name' => 'Fast Automate Turbo',
        'usage' => 'Classification, tagging, routage rapide, extraction masse',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.3
    ],
    'mistral-small-2506' => [
        'category' => 'small',
        'name' => 'Fast Automate Standard',
        'usage' => 'Scraping API, traitement flux, clustering',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.3
    ],
    
    // 🤖 Agents & Orchestration
    'magistral-medium-2509' => [
        'category' => 'agent',
        'name' => 'Agent Router Medium',
        'usage' => 'Orchestration multi-agents, décision autonome',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.5
    ],
    'magistral-small-2509' => [
        'category' => 'agent',
        'name' => 'Agent Router Small',
        'usage' => 'Routage rapide multi-agents, distribution prompts',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.5
    ],
    
    // 🎨 Créativité & Expérimentations
    'labs-mistral-small-creative' => [
        'category' => 'creative',
        'name' => 'Creative Writer',
        'usage' => 'Blog, storytelling, brainstorming, liberté stylistique',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.8
    ],
    
    // 👁️ Vision & Analyse Graphique (Multimodal)
    'pixtral-large-2411' => [
        'category' => 'vision',
        'name' => 'Vision Analyzer Max',
        'usage' => 'Analyse UI, diagrammes, plans, précision géométrique',
        'max_tokens' => 32000,
        'cost_tier' => 'high',
        'temperature_default' => 0.3
    ],
    'pixtral-12b-2409' => [
        'category' => 'vision',
        'name' => 'Vision Analyzer Light',
        'usage' => 'OCR, détection objets, sous-titrage images',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.3
    ],
    
    // 📱 Edge Computing (Local)
    'ministral-14b-2512' => [
        'category' => 'edge',
        'name' => 'Local Engine Heavy',
        'usage' => 'Processing local, low-memory, raisonnement compact',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.3
    ],
    'ministral-8b-2512' => [
        'category' => 'edge',
        'name' => 'Local Engine Medium',
        'usage' => 'Applications mobiles, embarqué, all-rounder',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.3
    ],
    'ministral-3b-2512' => [
        'category' => 'edge',
        'name' => 'Local Engine Micro',
        'usage' => 'Commande vocale, complétion basique, ultra-léger',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.2
    ],
    
    // 🎙️ Audio & Traitement Vocal
    'voxtral-small-2507' => [
        'category' => 'audio',
        'name' => 'Audio Core Small',
        'usage' => 'Analyse sémantique audio fine, intonations, contexte',
        'max_tokens' => 32000,
        'cost_tier' => 'medium',
        'temperature_default' => 0.3
    ],
    'voxtral-mini-2507' => [
        'category' => 'audio',
        'name' => 'Audio Core Mini',
        'usage' => 'Traitement flux audio rapide, commandes vocales',
        'max_tokens' => 32000,
        'cost_tier' => 'low',
        'temperature_default' => 0.2
    ]
]);

// ============================================================================
// MAPPING DES TÂCHES VERS LES MODÈLES OPTIMAUX
// ============================================================================

define('TASK_MODEL_MAPPING', [
    // Analyses principales
    'global_analysis' => 'mistral-large-2512',
    'individual_analysis' => 'mistral-medium-2508',
    'deep_analysis' => 'mistral-large-2512',
    
    // Blog & Contenu
    'blog_post' => 'labs-mistral-small-creative',
    'newsletter' => 'labs-mistral-small-creative',
    'social_media' => 'labs-mistral-small-creative',
    
    // Analyses spécialisées
    'sentiment_analysis' => 'mistral-small-2603',
    'risk_analysis' => 'mistral-medium-2508',
    'correlation_analysis' => 'mistral-medium-2505',
    'whale_detection' => 'mistral-small-2603',
    'arbitrage_opportunity' => 'mistral-small-2603',
    'macro_insights' => 'mistral-large-2512',
    'prediction_engine' => 'mistral-large-2512',
    'news_summary' => 'mistral-small-2506',
    'defi_analysis' => 'mistral-medium-2508',
    'nft_analysis' => 'mistral-small-2603',
    'tax_report' => 'mistral-medium-2505',
    'performance_review' => 'mistral-medium-2508',
    'technical_indicators' => 'mistral-small-2603',
    
    // Trading & Portfolio
    'trade_signal' => 'mistral-medium-2508',
    'portfolio_optimization' => 'mistral-large-2512',
    'rebalancing_advice' => 'mistral-medium-2508',
    
    // Code & Technique
    'code_generation' => 'devstral-2512',
    'code_review' => 'devstral-medium-2507',
    'data_extraction' => 'mistral-small-2603',
    'api_integration' => 'devstral-small-2507',
    
    // Alertes & Notifications
    'price_alert' => 'mistral-small-2603',
    'market_alert' => 'mistral-small-2603',
    'news_alert' => 'mistral-small-2506'
]);

// ============================================================================
// PARAMÈTRES DE ROTATION API
// ============================================================================

define('API_ROTATION_CONFIG', [
    'max_retries' => 3,
    'retry_delay_ms' => 1000,
    'blacklist_duration_seconds' => 300,
    'rate_limit_per_minute' => 60,
    'timeout_seconds' => 30,
    'user_agent' => 'NEOCryptoDash/3.0 (Hostinger; Production; Mistral-Free-Tier)',
    'fallback_model' => 'mistral-small-2603'
]);

// ============================================================================
// PARAMÈTRES DE TRADING VIRTUEL
// ============================================================================

define('TRADING_CONFIG', [
    'initial_capital' => 1000000,
    'investment_per_trade' => 5000,
    'max_position_size' => 50000,
    'max_positions' => 20,
    'stop_loss_percent' => 15,
    'take_profit_percent' => 25,
    'rebalance_threshold' => 10,
    'default_buy_score' => 65,
    'default_sell_score' => 35,
    'trailing_stop_enabled' => true,
    'dca_enabled' => true,
    'dca_levels' => 3
]);

// ============================================================================
// PARAMÈTRES D'ANALYSE TECHNIQUE
// ============================================================================

define('TECHNICAL_CONFIG', [
    'rsi_period' => 14,
    'macd_fast' => 12,
    'macd_slow' => 26,
    'macd_signal' => 9,
    'ema_short' => 12,
    'ema_long' => 26,
    'bollinger_period' => 20,
    'bollinger_std' => 2,
    'volatility_lookback' => 7,
    'trend_lookback' => 7,
    'score_trend_weight' => 0.35,
    'score_volatility_weight' => 0.20,
    'score_rsi_weight' => 0.25,
    'score_volume_weight' => 0.20
]);

// ============================================================================
// PARAMÈTRES DE CACHE ET PERFORMANCE
// ============================================================================

define('CACHE_CONFIG', [
    'coin_data_ttl' => 600,
    'analysis_ttl' => 3600,
    'global_analysis_ttl' => 7200,
    'portfolio_update_interval' => 3600,
    'max_historical_days' => 90,
    'sparkline_points' => 168,
    'batch_size' => 25
]);

// ============================================================================
// CONFIGURATION DE L'INTERFACE UTILISATEUR
// ============================================================================

define('UI_CONFIG', [
    'app_name' => 'NEO CRYPTO DASH',
    'app_version' => '3.0.0',
    'app_tagline' => 'IA Mistral · Analyses RL · Portefeuille virtuel 1M€',
    'theme_primary' => '#3b82f6',
    'theme_success' => '#10b981',
    'theme_danger' => '#ef4444',
    'theme_warning' => '#f59e0b',
    'theme_dark' => '#111827',
    'items_per_page' => 25,
    'enable_animations' => true,
    'enable_notifications' => true
]);

// ============================================================================
// FONCTIONS UTILITAIRES GLOBALES
// ============================================================================

/**
 * Logger centralisé compatible Hostinger
 * @param string $message Message à logger
 * @param string $level Niveau de log (INFO, WARNING, ERROR, CRITICAL)
 */
function appLog($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message" . PHP_EOL;
    
    if ($level === 'ERROR' || $level === 'CRITICAL') {
        file_put_contents(ERROR_LOG, $logLine, FILE_APPEND | LOCK_EX);
    } elseif ($level === 'API') {
        file_put_contents(API_LOG, $logLine, FILE_APPEND | LOCK_EX);
    } else {
        file_put_contents(LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Gestion d'erreur centralisée
 */
function handleError($errno, $errstr, $errfile, $errline) {
    appLog("PHP Error [$errno]: $errstr in $errfile on line $errline", 'ERROR');
    return false;
}

set_error_handler('handleError');

/**
 * Nettoyer les anciennes entrées de cache
 */
function cleanupCache() {
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $cutoff = time() - (CACHE_CONFIG['max_historical_days'] * 86400);
        $pdo->exec("DELETE FROM historical_snapshots WHERE snapshot_time < $cutoff");
        $pdo->exec("DELETE FROM global_analysis WHERE generated_at < $cutoff");
        $pdo->exec("DELETE FROM coin_analysis_history WHERE timestamp < $cutoff");
        $pdo->exec("DELETE FROM api_usage_logs WHERE timestamp < $cutoff");
        
        appLog("Cache cleanup completed - deleted entries older than " . CACHE_CONFIG['max_historical_days'] . " days");
    } catch (Exception $e) {
        appLog("Cache cleanup failed: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Vérifier et initialiser la base de données
 */
function ensureDatabaseInitialized() {
    if (!file_exists(DB_FILE)) {
        require_once ROOT_DIR . '/init_db.php';
        initializeDatabase();
    }
}

/**
 * Formater un nombre avec séparateurs français
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', ' ');
}

/**
 * Formater une grande valeur (K, M, Md, B)
 */
function formatLargeNumber($number) {
    if ($number >= 1e12) {
        return round($number / 1e12, 2) . ' B€';
    } elseif ($number >= 1e9) {
        return round($number / 1e9, 2) . ' Md€';
    } elseif ($number >= 1e6) {
        return round($number / 1e6, 2) . ' M€';
    } elseif ($number >= 1e3) {
        return round($number / 1e3, 2) . ' K€';
    }
    return round($number, 2) . '€';
}

/**
 * Obtenir le modèle optimal pour une tâche
 */
function getModelForTask($taskName) {
    return TASK_MODEL_MAPPING[$taskName] ?? API_ROTATION_CONFIG['fallback_model'];
}

/**
 * Calculer le score de confiance basé sur l'historique
 */
function calculateConfidenceScore($coinId, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT AVG(accuracy_score) as avg_accuracy, COUNT(*) as count 
                               FROM coin_analysis_history 
                               WHERE coin_id = ? AND accuracy_score IS NOT NULL 
                               LIMIT 20");
        $stmt->execute([$coinId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['count'] < 3) {
            return 50;
        }
        
        $weight = min(1, $result['count'] / 20);
        return round(($result['avg_accuracy'] * $weight) + (50 * (1 - $weight)));
    } catch (Exception $e) {
        return 50;
    }
}

// ============================================================================
// CHARGEMENT AUTOMATIQUE AU STARTUP
// ============================================================================

date_default_timezone_set('Europe/Paris');

appLog('═══════════════════════════════════════════════════════');
appLog('NEO CRYPTO DASH v' . UI_CONFIG['app_version'] . ' configuration loaded');
appLog('Timezone: Europe/Paris | PHP Version: ' . phpversion());
appLog('Database: ' . DB_FILE);
appLog('═══════════════════════════════════════════════════════');

?>
