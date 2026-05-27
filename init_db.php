<?php
/**
 * init_db.php
 * Initialisation complète de la base de données SQLite
 * Crée toutes les tables nécessaires pour NEO CRYPTO DASH
 * Compatible Hostinger Mutualisé
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

require_once ROOT_DIR . '/config.php';

/**
 * Initialiser la base de données avec toutes les tables
 * @return PDO Instance de connexion
 */
function initializeDatabase() {
    try {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        appLog('Starting database initialization...');
        
        // Table: coins (données CoinGecko)
        $pdo->exec("CREATE TABLE IF NOT EXISTS coins (
            id TEXT PRIMARY KEY,
            symbol TEXT NOT NULL,
            name TEXT NOT NULL,
            image TEXT,
            current_price REAL DEFAULT 0,
            market_cap REAL DEFAULT 0,
            market_cap_rank INTEGER DEFAULT 0,
            price_change_percentage_24h REAL DEFAULT 0,
            total_volume REAL DEFAULT 0,
            circulating_supply REAL DEFAULT 0,
            sparkline TEXT DEFAULT '[]',
            last_update INTEGER DEFAULT 0,
            ath REAL DEFAULT 0,
            atl REAL DEFAULT 0,
            high_24h REAL DEFAULT 0,
            low_24h REAL DEFAULT 0,
            price_change_24h REAL DEFAULT 0,
            market_cap_change_24h REAL DEFAULT 0,
            fully_diluted_valuation REAL DEFAULT 0,
            sentiment_votes_up_percentage REAL DEFAULT 0,
            sentiment_votes_down_percentage REAL DEFAULT 0,
            watchlist_portfolio_users INTEGER DEFAULT 0,
            market_cap_change_percentage_24h REAL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coins_rank ON coins(market_cap_rank)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coins_symbol ON coins(symbol)");
        
        // Table: historical_snapshots (historique des prix)
        $pdo->exec("CREATE TABLE IF NOT EXISTS historical_snapshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coin_id TEXT NOT NULL,
            snapshot_time INTEGER NOT NULL,
            current_price REAL DEFAULT 0,
            market_cap REAL DEFAULT 0,
            market_cap_rank INTEGER DEFAULT 0,
            price_change_percentage_24h REAL DEFAULT 0,
            total_volume REAL DEFAULT 0,
            circulating_supply REAL DEFAULT 0,
            volume_market_cap_ratio REAL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coin_time ON historical_snapshots(coin_id, snapshot_time)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_snapshot_time ON historical_snapshots(snapshot_time)");
        
        // Table: coin_analysis_history (historique des analyses IA avec RL)
        $pdo->exec("CREATE TABLE IF NOT EXISTS coin_analysis_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coin_id TEXT NOT NULL,
            timestamp INTEGER NOT NULL,
            price_at_analysis REAL DEFAULT 0,
            advice TEXT,
            score INTEGER DEFAULT 50,
            trend_pct REAL DEFAULT 0,
            volatility REAL DEFAULT 0,
            rsi REAL DEFAULT 0,
            predicted_change_pct REAL DEFAULT 0,
            actual_change_pct REAL DEFAULT 0,
            accuracy_score REAL DEFAULT 0,
            model_used TEXT DEFAULT 'mistral-small-2603',
            tokens_used INTEGER DEFAULT 0,
            prompt_hash TEXT,
            confidence_level REAL DEFAULT 0,
            risk_assessment TEXT,
            support_levels TEXT DEFAULT '[]',
            resistance_levels TEXT DEFAULT '[]',
            key_insights TEXT
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analysis_coin_time ON coin_analysis_history(coin_id, timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analysis_accuracy ON coin_analysis_history(accuracy_score)");
        
        // Table: individual_analysis (analyses rapides pour affichage)
        $pdo->exec("CREATE TABLE IF NOT EXISTS individual_analysis (
            coin_id TEXT PRIMARY KEY,
            advice TEXT,
            trend TEXT,
            analysis_text TEXT,
            generated_at INTEGER DEFAULT 0,
            score INTEGER DEFAULT 50,
            model_used TEXT DEFAULT 'mistral-medium-2508',
            tokens_used INTEGER DEFAULT 0,
            sentiment_score REAL DEFAULT 0,
            buy_signals INTEGER DEFAULT 0,
            sell_signals INTEGER DEFAULT 0,
            neutral_signals INTEGER DEFAULT 0,
            technical_summary TEXT,
            fundamental_summary TEXT
        )");
        
        // Table: global_analysis (revues de presse globales)
        $pdo->exec("CREATE TABLE IF NOT EXISTS global_analysis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            analysis_text TEXT,
            global_advice TEXT,
            market_summary TEXT,
            generated_at INTEGER DEFAULT 0,
            model_used TEXT DEFAULT 'mistral-large-2512',
            tokens_used INTEGER DEFAULT 0,
            market_sentiment TEXT DEFAULT 'neutral',
            fear_greed_index INTEGER DEFAULT 50,
            top_opportunities TEXT DEFAULT '[]',
            top_risks TEXT DEFAULT '[]',
            sector_performance TEXT DEFAULT '{}',
            macro_factors TEXT,
            regulatory_news TEXT,
            whale_activity TEXT,
            defi_tvl_change REAL DEFAULT 0,
            btc_dominance REAL DEFAULT 0,
            eth_dominance REAL DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_global_time ON global_analysis(generated_at)");
        
        // Table: portfolio (gestion du portefeuille virtuel)
        $pdo->exec("CREATE TABLE IF NOT EXISTS portfolio (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cash REAL DEFAULT 1000000,
            initial_capital REAL DEFAULT 1000000,
            last_update INTEGER DEFAULT 0,
            total_trades INTEGER DEFAULT 0,
            winning_trades INTEGER DEFAULT 0,
            losing_trades INTEGER DEFAULT 0,
            best_trade REAL DEFAULT 0,
            worst_trade REAL DEFAULT 0,
            win_rate REAL DEFAULT 0,
            avg_win REAL DEFAULT 0,
            avg_loss REAL DEFAULT 0,
            profit_factor REAL DEFAULT 0,
            sharpe_ratio REAL DEFAULT 0,
            max_drawdown REAL DEFAULT 0,
            strategy_name TEXT DEFAULT 'IA Momentum'
        )");
        
        // Table: holdings (positions actuelles)
        $pdo->exec("CREATE TABLE IF NOT EXISTS holdings (
            coin_id TEXT PRIMARY KEY,
            amount REAL DEFAULT 0,
            avg_buy_price REAL DEFAULT 0,
            first_purchase INTEGER DEFAULT 0,
            last_purchase INTEGER DEFAULT 0,
            total_invested REAL DEFAULT 0,
            realized_pnl REAL DEFAULT 0,
            unrealized_pnl REAL DEFAULT 0,
            position_size_percent REAL DEFAULT 0,
            stop_loss_price REAL DEFAULT 0,
            take_profit_price REAL DEFAULT 0,
            notes TEXT
        )");
        
        // Table: trades (historique des transactions)
        $pdo->exec("CREATE TABLE IF NOT EXISTS trades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coin_id TEXT NOT NULL,
            type TEXT NOT NULL CHECK(type IN ('buy', 'sell')),
            amount REAL DEFAULT 0,
            price REAL DEFAULT 0,
            total_value REAL DEFAULT 0,
            fee REAL DEFAULT 0,
            timestamp INTEGER DEFAULT 0,
            reason TEXT,
            score_at_trade INTEGER DEFAULT 50,
            pnl_realized REAL DEFAULT 0,
            pnl_percent REAL DEFAULT 0,
            holding_period_hours INTEGER DEFAULT 0,
            ai_confidence REAL DEFAULT 0,
            notes TEXT
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_trades_coin ON trades(coin_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_trades_time ON trades(timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_trades_type ON trades(type)");
        
        // Table: rl_thresholds (seuils d'apprentissage par renforcement)
        $pdo->exec("CREATE TABLE IF NOT EXISTS rl_thresholds (
            param TEXT PRIMARY KEY,
            value REAL DEFAULT 0,
            last_adjustment INTEGER DEFAULT 0,
            adjustment_reason TEXT,
            performance_before REAL DEFAULT 0,
            performance_after REAL DEFAULT 0
        )");
        
        // Insérer les seuils par défaut
        $pdo->exec("INSERT OR IGNORE INTO rl_thresholds (param, value, last_adjustment) 
                    VALUES ('buy_score', 65, " . time() . ")");
        $pdo->exec("INSERT OR IGNORE INTO rl_thresholds (param, value, last_adjustment) 
                    VALUES ('sell_score', 35, " . time() . ")");
        $pdo->exec("INSERT OR IGNORE INTO rl_thresholds (param, value, last_adjustment) 
                    VALUES ('position_size_multiplier', 1.0, " . time() . ")");
        $pdo->exec("INSERT OR IGNORE INTO rl_thresholds (param, value, last_adjustment) 
                    VALUES ('risk_tolerance', 0.5, " . time() . ")");
        
        // Table: ai_blog_posts (articles de blog générés par IA)
        $pdo->exec("CREATE TABLE IF NOT EXISTS ai_blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT,
            created_at INTEGER DEFAULT 0,
            tags TEXT DEFAULT '',
            model_used TEXT DEFAULT 'labs-mistral-small-creative',
            tokens_used INTEGER DEFAULT 0,
            views INTEGER DEFAULT 0,
            sentiment TEXT DEFAULT 'neutral',
            category TEXT DEFAULT 'general',
            featured_image TEXT,
            meta_description TEXT,
            reading_time_minutes INTEGER DEFAULT 5
        )");
        
        // Table: api_usage_logs (suivi de l'utilisation API)
        $pdo->exec("CREATE TABLE IF NOT EXISTS api_usage_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp INTEGER DEFAULT 0,
            model_used TEXT,
            tokens_prompt INTEGER DEFAULT 0,
            tokens_completion INTEGER DEFAULT 0,
            tokens_total INTEGER DEFAULT 0,
            cost_estimate REAL DEFAULT 0,
            endpoint TEXT,
            success INTEGER DEFAULT 1,
            error_message TEXT,
            response_time_ms INTEGER DEFAULT 0,
            key_index INTEGER DEFAULT 0
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_time ON api_usage_logs(timestamp)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_api_model ON api_usage_logs(model_used)");
        
        // Table: news_articles (articles de presse crypto)
        $pdo->exec("CREATE TABLE IF NOT EXISTS news_articles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT,
            summary TEXT,
            content TEXT,
            source TEXT,
            url TEXT,
            published_at INTEGER DEFAULT 0,
            sentiment_score REAL DEFAULT 0,
            relevance_score REAL DEFAULT 0,
            categories TEXT DEFAULT '[]',
            mentioned_coins TEXT DEFAULT '[]',
            impact_level TEXT DEFAULT 'medium',
            processed_by_ai INTEGER DEFAULT 0,
            ai_summary TEXT,
            created_at INTEGER DEFAULT 0
        )");
        
        // Table: alerts (alertes de prix et signaux)
        $pdo->exec("CREATE TABLE IF NOT EXISTS alerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coin_id TEXT,
            alert_type TEXT,
            condition TEXT,
            threshold_value REAL,
            current_value REAL,
            triggered INTEGER DEFAULT 0,
            triggered_at INTEGER DEFAULT 0,
            created_at INTEGER DEFAULT 0,
            message TEXT,
            is_active INTEGER DEFAULT 1
        )");
        
        // Table: correlation_matrix (corrélations entre cryptos)
        $pdo->exec("CREATE TABLE IF NOT EXISTS correlation_matrix (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coin_a TEXT NOT NULL,
            coin_b TEXT NOT NULL,
            correlation_7d REAL DEFAULT 0,
            correlation_30d REAL DEFAULT 0,
            last_updated INTEGER DEFAULT 0
        )");
        $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_correlation_pair ON correlation_matrix(coin_a, coin_b)");
        
        // Table: system_settings (paramètres système)
        $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT,
            setting_type TEXT DEFAULT 'string',
            description TEXT,
            last_modified INTEGER DEFAULT 0
        )");
        
        // Paramètres système par défaut
        $now = time();
        $pdo->exec("INSERT OR IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, last_modified) VALUES
            ('app_version', '2.0.0', 'string', 'Version de l''application', $now),
            ('last_coin_update', '0', 'integer', 'Dernière mise à jour des cryptos', $now),
            ('last_analysis_run', '0', 'integer', 'Dernière exécution des analyses', $now),
            ('last_portfolio_update', '0', 'integer', 'Dernière mise à jour du portefeuille', $now),
            ('enable_auto_trading', '1', 'boolean', 'Activer le trading automatique', $now),
            ('max_positions', '20', 'integer', 'Nombre maximum de positions', $now),
            ('analysis_frequency_hours', '1', 'integer', 'Fréquence des analyses en heures', $now)
        ");
        
        appLog('Database initialization completed successfully');
        
        return $pdo;
        
    } catch (PDOException $e) {
        appLog('Database initialization failed: ' . $e->getMessage(), 'CRITICAL');
        throw $e;
    }
}

// Exécution automatique si inclus directement
if (!isset($skipAutoInit)) {
    ensureDatabaseInitialized();
}

?>
