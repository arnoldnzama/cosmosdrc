<?php
/**
 * Script de soumission de candidature avec base de données
 * COSMOS Group - Système de recrutement
 * Version: Database Integration
 * Conforme au schéma cosmos_database.sql
 */

// Nettoyer tous les buffers de sortie existants
while (ob_get_level()) {
    ob_end_clean();
}

// Démarrer un nouveau buffer
ob_start();

// Configuration des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Header JSON
header('Content-Type: application/json; charset=utf-8');

// Réponse par défaut
$response = [
    'success' => false,
    'message' => 'Une erreur imprévue est survenue.',
    'errors' => [],
    'application_id' => null
];

/**
 * Fonction pour envoyer une réponse JSON propre
 */
function send_response($success, $message, $errors = [], $data = []) {
    global $response;
    if (ob_get_level()) {
        ob_end_clean();
    }
    $response['success'] = $success;
    $response['message'] = $message;
    $response['errors'] = $errors;
    $response = array_merge($response, $data);
    echo json_encode($response);
    exit;
}

// Gestionnaire d'erreurs personnalisé
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

// Gestionnaire de fin de script
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("PHP FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}");
        send_response(false, 'Une erreur technique est survenue sur le serveur.');
    }
});

try {
    error_log("=== DÉBUT TRAITEMENT CANDIDATURE (DATABASE) ===");
    error_log("Méthode: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
    
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Charger les fichiers requis
    $requiredFiles = [
        'includes/LoadEnv.php',
        'includes/Validator.php',
        'includes/Database.php',
        'includes/EmailService.php'
    ];
    
    foreach ($requiredFiles as $file) {
        $filePath = __DIR__ . '/' . $file;
        if (!file_exists($filePath)) {
            error_log("❌ Fichier requis manquant: $file");
            throw new Exception("Fichier requis manquant : $file");
        }
        require_once $filePath;
        error_log("✅ Fichier chargé: $file");
    }
    
    // Charger .env
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        LoadEnv::load($envFile);
        error_log("✅ Fichier .env chargé");
    }
    
    // Définir la timezone
    date_default_timezone_set('Africa/Kinshasa');
    
    error_log("✅ Tous les fichiers chargés");

    // Récupérer les données
    $data = $_POST;
    $files = $_FILES;
    
    error_log("Données POST: " . json_encode(array_keys($data)));
    error_log("Fichiers: " . json_encode(array_keys($files)));

    // Validation des données
    $validator = new Validator($data);

    if (!$validator->validateApplicationForm()) {
        $response['errors'] = $validator->getErrors();
        $response['message'] = 'Veuillez corriger les erreurs dans le formulaire';
        error_log("❌ Validation échouée: " . json_encode($validator->getErrors()));
        throw new Exception('Validation échouée');
    }
    
    error_log("✅ Validation OK");

    // Nettoyer les données
    $sanitizedData = $validator->sanitizeData();

    // Créer le dossier uploads si nécessaire
    $uploadDir = __DIR__ . '/uploads/cv';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier uploads/cv');
        }
        error_log("📁 Dossier uploads/cv créé");
    }
    
    if (!is_writable($uploadDir)) {
        throw new Exception('Dossier uploads/cv non accessible en écriture');
    }

    // Traiter le fichier CV (OBLIGATOIRE)
    error_log("📤 Traitement du fichier CV...");
    
    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Fichier CV manquant ou erreur lors de l\'upload');
    }
    
    $cvFile = $validator->processUploadedFile('cv', $uploadDir);
    
    if (!$cvFile) {
        throw new Exception('Erreur lors du traitement du fichier CV');
    }
    
    error_log("✅ CV traité: " . $cvFile['filename']);

    // Traiter le fichier Lettre de motivation (OBLIGATOIRE selon le schéma)
    error_log("📤 Traitement de la lettre de motivation...");
    
    if (!isset($_FILES['lettre']) || $_FILES['lettre']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Fichier lettre de motivation manquant ou erreur lors de l\'upload');
    }
    
    $lettreFile = $validator->processUploadedFile('lettre', $uploadDir);
    
    if (!$lettreFile) {
        throw new Exception('Erreur lors du traitement de la lettre de motivation');
    }
    
    error_log("✅ Lettre traitée: " . $lettreFile['filename']);

    // Préparer les données pour la base de données (conforme au schéma SQL)
    $dbData = [
        // Documents (OBLIGATOIRES)
        'cv_filename' => $cvFile['filename'],
        'cv_path' => $cvFile['path'],
        'lettre_filename' => $lettreFile['filename'],
        'lettre_path' => $lettreFile['path'],
        
        // Informations du poste (OBLIGATOIRES)
        'job_title' => $sanitizedData['job_title'],
        'job_company' => $sanitizedData['job_company'],
        'job_location' => $sanitizedData['job_location'],
        'job_description' => $sanitizedData['job_description'] ?? null,
        
        // Informations RH complémentaires
        'disponibilite' => $sanitizedData['disponibilite'] ?? null,
        'pretention_salariale' => !empty($sanitizedData['pretention_salariale']) ? (float)$sanitizedData['pretention_salariale'] : null,
        
        // Métadonnées techniques
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    // Préparer les données pour l'email (inclut les infos du candidat)
    $emailData = array_merge($dbData, [
        'nom_complet' => $sanitizedData['nom_complet'],
        'email' => $sanitizedData['email'],
        'cv_absolute_path' => $cvFile['absolute_path'],
        'lettre_absolute_path' => $lettreFile['absolute_path']
    ]);

    // Connexion à la base de données
    error_log("🔌 Connexion à la base de données...");
    $db = Database::getInstance();
    
    // Insérer dans la base de données
    error_log("💾 Insertion dans la base de données...");
    $applicationId = $db->insertApplication($dbData);
    
    if (!$applicationId) {
        throw new Exception('Erreur lors de l\'enregistrement dans la base de données');
    }
    
    error_log("✅ Candidature enregistrée avec ID: " . $applicationId);

    // Synchroniser avec le fichier JSON (pour le dashboard admin)
    $jsonFile = __DIR__ . '/data/applications.json';
    $jsonRecord = array_merge($dbData, [
        'id' => (int)$applicationId,
        'status' => 'nouveau',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => null
    ]);
    $existing = [];
    if (file_exists($jsonFile)) {
        $existing = json_decode(file_get_contents($jsonFile), true) ?: [];
    }
    array_unshift($existing, $jsonRecord);
    file_put_contents($jsonFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Envoyer les emails
    $emailService = new EmailService();
    
    try {
        error_log("📧 Envoi des emails...");
        
        // Email à l'administration avec pièces jointes
        $emailService->sendApplicationEmail(
            $emailData, 
            $cvFile['absolute_path'],
            $lettreFile['absolute_path']
        );
        
        // Email de confirmation au candidat
        $emailService->sendCandidateConfirmation($emailData);
        
        error_log("✅ Emails envoyés avec succès");
        
    } catch (Exception $emailError) {
        error_log("⚠️ Erreur envoi email: " . $emailError->getMessage());
        // Continuer même si l'email échoue (la candidature est déjà en BDD)
    }

    // Succès
    $response['success'] = true;
    $response['message'] = 'Merci pour votre candidature ! Vous allez recevoir un email de confirmation.';
    $response['application_id'] = $applicationId;
    
    error_log("=== FIN TRAITEMENT CANDIDATURE (SUCCÈS) ===");

} catch (Exception $e) {
    error_log("❌ ERREUR: " . $e->getMessage());
    error_log("Fichier: " . $e->getFile() . " Ligne: " . $e->getLine());
    error_log("Stack: " . $e->getTraceAsString());
    
    $response['success'] = false;
    
    // Message d'erreur adapté
    if (strpos($e->getMessage(), 'Validation') !== false) {
        $response['message'] = 'Veuillez corriger les erreurs dans le formulaire.';
    } elseif (strpos($e->getMessage(), 'fichier') !== false || strpos($e->getMessage(), 'upload') !== false) {
        $response['message'] = 'Erreur lors du traitement des fichiers. Vérifiez que vos fichiers sont au format PDF, DOC ou DOCX et font moins de 5 MB.';
    } elseif (strpos($e->getMessage(), 'base de données') !== false || strpos($e->getMessage(), 'database') !== false) {
        $response['message'] = 'Erreur de connexion à la base de données. Veuillez réessayer plus tard.';
    } else {
        $response['message'] = 'Une erreur est survenue lors du traitement de votre candidature. Veuillez réessayer.';
    }
    
    $response['errors']['general'] = $e->getMessage();
    
    error_log("=== FIN TRAITEMENT CANDIDATURE (ERREUR) ===");
}

// Nettoyer le buffer et envoyer le JSON
ob_end_clean();
echo json_encode($response);
exit;
