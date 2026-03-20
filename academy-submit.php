<?php
/**
 * Script de traitement du formulaire COSMOS Academy
 * COSMOS Group - Système d'inscription Academy
 * Destinataire : academie@emergencemag.net
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
    error_log("=== DÉBUT TRAITEMENT FORMULAIRE ACADEMY ===");
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
        define('SITE_NAME', 'COSMOS Group');
    }
    if (!defined('SITE_URL')) {
        define('SITE_URL', 'https://COSMOS.emergencemag.net');
    }
    
    error_log("✅ Tous les fichiers chargés");

    // Récupérer les données
    $data = $_POST;
    
    error_log("Données POST: " . json_encode(array_keys($data)));

    // Validation des données
    $errors = [];
    
    // Vérifier les champs obligatoires
    if (empty($data['nom_prenom'])) {
        $errors['nom_prenom'] = 'Le nom et prénom sont obligatoires';
    }
    
    if (empty($data['age'])) {
        $errors['age'] = 'L\'âge est obligatoire';
    } elseif (!is_numeric($data['age']) || $data['age'] < 15 || $data['age'] > 35) {
        $errors['age'] = 'L\'âge doit être entre 15 et 35 ans';
    }
    
    if (empty($data['niveau_etude'])) {
        $errors['niveau_etude'] = 'Le niveau d\'étude est obligatoire';
    }
    
    if (empty($data['ville'])) {
        $errors['ville'] = 'La ville est obligatoire';
    }
    
    if (empty($data['whatsapp'])) {
        $errors['whatsapp'] = 'Le téléphone est obligatoire';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'L\'email est obligatoire';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalide';
    }
    
    if (empty($data['programme'])) {
        $errors['programme'] = 'Le programme souhaité est obligatoire';
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
        'nom_prenom' => trim($data['nom_prenom']),
        'age' => trim($data['age']),
        'niveau_etude' => trim($data['niveau_etude']),
        'etablissement' => isset($data['etablissement']) ? trim($data['etablissement']) : '',
        'ville' => trim($data['ville']),
        'whatsapp' => trim($data['whatsapp']),
        'email' => trim($data['email']),
        'programme' => trim($data['programme']),
        'motivation' => isset($data['motivation']) ? trim($data['motivation']) : ''
    ];

    // Initialiser EmailService
    $emailService = new EmailService();

    // Envoyer les emails
    try {
        error_log("📧 Envoi des emails Academy...");
        
        $adminEmailSent = false;
        $confirmationEmailSent = false;
        
        // Email à l'administration (academie@emergencemag.net)
        try {
            $adminEmailSent = $emailService->sendAcademyEmail($sanitizedData);
            error_log("✅ Email envoyé à l'administration Academy: " . ($adminEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $adminError) {
            error_log("❌ Erreur envoi email administration Academy: " . $adminError->getMessage());
        }
        
        // Email de confirmation à l'utilisateur
        try {
            $confirmationEmailSent = $emailService->sendAcademyConfirmation($sanitizedData);
            error_log("✅ Email de confirmation Academy envoyé à l'utilisateur: " . ($confirmationEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $confirmError) {
            error_log("❌ Erreur envoi email de confirmation Academy: " . $confirmError->getMessage());
        }
        
        // Déterminer le message de réponse selon les résultats
        if ($confirmationEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre inscription a été envoyée avec succès ! Un email de confirmation vous a été envoyé. Nous vous contacterons dans les plus brefs délais.';
        } elseif ($adminEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre inscription a été envoyée avec succès ! Nous vous contacterons dans les plus brefs délais.';
            error_log("⚠️ Inscription envoyée mais confirmation email échouée");
        } else {
            throw new Exception('Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard ou nous contacter directement par téléphone.');
        }
        
    } catch (Exception $emailError) {
        error_log("⚠️ Erreur envoi email Academy: " . $emailError->getMessage());
        throw new Exception($emailError->getMessage());
    }
    
    error_log("=== FIN TRAITEMENT FORMULAIRE ACADEMY (SUCCÈS) ===");

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
    
    error_log("=== FIN TRAITEMENT FORMULAIRE ACADEMY (ERREUR) ===");
}

// Nettoyer le buffer et envoyer le JSON
ob_end_clean();
echo json_encode($response);
exit;
