<?php
/**
 * Script de configuration de la base de données
 * Cosmos Group - Installation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Configuration de la base de données</h1>";
echo "<pre>";

// Charger les variables d'environnement
require_once 'includes/LoadEnv.php';
LoadEnv::load(__DIR__ . '/.env');

// Configuration
$host = getenv('COSMOS_DB_HOST') ?: 'localhost';
$port = (int)(getenv('COSMOS_DB_PORT') ?: 3306);
$dbname = getenv('COSMOS_DB_NAME') ?: 'emergen1_cosmos';
$user = getenv('COSMOS_DB_USER') ?: 'emergen1_cosmos';
$pass = getenv('COSMOS_DB_PASS') ?: 'F@mille123';
$charset = getenv('COSMOS_DB_CHARSET') ?: 'utf8mb4';

echo "=== CONFIGURATION ===\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Database: $dbname\n";
echo "User: $user\n";
echo "Password: " . str_repeat('*', strlen($pass)) . "\n\n";

// Étape 1: Connexion sans base de données spécifique
echo "=== ÉTAPE 1: CONNEXION AU SERVEUR MYSQL ===\n";
try {
    $dsn = "mysql:host=$host;port=$port;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✅ Connexion au serveur MySQL réussie\n\n";
} catch (PDOException $e) {
    echo "❌ ERREUR: Impossible de se connecter au serveur MySQL\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n\n";
    
    if ($e->getCode() == 1045) {
        echo "⚠️ Erreur d'authentification\n";
        echo "Vérifiez que l'utilisateur '$user' existe et que le mot de passe est correct.\n";
    } elseif ($e->getCode() == 2002) {
        echo "⚠️ Serveur MySQL inaccessible\n";
        echo "Vérifiez que MySQL est démarré et accessible sur $host:$port\n";
    }
    exit;
}

// Étape 2: Créer la base de données si elle n'existe pas
echo "=== ÉTAPE 2: CRÉATION DE LA BASE DE DONNÉES ===\n";
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
    echo "✅ Base de données '$dbname' créée ou déjà existante\n\n";
} catch (PDOException $e) {
    echo "❌ ERREUR: Impossible de créer la base de données\n";
    echo "Message: " . $e->getMessage() . "\n\n";
    exit;
}

// Étape 3: Sélectionner la base de données
echo "=== ÉTAPE 3: SÉLECTION DE LA BASE DE DONNÉES ===\n";
try {
    $pdo->exec("USE `$dbname`");
    echo "✅ Base de données '$dbname' sélectionnée\n\n";
} catch (PDOException $e) {
    echo "❌ ERREUR: Impossible de sélectionner la base de données\n";
    echo "Message: " . $e->getMessage() . "\n\n";
    exit;
}

// Étape 4: Créer les tables
echo "=== ÉTAPE 4: CRÉATION DES TABLES ===\n";

// Lire le fichier schema.sql
$schemaFile = __DIR__ . '/database/schema.sql';
if (!file_exists($schemaFile)) {
    echo "❌ ERREUR: Fichier schema.sql introuvable\n";
    exit;
}

$schema = file_get_contents($schemaFile);

// Séparer les requêtes
$queries = array_filter(
    array_map('trim', explode(';', $schema)),
    function($query) {
        return !empty($query) && strpos($query, '--') !== 0;
    }
);

$successCount = 0;
$errorCount = 0;

foreach ($queries as $query) {
    if (empty(trim($query))) continue;
    
    try {
        $pdo->exec($query);
        
        // Extraire le nom de la table
        if (preg_match('/CREATE TABLE.*?`(\w+)`/i', $query, $matches)) {
            echo "✅ Table '{$matches[1]}' créée\n";
            $successCount++;
        }
    } catch (PDOException $e) {
        echo "❌ ERREUR lors de l'exécution d'une requête\n";
        echo "Message: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\nRésumé: $successCount table(s) créée(s), $errorCount erreur(s)\n\n";

// Étape 5: Vérifier les tables
echo "=== ÉTAPE 5: VÉRIFICATION DES TABLES ===\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($tables) > 0) {
    echo "Tables présentes dans la base de données:\n";
    foreach ($tables as $table) {
        echo "  • $table\n";
        
        // Compter les enregistrements
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $result = $stmt->fetch();
        echo "    → " . $result['count'] . " enregistrement(s)\n";
    }
} else {
    echo "⚠️ Aucune table trouvée dans la base de données\n";
}

echo "\n=== ÉTAPE 6: CRÉATION DU DOSSIER UPLOADS ===\n";
$uploadDir = __DIR__ . '/uploads/cv';
if (!is_dir($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "✅ Dossier uploads/cv créé\n";
    } else {
        echo "❌ Impossible de créer le dossier uploads/cv\n";
    }
} else {
    echo "✅ Dossier uploads/cv existe déjà\n";
}

// Vérifier les permissions
if (is_writable($uploadDir)) {
    echo "✅ Dossier accessible en écriture\n";
} else {
    echo "⚠️ Dossier non accessible en écriture\n";
    echo "   Exécutez: chmod 755 uploads/cv\n";
}

echo "\n=== CONFIGURATION TERMINÉE ===\n";
echo "La base de données est prête à être utilisée.\n";
echo "Vous pouvez maintenant tester avec test-database.php\n";

echo "</pre>";
?>
