<?php
/**
 * test.php - Page de test pour débogage
 * Permet de vérifier que la configuration fonctionne correctement
 */

// Configuration ultra-minimale
define('ROOT_DIR', dirname(__FILE__));
define('LOG_FILE', ROOT_DIR . '/logs/app.log');
define('ERROR_LOG', ROOT_DIR . '/logs/error.log');

// Créer logs si nécessaire
if (!is_dir(ROOT_DIR . '/logs')) {
    @mkdir(ROOT_DIR . '/logs', 0755, true);
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', ERROR_LOG);

echo "<!DOCTYPE html>\n<html><head><meta charset='UTF-8'><title>TEST DEBUG</title></head><body>\n";
echo "<h1>🔍 Test de Debug - NEO CRYPTO DASH</h1>\n";

$tests = [];

// Test 1: Écriture dans les logs
try {
    $testLog = @file_put_contents(ERROR_LOG, "[TEST] Log test at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND | LOCK_EX);
    $tests['Log writing'] = $testLog !== false ? '✅ PASS' : '❌ FAIL';
} catch (Exception $e) {
    $tests['Log writing'] = '❌ FAIL: ' . $e->getMessage();
}

// Test 2: Création dossier data
try {
    if (!is_dir(ROOT_DIR . '/data')) {
        @mkdir(ROOT_DIR . '/data', 0755, true);
    }
    $tests['Data directory'] = is_dir(ROOT_DIR . '/data') ? '✅ PASS' : '❌ FAIL';
} catch (Exception $e) {
    $tests['Data directory'] = '❌ FAIL: ' . $e->getMessage();
}

// Test 3: Création dossier cache
try {
    if (!is_dir(ROOT_DIR . '/cache')) {
        @mkdir(ROOT_DIR . '/cache', 0755, true);
    }
    $tests['Cache directory'] = is_dir(ROOT_DIR . '/cache') ? '✅ PASS' : '❌ FAIL';
} catch (Exception $e) {
    $tests['Cache directory'] = '❌ FAIL: ' . $e->getMessage();
}

// Test 4: Chargement config.php
try {
    require_once ROOT_DIR . '/config.php';
    $tests['config.php loading'] = '✅ PASS';
} catch (Exception $e) {
    $tests['config.php loading'] = '❌ FAIL: ' . $e->getMessage();
}

// Test 5: Vérification DB_FILE constant
if (defined('DB_FILE')) {
    $tests['DB_FILE constant'] = '✅ Defined: ' . DB_FILE;
} else {
    $tests['DB_FILE constant'] = '❌ FAIL: Not defined';
}

// Test 6: Création/connexion SQLite
try {
    if (!defined('DB_FILE')) {
        define('DB_FILE', ROOT_DIR . '/crypto_cache.db');
    }
    $pdo = new PDO("sqlite:" . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $tests['SQLite connection'] = '✅ PASS';
    
    // Vérifier permissions du fichier DB
    if (file_exists(DB_FILE)) {
        $tests['DB file exists'] = '✅ Yes (' . filesize(DB_FILE) . ' bytes)';
        $tests['DB file writable'] = is_writable(DB_FILE) ? '✅ Yes' : '❌ No';
    } else {
        $tests['DB file exists'] = '⚠️ Created on first use';
    }
} catch (Exception $e) {
    $tests['SQLite connection'] = '❌ FAIL: ' . $e->getMessage();
}

// Test 7: Vérification des clés API
if (defined('DEFAULT_MISTRAL_API_KEYS')) {
    $keys = DEFAULT_MISTRAL_API_KEYS;
    $validKeys = array_filter($keys, function($k) {
        return !empty($k) && strpos($k, 'YOUR_') === false && strpos($k, 'placeholder') === false;
    });
    $tests['Mistral API Keys'] = count($validKeys) . ' valid key(s) / ' . count($keys) . ' total';
    if (count($validKeys) === 0) {
        $tests['Mistral API Keys'] .= ' ⚠️ WARNING: No valid keys configured';
    }
} else {
    $tests['Mistral API Keys'] = '❌ FAIL: Constant not defined';
}

// Test 8: Fonction appLog
if (function_exists('appLog')) {
    appLog('Test log entry from test.php');
    $tests['appLog function'] = '✅ PASS';
} else {
    $tests['appLog function'] = '❌ FAIL: Function not found';
}

// Test 9: ensureDatabaseInitialized
if (function_exists('ensureDatabaseInitialized')) {
    try {
        ensureDatabaseInitialized();
        $tests['Database initialization'] = '✅ PASS';
    } catch (Exception $e) {
        $tests['Database initialization'] = '❌ FAIL: ' . $e->getMessage();
    }
} else {
    $tests['ensureDatabaseInitialized'] = '❌ FAIL: Function not found';
}

// Test 10: cURL disponible
if (function_exists('curl_init')) {
    $tests['cURL extension'] = '✅ Available';
} else {
    $tests['cURL extension'] = '❌ FAIL: Not installed';
}

// Affichage des résultats
echo "<h2>📊 Résultats des Tests</h2>\n";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>Test</th><th>Résultat</th></tr>\n";
foreach ($tests as $name => $result) {
    echo "<tr><td><strong>$name</strong></td><td>$result</td></tr>\n";
}
echo "</table>\n";

// Informations système
echo "<h2>ℹ️ Informations Système</h2>\n";
echo "<ul>\n";
echo "<li>PHP Version: " . phpversion() . "</li>\n";
echo "<li>Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</li>\n";
echo "<li>Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "</li>\n";
echo "<li>Script Path: " . __FILE__ . "</li>\n";
echo "<li>Root Dir: " . ROOT_DIR . "</li>\n";
echo "<li>Current User: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'Unknown') . "</li>\n";
echo "</ul>\n";

// Extensions PHP
echo "<h2>🔧 Extensions PHP chargées</h2>\n";
$extensions = get_loaded_extensions();
echo "<div style='max-height: 200px; overflow-y: auto; background: #f5f5f5; padding: 10px;'>\n";
echo implode(', ', $extensions);
echo "</div>\n";

// Liens utiles
echo "<h2>🔗 Liens Utiles</h2>\n";
echo "<ul>\n";
echo "<li><a href='index.php'>Retour à l'index</a></li>\n";
echo "<li><a href='update.php'>Lancer update.php</a></li>\n";
echo "<li><a href='init_db.php'>Initialiser la base de données</a></li>\n";
echo "</ul>\n";

// Contenu du error.log
echo "<h2>📄 Dernières erreurs (logs/error.log)</h2>\n";
if (file_exists(ERROR_LOG)) {
    $logContent = file_get_contents(ERROR_LOG);
    $lines = array_slice(array_reverse(explode("\n", $logContent)), 0, 20);
    echo "<pre style='background: #ffe0e0; padding: 10px; max-height: 300px; overflow-y: auto;'>\n";
    echo htmlspecialchars(implode("\n", $lines));
    echo "</pre>\n";
} else {
    echo "<p>Aucun fichier error.log trouvé</p>\n";
}

echo "</body></html>\n";
?>
