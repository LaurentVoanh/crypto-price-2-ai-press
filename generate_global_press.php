<?php
/**
 * generate_global_press.php
 * Génère une revue de presse IA complète avec Mistral Large
 * Utilise MistralAPIRotator pour rotation des clés et prompts enrichis
 * Compatible Hostinger Mutualisé
 */

define('ROOT_DIR', dirname(__FILE__));
require_once ROOT_DIR . '/config.php';
ensureDatabaseInitialized();

/**
 * Générer l'analyse globale du marché crypto
 * @param PDO $pdo Instance de base de données
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 */
function generateGlobalAnalysis($pdo) {
    try {
        // Récupérer les 10 cryptos avec les meilleurs scores récents
        $top = $pdo->query("SELECT c.id, c.name, c.symbol, c.current_price, c.price_change_percentage_24h, c.market_cap, 
                                   a.score, a.trend_pct, a.volatility, a.rsi, a.advice
                            FROM coin_analysis_history a
                            JOIN coins c ON a.coin_id = c.id
                            WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = c.id)
                            ORDER BY a.score DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($top)) {
            return [
                'success' => false,
                'message' => 'Aucune donnée disponible. Lancez d\'abord update.php pour récupérer les cryptos.',
                'error' => 'Pas de données coins/analyses'
            ];
        }
        
        // Récupérer aussi les 5 plus mauvais scores pour équilibre
        $bottom = $pdo->query("SELECT c.name, c.symbol, a.score, a.trend_pct, a.rsi
                               FROM coin_analysis_history a
                               JOIN coins c ON a.coin_id = c.id
                               WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = c.id)
                               ORDER BY a.score ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        // Statistiques globales
        $stats = $pdo->query("SELECT 
                                    AVG(a.score) as avg_score,
                                    COUNT(CASE WHEN a.score >= 70 THEN 1 END) as bullish_count,
                                    COUNT(CASE WHEN a.score <= 30 THEN 1 END) as bearish_count,
                                    COUNT(*) as total_analyzed
                              FROM coin_analysis_history a
                              WHERE a.timestamp = (SELECT MAX(timestamp) FROM coin_analysis_history WHERE coin_id = a.coin_id)")
                      ->fetch(PDO::FETCH_ASSOC);
        
        // Construire le résumé détaillé des données
        $marketSummary = "DONNÉES DE MARCHÉ ACTUELLES - Top 10 Cryptos par Score IA:\n\n";
        foreach ($top as $t) {
            $marketSummary .= "• {$t['name']} ({$t['symbol']}) - Prix: " . number_format($t['current_price'], 2, ',', ' ') . "€ | ";
            $marketSummary .= "Score IA: {$t['score']}/100 | ";
            $marketSummary .= "Tendance 7j: {$t['trend_pct']}% | ";
            $marketSummary .= "RSI: {$t['rsi']} | Volatilité: {$t['volatility']}%\n";
        }
        
        $marketSummary .= "\n⚠️ Zones de risque (scores bas):\n";
        foreach ($bottom as $b) {
            $marketSummary .= "• {$b['name']} ({$b['symbol']}) - Score: {$b['score']}/100 | Tendance: {$b['trend_pct']}% | RSI: {$b['rsi']}\n";
        }
        
        $marketSummary .= "\n📊 SENTIMENT GLOBAL DU MARCHÉ:\n";
        $marketSummary .= "- Score moyen: " . round($stats['avg_score'], 1) . "/100\n";
        $marketSummary .= "- Actifs haussiers (score ≥70): {$stats['bullish_count']}\n";
        $marketSummary .= "- Actifs baissiers (score ≤30): {$stats['bearish_count']}\n";
        $marketSummary .= "- Total analysés: {$stats['total_analyzed']}\n";
        
        // Prompt ultra-enrichi pour analyse professionnelle
        $prompt = "Tu es un analyste financier senior chez BlackRock avec 20 ans d'expérience dans les marchés crypto et traditionnels. Tu rédiges une revue de presse institutionnelle complète pour des investisseurs professionnels.

CONTEXTE ET DONNÉES DE MARCHÉ:
$marketSummary

TA MISSION:
Rédiger une analyse de marché approfondie (600-800 mots minimum) structurée comme suit:

1. **SYNTHÈSE EXÉCUTIVE** (2-3 phrases)
   - État général du marché crypto actuel
   - Sentiment dominant (haussier/baissier/neutre)
   - Niveau de confiance global

2. **ANALYSE TECHNIQUE DÉTAILLÉE**
   - Interprétation des scores IA moyens et distribution
   - Analyse des tendances dominantes (7 jours)
   - Niveaux de RSI moyens et zones de surachat/survente
   - Volatilité globale du marché

3. **OPPORTUNITÉS D'INVESTISSEMENT PRIORITAIRES**
   - Top 3-5 cryptos les plus attractives selon les données
   - Justification technique pour chaque opportunité
   - Niveaux d'entrée recommandés
   - Objectifs de prix à court/moyen terme

4. **RISQUES ET FACTEURS DE VIGILANCE**
   - Actifs présentant des signaux d'alerte (RSI élevé, volatilité excessive)
   - Risques systémiques potentiels
   - Facteurs macroéconomiques à surveiller

5. **RECOMMANDATION STRATÉGIQUE GLOBALE**
   - Allocation de capital recommandée (% crypto vs cash)
   - Stratégie dominante (accumulation, prise de profits, attente)
   - Horizon temporel conseillé

STYLE ET FORMAT:
- Ton professionnel, factuel, institutionnel
- Chiffres précis et arguments techniques
- Pas de langage marketing ou sensationnaliste
- Conclusion claire et actionnable
- Utiliser des termes financiers appropriés

TERMINE PAR UNE PHRASE DE CONSEIL ULTRA-PRÉCISE commençant par 'RECOMMANDATION:' qui résume l'action principale à mener.";

        $messages = [
            ['role' => 'system', 'content' => 'Tu es un directeur de recherche chez Goldman Sachs Crypto Division. Tu produces des analyses institutionnelles de haute qualité pour des family offices et fonds d\'investissement. Tes recommandations sont précises, argumentées et basées exclusivement sur des données. Style: Bloomberg Terminal meets institutional research.'],
            ['role' => 'user', 'content' => $prompt]
        ];
        
        // Utilisation de MistralAPIRotator avec mistral-large-2512
        require_once ROOT_DIR . '/MistralAPIRotator.php';
        $rotator = new MistralAPIRotator();
        $result = $rotator->call($messages, 'mistral-large-2512', 1500, 0.35);
        
        if ($result['success'] && !empty($result['content'])) {
            $globalAnalysis = trim($result['content']);
            $tokensUsed = $result['usage']['total_tokens'] ?? 0;
            $modelUsed = $result['model'] ?? 'mistral-large-2512';
            
            // Extraire la recommandation finale
            if (preg_match('/RECOMMANDATION:(.+?)(?:\.|$)/s', $globalAnalysis, $matches)) {
                $globalAdvice = trim($matches[1]);
            } else {
                // Fallback: prendre les 2 dernières phrases
                $sentences = preg_split('/(?<=[.!?])\s+/', $globalAnalysis);
                $globalAdvice = implode(' ', array_slice($sentences, -2));
            }
            
            // Calculer le sentiment global basé sur le score moyen
            $avgScore = floatval($stats['avg_score']);
            if ($avgScore >= 65) {
                $marketSentiment = 'bullish';
            } elseif ($avgScore <= 35) {
                $marketSentiment = 'bearish';
            } else {
                $marketSentiment = 'neutral';
            }
            
            // Fear & Greed Index estimé
            $fearGreedIndex = intval($avgScore);
            
            // Sauvegarde en base
            $now = time();
            $stmt = $pdo->prepare("INSERT INTO global_analysis 
                (analysis_text, global_advice, market_summary, generated_at, model_used, tokens_used, 
                 market_sentiment, fear_greed_index, top_opportunities, top_risks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            // Extraire top opportunités et risques
            $topOpp = json_encode(array_column(array_slice($top, 0, 5), 'symbol'));
            $topRisk = json_encode(array_column(array_slice($bottom, 0, 3), 'symbol'));
            
            $stmt->execute([
                $globalAnalysis,
                $globalAdvice,
                $marketSummary,
                $now,
                $modelUsed,
                $tokensUsed,
                $marketSentiment,
                $fearGreedIndex,
                $topOpp,
                $topRisk
            ]);
            
            // Nettoyer les anciennes analyses (garder 15 dernières)
            $pdo->exec("DELETE FROM global_analysis WHERE id NOT IN (SELECT id FROM global_analysis ORDER BY generated_at DESC LIMIT 15)");
            
            appLog("Global analysis generated successfully with $modelUsed ($tokensUsed tokens)", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Analyse globale générée avec succès',
                'analysis_length' => strlen($globalAnalysis),
                'tokens_used' => $tokensUsed,
                'model' => $modelUsed
            ];
            
        } else {
            $errorMsg = $result['error'] ?? 'Erreur inconnue';
            appLog("Global analysis failed: $errorMsg", 'ERROR');
            
            return [
                'success' => false,
                'message' => 'Échec de génération de l\'analyse globale',
                'error' => $errorMsg
            ];
        }
        
    } catch (Exception $e) {
        appLog("Generate global analysis error: " . $e->getMessage(), 'CRITICAL');
        return [
            'success' => false,
            'message' => 'Erreur critique lors de la génération',
            'error' => $e->getMessage()
        ];
    }
}

// Exécution automatique si appelé directement (pour test/debug)
if (!isset($skipAutoRun) && basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    try {
        $pdo = new PDO("sqlite:" . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $result = generateGlobalAnalysis($pdo);
        echo $result['success'] ? "OK: " . $result['message'] : "ERREUR: " . ($result['error'] ?? $result['message']);
    } catch (Exception $e) {
        echo "ERREUR CRITIQUE: " . $e->getMessage();
    }
}
?>
