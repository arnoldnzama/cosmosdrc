<?php
/**
 * Script de traitement du formulaire de contact
 * Cosmos Group - Système de contact
 * Destinataire : contact1@emergencemag.net
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
    'errors' => []
];

/**
 * Fonction pour envoyer une réponse JSON propre
 */
function send_response($success, $message, $errors = []) {
    global $response;
    if (ob_get_level()) {
        ob_end_clean();
    }
    $response['success'] = $success;
    $response['message'] = $message;
    $response['errors'] = $errors;
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
    error_log("=== DÉBUT TRAITEMENT FORMULAIRE CONTACT ===");
    error_log("Méthode: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
    
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Charger les fichiers requis
    $requiredFiles = [
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
    
    // Définir les constantes nécessaires si elles n'existent pas déjà
    if (!defined('SITE_NAME')) {
        define('SITE_NAME', 'Cosmos Group');
    }
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'https://cosmos.emergencemag.net');
    }
    
    error_log("✅ Tous les fichiers chargés");

    // Récupérer les données
    $data = $_POST;
    
    error_log("Données POST: " . json_encode(array_keys($data)));

    // Validation des données
    $errors = [];
    
    // Vérifier les champs obligatoires
    if (empty($data['firstName'])) {
        $errors['firstName'] = 'Le prénom est obligatoire';
    }
    
    if (empty($data['lastName'])) {
        $errors['lastName'] = 'Le nom est obligatoire';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'L\'email est obligatoire';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalide';
    }
    
    if (empty($data['subject'])) {
        $errors['subject'] = 'Le sujet est obligatoire';
    }
    
    if (empty($data['message'])) {
        $errors['message'] = 'Le message est obligatoire';
    }
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Veuillez corriger les erreurs dans le formulaire';
        error_log("❌ Validation échouée: " . json_encode($errors));
        throw new Exception('Validation échouée');
    }
    
    error_log("✅ Validation OK");

    // Nettoyer les données
    $sanitizedData = [
        'firstName' => trim($data['firstName']),
        'lastName' => trim($data['lastName']),
        'email' => trim($data['email']),
        'phone' => isset($data['phone']) ? trim($data['phone']) : '',
        'subject' => trim($data['subject']),
        'message' => trim($data['message'])
    ];

    // Initialiser EmailService
    $emailService = new EmailService();

    // Envoyer les emails
    try {
        error_log("📧 Envoi des emails...");
        
        $adminEmailSent = false;
        $confirmationEmailSent = false;
        
        // Email à l'administration
        try {
            $adminEmailSent = $emailService->sendContactEmail($sanitizedData);
            error_log("✅ Email envoyé à l'administration: " . ($adminEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $adminError) {
            error_log("❌ Erreur envoi email administration: " . $adminError->getMessage());
        }
        
        // Email de confirmation à l'utilisateur
        try {
            $confirmationEmailSent = $emailService->sendContactConfirmation($sanitizedData);
            error_log("✅ Email de confirmation envoyé à l'utilisateur: " . ($confirmationEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $confirmError) {
            error_log("❌ Erreur envoi email de confirmation: " . $confirmError->getMessage());
        }
        
        // Déterminer le message de réponse selon les résultats
        if ($confirmationEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre message a été envoyé avec succès ! Un email de confirmation vous a été envoyé. Nous vous répondrons dans les plus brefs délais.';
        } elseif ($adminEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.';
            error_log("⚠️ Message envoyé mais confirmation email échouée");
        } else {
            throw new Exception('Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard ou nous contacter directement par téléphone.');
        }
        
    } catch (Exception $emailError) {
        error_log("⚠️ Erreur envoi email: " . $emailError->getMessage());
        throw new Exception($emailError->getMessage());
    }
    
    error_log("=== FIN TRAITEMENT FORMULAIRE CONTACT (SUCCÈS) ===");

} catch (Exception $e) {
    error_log("❌ ERREUR: " . $e->getMessage());
    error_log("Fichier: " . $e->getFile() . " Ligne: " . $e->getLine());
    
    $response['success'] = false;
    
    // Message d'erreur adapté
    if (strpos($e->getMessage(), 'Validation') !== false) {
        $response['message'] = 'Veuillez corriger les erreurs dans le formulaire.';
    } else {
        $response['message'] = $e->getMessage();
    }
    
    error_log("=== FIN TRAITEMENT FORMULAIRE CONTACT (ERREUR) ===");
}

// Nettoyer le buffer et envoyer le JSON
ob_end_clean();
echo json_encode($response);
exit;
