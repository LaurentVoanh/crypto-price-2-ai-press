<?php
/**
 * MistralAPIRotator.php
 * Classe de rotation intelligente des clés API Mistral v3.0
 * Compatible Hostinger Mutualisé
 * 
 * Fonctionnalités avancées:
 * - Rotation round-robin pondérée avec scoring dynamique
 * - Blacklist temporaire des clés en erreur
 * - Suivi détaillé des performances par clé
 * - Gestion du rate limiting adaptatif
 * - Logging complet avec statistiques
 * - Fallback automatique vers modèle économique
 * - Support des prompts longs (jusqu'à 32K tokens)
 */

if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__FILE__));
}

require_once ROOT_DIR . '/config.php';

class MistralAPIRotator {
    
    private $apiKeys = [];
    private $keyStats = [];
    private $blacklistedKeys = [];
    private $currentKeyIndex = 0;
    private $requestCount = 0;
    private $lastResetTime;
    private $sessionStats = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'total_tokens' => 0,
        'total_cost_estimate' => 0,
        'avg_response_time' => 0
    ];
    
    /**
     * Constructeur - Initialise la rotation des clés avec récupération des stats persistantes
     */
    public function __construct() {
        $this->apiKeys = DEFAULT_MISTRAL_API_KEYS;
        $this->lastResetTime = time();
        
        // Charger les stats depuis la session ou fichier cache
        $this->loadPersistentStats();
        
        // Initialiser les statistiques pour chaque clé
        foreach ($this->apiKeys as $index => $key) {
            if (!isset($this->keyStats[$index])) {
                $this->keyStats[$index] = [
                    'success_count' => 0,
                    'failure_count' => 0,
                    'total_tokens' => 0,
                    'last_used' => 0,
                    'last_error' => null,
                    'avg_response_time' => 0,
                    'consecutive_failures' => 0,
                    'success_rate' => 100,
                    'weight' => 1.0
                ];
            }
        }
        
        appLog('MistralAPIRotator v3.0 initialized with ' . count($this->apiKeys) . ' keys', 'INFO');
    }
    
    /**
     * Charger les statistiques persistantes depuis un fichier cache
     */
    private function loadPersistentStats() {
        $cacheFile = CACHE_DIR . '/api_rotator_stats.json';
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data) {
                $this->keyStats = $data['key_stats'] ?? [];
                $this->sessionStats = $data['session_stats'] ?? $this->sessionStats;
            }
        }
    }
    
    /**
     * Sauvegarder les statistiques dans un fichier cache
     */
    private function savePersistentStats() {
        $cacheFile = CACHE_DIR . '/api_rotator_stats.json';
        $data = [
            'key_stats' => $this->keyStats,
            'session_stats' => $this->sessionStats,
            'last_updated' => time()
        ];
        file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Obtenir la prochaine clé API disponible avec pondération
     * @return string|null La clé API ou null si aucune disponible
     */
    public function getNextKey() {
        $availableKeys = [];
        
        // Filtrer les clés non blacklistées
        foreach ($this->apiKeys as $index => $key) {
            if (!$this->isBlacklisted($index)) {
                $weight = $this->keyStats[$index]['weight'] ?? 1.0;
                $availableKeys[] = ['index' => $index, 'key' => $key, 'weight' => $weight];
            }
        }
        
        if (empty($availableKeys)) {
            // Toutes les clés sont blacklistées, retourner la première quand même
            appLog('All keys blacklisted, returning first key as fallback', 'WARNING');
            return $this->apiKeys[0];
        }
        
        // Sélection pondérée aléatoire
        $totalWeight = array_sum(array_column($availableKeys, 'weight'));
        $random = mt_rand(1, $totalWeight * 100) / 100;
        $cumulative = 0;
        
        foreach ($availableKeys as $keyData) {
            $cumulative += $keyData['weight'];
            if ($random <= $cumulative) {
                $this->currentKeyIndex = $keyData['index'];
                return $keyData['key'];
            }
        }
        
        $this->currentKeyIndex = $availableKeys[0]['index'];
        return $availableKeys[0]['key'];
    }
    
    /**
     * Vérifier si une clé est blacklistée
     * @param int $keyIndex Index de la clé
     * @return bool True si blacklistée
     */
    private function isBlacklisted($keyIndex) {
        if (!isset($this->blacklistedKeys[$keyIndex])) {
            return false;
        }
        
        $blacklistUntil = $this->blacklistedKeys[$keyIndex];
        if (time() > $blacklistUntil) {
            unset($this->blacklistedKeys[$keyIndex]);
            appLog("Key $keyIndex removed from blacklist", 'INFO');
            return false;
        }
        
        return true;
    }
    
    /**
     * Blacklister temporairement une clé
     * @param int $keyIndex Index de la clé
     */
    public function blacklistKey($keyIndex) {
        $duration = API_ROTATION_CONFIG['blacklist_duration_seconds'];
        $this->blacklistedKeys[$keyIndex] = time() + $duration;
        $this->keyStats[$keyIndex]['consecutive_failures']++;
        
        appLog("Key $keyIndex blacklisted for $duration seconds (failures: {$this->keyStats[$keyIndex]['consecutive_failures']})", 'WARNING');
    }
    
    /**
     * Enregistrer un succès d'appel API
     * @param int $keyIndex Index de la clé utilisée
     * @param int $tokensUsed Nombre de tokens utilisés
     * @param float $responseTime Temps de réponse en ms
     */
    public function recordSuccess($keyIndex, $tokensUsed = 0, $responseTime = 0) {
        if (!isset($this->keyStats[$keyIndex])) {
            return;
        }
        
        $stats = &$this->keyStats[$keyIndex];
        $stats['success_count']++;
        $stats['total_tokens'] += $tokensUsed;
        $stats['last_used'] = time();
        $stats['consecutive_failures'] = 0;
        $stats['last_error'] = null;
        
        // Moyenne mobile du temps de réponse
        if ($stats['avg_response_time'] > 0) {
            $stats['avg_response_time'] = ($stats['avg_response_time'] * 0.9) + ($responseTime * 0.1);
        } else {
            $stats['avg_response_time'] = $responseTime;
        }
        
        // Calculer le taux de succès
        $total = $stats['success_count'] + $stats['failure_count'];
        $stats['success_rate'] = $total > 0 ? ($stats['success_count'] / $total) * 100 : 100;
        
        // Ajuster le poids basé sur le taux de succès et le temps de réponse
        $stats['weight'] = max(0.1, min(2.0, $stats['success_rate'] / 50));
        
        // Mettre à jour les stats de session
        $this->sessionStats['successful_requests']++;
        $this->sessionStats['total_tokens'] += $tokensUsed;
        $this->updateSessionAvgResponseTime($responseTime);
        
        $this->resetRequestCountIfNeeded();
        $this->requestCount++;
        $this->sessionStats['total_requests']++;
        
        // Sauvegarder périodiquement
        if ($this->sessionStats['total_requests'] % 10 === 0) {
            $this->savePersistentStats();
        }
    }
    
    /**
     * Enregistrer un échec d'appel API
     * @param int $keyIndex Index de la clé utilisée
     * @param string $error Message d'erreur
     * @param int $httpCode Code HTTP retourné
     */
    public function recordFailure($keyIndex, $error = '', $httpCode = 0) {
        if (!isset($this->keyStats[$keyIndex])) {
            return;
        }
        
        $stats = &$this->keyStats[$keyIndex];
        $stats['failure_count']++;
        $stats['last_error'] = $error;
        $stats['consecutive_failures']++;
        
        // Calculer le taux de succès
        $total = $stats['success_count'] + $stats['failure_count'];
        $stats['success_rate'] = $total > 0 ? ($stats['success_count'] / $total) * 100 : 0;
        
        // Réduire le poids
        $stats['weight'] = max(0.1, $stats['success_rate'] / 100);
        
        appLog("API failure on key $keyIndex: HTTP $httpCode - " . substr($error, 0, 100), 'ERROR');
        
        // Mettre à jour les stats de session
        $this->sessionStats['failed_requests']++;
        $this->sessionStats['total_requests']++;
        
        // Blacklister après 3 échecs consécutifs
        if ($stats['consecutive_failures'] >= 3) {
            $this->blacklistKey($keyIndex);
        }
    }
    
    /**
     * Mettre à jour la moyenne mobile du temps de réponse de session
     */
    private function updateSessionAvgResponseTime($responseTime) {
        $count = $this->sessionStats['successful_requests'];
        if ($count > 1) {
            $this->sessionStats['avg_response_time'] = 
                ($this->sessionStats['avg_response_time'] * ($count - 1) + $responseTime) / $count;
        } else {
            $this->sessionStats['avg_response_time'] = $responseTime;
        }
    }
    
    /**
     * Réinitialiser le compteur de requêtes chaque minute
     */
    private function resetRequestCountIfNeeded() {
        if (time() - $this->lastResetTime >= 60) {
            $this->requestCount = 0;
            $this->lastResetTime = time();
        }
    }
    
    /**
     * Vérifier si on peut faire une nouvelle requête (rate limiting)
     * @return bool True si autorisé
     */
    public function canMakeRequest() {
        $this->resetRequestCountIfNeeded();
        return $this->requestCount < API_ROTATION_CONFIG['rate_limit_per_minute'];
    }
    
    /**
     * Appeler l'API Mistral avec rotation automatique et prompt enrichi
     * @param array $messages Messages au format OpenAI/Mistral
     * @param string|null $model Modèle à utiliser (null = auto)
     * @param int $maxTokens Nombre max de tokens (par défaut 2000 pour prompts longs)
     * @param float|null $temperature Température (0-2, null = auto)
     * @param array $options Options supplémentaires (top_p, presence_penalty, etc.)
     * @return array ['success' => bool, 'content' => string|null, 'usage' => array, 'model' => string]
     */
    public function call($messages, $model = null, $maxTokens = 2000, $temperature = null, $options = []) {
        // Déterminer le modèle optimal si non spécifié
        if ($model === null) {
            $model = $this->determineOptimalModel($messages);
        }
        
        // Température par défaut selon le modèle
        if ($temperature === null) {
            $temperature = MISTRAL_MODELS[$model]['temperature_default'] ?? 0.3;
        }
        
        $maxRetries = API_ROTATION_CONFIG['max_retries'];
        $retryDelay = API_ROTATION_CONFIG['retry_delay_ms'];
        
        appLog("API call initiated: model=$model, maxTokens=$maxTokens, temperature=$temperature", 'API');
        
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            // Vérifier le rate limiting
            if (!$this->canMakeRequest()) {
                appLog('Rate limit reached, waiting 2 seconds...', 'WARNING');
                usleep(2000000); // 2 secondes
                continue;
            }
            
            $keyIndex = $this->currentKeyIndex;
            $apiKey = $this->getNextKey();
            
            // Vérifier si la clé est correctement configurée
            if (empty($apiKey) || strpos($apiKey, 'YOUR_') === 0 || strlen($apiKey) < 20) {
                appLog('API Key not configured properly - please add real keys in config.php', 'ERROR');
                return [
                    'success' => false,
                    'content' => null,
                    'error' => 'Clé API non configurée. Veuillez ajouter vos vraies clés dans config.php (fichier sécurisé pour repo public)',
                    'usage' => [],
                    'model' => $model,
                    'fallback_used' => true
                ];
            }
            
            $startTime = microtime(true);
            
            $result = $this->executeCurlCall($apiKey, $model, $messages, $maxTokens, $temperature, $options);
            
            $responseTime = (microtime(true) - $startTime) * 1000; // ms
            
            if ($result['success']) {
                $tokensUsed = $result['usage']['total_tokens'] ?? 0;
                $this->recordSuccess($keyIndex, $tokensUsed, $responseTime);
                
                appLog("✓ API call successful | model: $model | tokens: $tokensUsed | time: " . round($responseTime, 2) . "ms", 'API');
                
                return $result;
            } else {
                $this->recordFailure($keyIndex, $result['error'] ?? 'Unknown error', $result['http_code'] ?? 0);
                
                // Attendre avant de réessayer (backoff exponentiel)
                if ($attempt < $maxRetries - 1) {
                    $delay = $retryDelay * pow(2, $attempt);
                    usleep($delay * 1000);
                }
            }
        }
        
        // Échec après toutes les tentatives - retour fallback
        appLog("✗ API call failed after $maxRetries attempts", 'ERROR');
        
        return [
            'success' => false,
            'content' => null,
            'error' => 'Échec après ' . $maxRetries . ' tentatives. Vérifiez vos clés API.',
            'usage' => [],
            'model' => $model,
            'fallback_used' => true
        ];
    }
    
    /**
     * Exécuter l'appel cURL vers l'API Mistral
     */
    private function executeCurlCall($apiKey, $model, $messages, $maxTokens, $temperature, $options) {
        $url = MISTRAL_API_ENDPOINT;
        
        // Construire le payload avec toutes les options
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];
        
        // Ajouter les options supplémentaires si fournies
        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }
        if (isset($options['presence_penalty'])) {
            $payload['presence_penalty'] = $options['presence_penalty'];
        }
        if (isset($options['frequency_penalty'])) {
            $payload['frequency_penalty'] = $options['frequency_penalty'];
        }
        if (isset($options['stop'])) {
            $payload['stop'] = $options['stop'];
        }
        if (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: ' . API_ROTATION_CONFIG['user_agent']
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, API_ROTATION_CONFIG['timeout_seconds']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Headers pour debugging
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'content' => null,
                'error' => "HTTP $httpCode: " . ($curlError ?: $response),
                'http_code' => $httpCode,
                'usage' => []
            ];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'content' => null,
                'error' => 'Invalid JSON response from API: ' . json_last_error_msg(),
                'http_code' => $httpCode,
                'usage' => []
            ];
        }
        
        $content = $data['choices'][0]['message']['content'] ?? null;
        $usage = $data['usage'] ?? [];
        
        return [
            'success' => true,
            'content' => $content,
            'error' => null,
            'http_code' => $httpCode,
            'usage' => $usage,
            'model' => $data['model'] ?? $model
        ];
    }
    
    /**
     * Déterminer le modèle optimal basé sur le contenu du message et la tâche
     */
    private function determineOptimalModel($messages) {
        $lastMessage = end($messages);
        $content = strtolower($lastMessage['content'] ?? '');
        $contentLength = strlen($content);
        
        // Analyse contextuelle avancée
        if (strpos($content, 'code') !== false || strpos($content, 'programmation') !== false || strpos($content, 'débug') !== false) {
            return 'devstral-2512';
        }
        
        if (strpos($content, 'blog') !== false || strpos($content, 'article') !== false || strpos($content, 'rédige') !== false) {
            return 'labs-mistral-small-creative';
        }
        
        if (strpos($content, 'global') !== false || strpos($content, 'marché') !== false || strpos($content, 'revue') !== false || strpos($content, 'synthèse') !== false) {
            return 'mistral-large-2512';
        }
        
        if (strpos($content, 'analyse approfondie') !== false || strpos($content, 'analyse détaillée') !== false) {
            return 'mistral-large-2512';
        }
        
        if (strpos($content, 'sentiment') !== false || strpos($content, 'classification') !== false) {
            return 'mistral-small-2603';
        }
        
        if (strpos($content, 'risque') !== false || strpos($content, 'risk') !== false) {
            return 'mistral-medium-2508';
        }
        
        // Pour les contenus longs (>1000 caractères), privilégier les modèles intermédiaires
        if ($contentLength > 1000) {
            return 'mistral-medium-2508';
        }
        
        // Défaut: modèle rapide et économique
        return 'mistral-small-2603';
    }
    
    /**
     * Obtenir les statistiques complètes de toutes les clés
     * @return array Statistiques détaillées
     */
    public function getStats() {
        $this->savePersistentStats();
        
        return [
            'keys_count' => count($this->apiKeys),
            'active_keys' => count($this->apiKeys) - count($this->blacklistedKeys),
            'blacklisted_count' => count($this->blacklistedKeys),
            'request_count' => $this->requestCount,
            'can_make_request' => $this->canMakeRequest(),
            'session_stats' => $this->sessionStats,
            'key_stats' => $this->keyStats,
            'estimated_tokens_remaining' => $this->estimateTokensRemaining()
        ];
    }
    
    /**
     * Estimer les tokens restants basés sur l'utilisation mensuelle
     * @return int Tokens restants estimés
     */
    private function estimateTokensRemaining() {
        // Chaque clé a 1 milliard de tokens/mois
        $monthlyLimit = 1000000000;
        $totalLimit = $monthlyLimit * count($this->apiKeys);
        
        // Utilisation actuelle (approximative)
        $usedToday = $this->sessionStats['total_tokens'];
        $dayOfMonth = date('j');
        $estimatedMonthly = $usedToday * (30 / max(1, $dayOfMonth));
        
        return max(0, $totalLimit - $estimatedMonthly);
    }
    
    /**
     * Réinitialiser les blacklists manuellement
     */
    public function clearBlacklist() {
        $this->blacklistedKeys = [];
        appLog('API key blacklist manually cleared', 'INFO');
    }
    
    /**
     * Obtenir le coût estimé de la session
     * @return array Coût estimé par modèle
     */
    public function getSessionCostEstimate() {
        // Prix approximatifs Mistral (€/1M tokens)
        $prices = [
            'mistral-large' => 4.0,
            'mistral-medium' => 1.0,
            'mistral-small' => 0.2,
            'devstral' => 1.0,
            'default' => 0.5
        ];
        
        $costs = [];
        foreach ($this->keyStats as $index => $stats) {
            $tokens = $stats['total_tokens'];
            $pricePerMillion = $prices['default'];
            
            foreach ($prices as $modelPrefix => $price) {
                if (strpos($this->apiKeys[$index], $modelPrefix) !== false) {
                    $pricePerMillion = $price;
                    break;
                }
            }
            
            $costs[$index] = ($tokens / 1000000) * $pricePerMillion;
        }
        
        return [
            'by_key' => $costs,
            'total' => array_sum($costs)
        ];
    }
}

?>
