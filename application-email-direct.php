<?php
/**
 * Script d'envoi direct d'emails pour les candidatures
 * COSMOS Group - Système de recrutement
 * Version: Email Direct (sans base de données)
 * Destinataire: web@emergencemag.net
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
    error_log("=== DÉBUT TRAITEMENT CANDIDATURE (EMAIL DIRECT) ===");
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
    
    // Charger .env en premier
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile) && file_exists(__DIR__ . '/includes/LoadEnv.php')) {
        require_once __DIR__ . '/includes/LoadEnv.php';
        LoadEnv::load($envFile);
        error_log("✅ Fichier .env chargé");
    }
    
    // Définir la timezone
    date_default_timezone_set('Africa/Kinshasa');
    
    // Charger les fichiers requis
    foreach ($requiredFiles as $file) {
        $filePath = __DIR__ . '/' . $file;
        if (!file_exists($filePath)) {
            error_log("❌ Fichier requis manquant: $file");
            throw new Exception("Fichier requis manquant : $file");
        }
        require_once $filePath;
        error_log("✅ Fichier chargé: $file");
    }
    
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

    // Traiter le fichier CV
    error_log("📤 Traitement du fichier CV...");
    
    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Fichier CV manquant ou erreur lors de l\'upload');
    }
    
    $cvFile = $validator->processUploadedFile('cv', $uploadDir);
    
    if (!$cvFile) {
        throw new Exception('Erreur lors du traitement du fichier CV');
    }
    
    error_log("✅ CV traité: " . $cvFile['filename']);

    // Traiter le fichier Lettre de motivation (OBLIGATOIRE selon le schéma BDD)
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
        
        // Métadonnées techniques
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ];

    // Préparer les données pour l'email (inclut les infos du candidat NON stockées en BDD)
    $applicationData = array_merge($dbData, [
        'nom_complet' => $sanitizedData['nom_complet'],
        'email' => $sanitizedData['email'],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'nouveau'
    ]);

    // Connexion à la base de données et insertion
    $applicationId = null;
    try {
        error_log("� Connexion à la base de données...");
        $db = Database::getInstance();
        
        // Insérer dans la base de données
        error_log("💾 Insertion dans la base de données...");
        $applicationId = $db->insertApplication($dbData);
        
        if (!$applicationId) {
            throw new Exception('Erreur lors de l\'enregistrement dans la base de données');
        }
        
        error_log("✅ Candidature enregistrée en BDD avec ID: " . $applicationId);
        
    } catch (Exception $dbError) {
        error_log("⚠️ Erreur BDD: " . $dbError->getMessage());
        // Continuer même si la BDD échoue (fallback sur JSON)
    }

    // Envoyer les emails
    $emailService = new EmailService();
    
    try {
        error_log("📧 Envoi des emails...");
        
        // Email à l'administration avec pièces jointes
        $emailService->sendApplicationEmail(
            $applicationData, 
            $cvFile['absolute_path'],
            $lettreFile['absolute_path']
        );
        
        // Email de confirmation au candidat
        $emailService->sendCandidateConfirmation($applicationData);
        
        error_log("✅ Emails envoyés avec succès");
        
    } catch (Exception $emailError) {
        error_log("⚠️ Erreur envoi email: " . $emailError->getMessage());
        // Continuer même si l'email échoue
    }

    // Backup JSON (si la BDD a échoué ou comme sauvegarde supplémentaire)
    if (!$applicationId) {
        try {
            $dataDir = __DIR__ . '/data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            $jsonFile = $dataDir . '/applications.json';
            
            // Générer un ID unique
            $applicationId = uniqid('app_', true);
            $applicationData['id'] = $applicationId;
            $applicationData['source'] = 'json_fallback';
            
            // Lire les données existantes
            $applications = [];
            if (file_exists($jsonFile)) {
                $jsonContent = file_get_contents($jsonFile);
                $applications = json_decode($jsonContent, true) ?: [];
            }
            
            // Ajouter la nouvelle candidature
            array_unshift($applications, $applicationData);
            
            // Limiter à 1000 entrées (rotation automatique)
            if (count($applications) > 1000) {
                $applications = array_slice($applications, 0, 1000);
            }
            
            // Sauvegarder
            file_put_contents($jsonFile, json_encode($applications, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            error_log("✅ Candidature enregistrée dans applications.json (fallback)");
            
        } catch (Exception $jsonError) {
            error_log("⚠️ Erreur sauvegarde JSON: " . $jsonError->getMessage());
        }
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
        $response['message'] = 'Erreur de connexion à la base de données. Votre candidature a été enregistrée et sera traitée.';
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
