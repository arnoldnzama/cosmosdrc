<?php
/**
 * Configuration de la base de données
 * Cosmos Group - Système de recrutement
 */

// Charger les variables d'environnement depuis .env
require_once __DIR__ . '/../includes/LoadEnv.php';
LoadEnv::load(__DIR__ . '/../.env');

// Configuration de la base de données avec variables d'environnement
define('DB_HOST', getenv('COSMOS_DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('COSMOS_DB_PORT') ?: 3306));
define('DB_NAME', getenv('COSMOS_DB_NAME') ?: 'emergen1_cosmos');
define('DB_USER', getenv('COSMOS_DB_USER') ?: 'emergen1_cosmos');
// IMPORTANT : ne jamais commiter un mot de passe en production
define('DB_PASS', getenv('COSMOS_DB_PASS') ?: 'F@mille123');
define('DB_CHARSET', getenv('COSMOS_DB_CHARSET') ?: 'utf8mb4');

// Configuration du site
define('SITE_NAME', 'Cosmos Group');
define('SITE_URL', 'https://cosmos.emergencemag.net/');

// Configuration des emails (depuis .env)
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'web@emergencemag.net');
define('CONTACT_EMAIL', getenv('CONTACT_EMAIL') ?: 'contact1@emergencemag.net');
define('NEWS_EMAIL', getenv('NEWS_EMAIL') ?: 'news@emergencemag.net');

// Configuration des uploads
define('UPLOAD_DIR', __DIR__ . '/../uploads/cv');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx']);

// Messages d'erreur
define('ERROR_MESSAGES', [
    'database_connection' => 'Erreur de connexion à la base de données',
    'database_insert_failed' => 'Erreur lors de l\'enregistrement des données',
    'invalid_email' => 'Adresse email invalide',
    'invalid_phone' => 'Numéro de téléphone invalide',
    'file_too_large' => 'Le fichier est trop volumineux (max 5 MB)',
    'invalid_file_type' => 'Type de fichier non autorisé (PDF uniquement)',
    'csrf_invalid' => 'Token de sécurité invalide',
    'required_field' => 'Ce champ est obligatoire'
]);

// Configuration de la timezone
date_default_timezone_set('Africa/Kinshasa');

// Configuration des erreurs
if (getenv('APP_ENV') === 'production') {
    $errorMask = E_ALL;
    $errorMask &= ~E_NOTICE;
    $errorMask &= ~E_DEPRECATED;
    error_reporting($errorMask);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

// Test de connexion à la base de données au chargement
try {
    $testConn = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    error_log("✅ Configuration BDD validée - Host: " . DB_HOST . ", DB: " . DB_NAME);
    $testConn = null;
} catch (PDOException $e) {
    error_log("❌ ERREUR CONFIG BDD: " . $e->getMessage());
    error_log("   Host: " . DB_HOST . ", Port: " . DB_PORT . ", DB: " . DB_NAME . ", User: " . DB_USER);
}
