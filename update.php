<?php
/**
 * update.php
 * Récupère les 1000 cryptos via CoinGecko (cURL),
 * met à jour la table `coins` et ajoute un snapshot historique.
 * Appel AJAX toutes les 10 minutes depuis index.php (pas de cron).
 */

define('ROOT_DIR', dirname(__FILE__));
define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
define('LOG_FILE', ROOT_DIR . '/update_log.txt');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Création des tables si absentes
    $pdo->exec("CREATE TABLE IF NOT EXISTS coins (
        id TEXT PRIMARY KEY,
        symbol TEXT,
        name TEXT,
        image TEXT,
        current_price REAL,
        market_cap REAL,
        market_cap_rank INTEGER,
        price_change_percentage_24h REAL,
        total_volume REAL,
        circulating_supply REAL,
        sparkline TEXT,
        last_update INTEGER
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS historical_snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        coin_id TEXT,
        snapshot_time INTEGER,
        current_price REAL,
        market_cap REAL,
        market_cap_rank INTEGER,
        price_change_percentage_24h REAL,
        total_volume REAL,
        circulating_supply REAL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coin_time ON historical_snapshots(coin_id, snapshot_time)");
    
    // Récupération des données via cURL
    $url = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=eur&order=market_cap_desc&per_page=1000&page=1&sparkline=true";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (CryptoDashboard; Hostinger)');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || $response === false) {
        throw new Exception("cURL erreur HTTP $httpCode - $curlError");
    }
    
    $coinsData = json_decode($response, true);
    if (!is_array($coinsData) || empty($coinsData)) {
        throw new Exception("Données JSON invalides ou vides");
    }
    
    $now = time();
    $stmtCoin = $pdo->prepare("INSERT OR REPLACE INTO coins 
        (id, symbol, name, image, current_price, market_cap, market_cap_rank,
         price_change_percentage_24h, total_volume, circulating_supply, sparkline, last_update)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmtHist = $pdo->prepare("INSERT INTO historical_snapshots 
        (coin_id, snapshot_time, current_price, market_cap, market_cap_rank,
         price_change_percentage_24h, total_volume, circulating_supply)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    foreach ($coinsData as $coin) {
        $sparklineJson = json_encode($coin['sparkline_in_7d']['price'] ?? []);
        
        $stmtCoin->execute([
            $coin['id'],
            $coin['symbol'],
            $coin['name'],
            $coin['image'],
            $coin['current_price'] ?? 0,
            $coin['market_cap'] ?? 0,
            $coin['market_cap_rank'] ?? 0,
            $coin['price_change_percentage_24h'] ?? 0,
            $coin['total_volume'] ?? 0,
            $coin['circulating_supply'] ?? 0,
            $sparklineJson,
            $now
        ]);
        
        $stmtHist->execute([
            $coin['id'],
            $now,
            $coin['current_price'] ?? 0,
            $coin['market_cap'] ?? 0,
            $coin['market_cap_rank'] ?? 0,
            $coin['price_change_percentage_24h'] ?? 0,
            $coin['total_volume'] ?? 0,
            $coin['circulating_supply'] ?? 0
        ]);
        $count++;
    }
    
    // Nettoyage des vieux snapshots (plus de 60 jours)
    $old = $now - 60 * 86400;
    $deleted = $pdo->exec("DELETE FROM historical_snapshots WHERE snapshot_time < $old");
    
    // Sauvegarde de la date de dernière mise à jour
    file_put_contents(ROOT_DIR . '/last_update.txt', $now);
    logMessage("$count cryptos mises à jour, snapshots supprimés : $deleted");
    
    echo "OK";
    
} catch (Exception $e) {
    logMessage("ERREUR FATALE : " . $e->getMessage());
    echo "ERREUR";
}
?>