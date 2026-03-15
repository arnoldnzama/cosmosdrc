<?php
/**
 * Script de traitement du formulaire Recruteur
 * Cosmos Group - Système de demande de recrutement
 * Destinataire : recrutement@emergencemag.net
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
    error_log("=== DÉBUT TRAITEMENT FORMULAIRE RECRUTEUR ===");
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
    
    // Vérifier les champs obligatoires - Informations entreprise
    if (empty($data['entreprise'])) {
        $errors['entreprise'] = 'Le nom de l\'entreprise est obligatoire';
    }
    
    if (empty($data['nom_responsable'])) {
        $errors['nom_responsable'] = 'Le nom du responsable est obligatoire';
    }
    
    if (empty($data['fonction'])) {
        $errors['fonction'] = 'La fonction est obligatoire';
    }
    
    if (empty($data['email'])) {
        $errors['email'] = 'L\'email professionnel est obligatoire';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email invalide';
    }
    
    if (empty($data['telephone'])) {
        $errors['telephone'] = 'Le téléphone est obligatoire';
    }
    
    if (empty($data['ville_pays'])) {
        $errors['ville_pays'] = 'La ville/pays est obligatoire';
    }
    
    // Vérifier le type de recrutement
    if (empty($data['type_recrutement']) || !is_array($data['type_recrutement'])) {
        $errors['type_recrutement'] = 'Veuillez sélectionner au moins un type de recrutement';
    }
    
    // Vérifier les informations sur le poste
    if (empty($data['intitule_poste'])) {
        $errors['intitule_poste'] = 'L\'intitulé du poste est obligatoire';
    }
    
    if (empty($data['secteur_activite'])) {
        $errors['secteur_activite'] = 'Le secteur d\'activité est obligatoire';
    }
    
    if (empty($data['localisation'])) {
        $errors['localisation'] = 'La localisation est obligatoire';
    }
    
    if (empty($data['niveau_experience'])) {
        $errors['niveau_experience'] = 'Le niveau d\'expérience est obligatoire';
    }
    
    if (empty($data['nombre_postes'])) {
        $errors['nombre_postes'] = 'Le nombre de postes est obligatoire';
    } elseif (!is_numeric($data['nombre_postes']) || $data['nombre_postes'] < 1) {
        $errors['nombre_postes'] = 'Le nombre de postes doit être au moins 1';
    }
    
    if (empty($data['description_besoin'])) {
        $errors['description_besoin'] = 'La description du besoin est obligatoire';
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
        'entreprise' => trim($data['entreprise']),
        'nom_responsable' => trim($data['nom_responsable']),
        'fonction' => trim($data['fonction']),
        'email' => trim($data['email']),
        'telephone' => trim($data['telephone']),
        'ville_pays' => trim($data['ville_pays']),
        'type_recrutement' => $data['type_recrutement'],
        'intitule_poste' => trim($data['intitule_poste']),
        'secteur_activite' => trim($data['secteur_activite']),
        'localisation' => trim($data['localisation']),
        'niveau_experience' => trim($data['niveau_experience']),
        'nombre_postes' => trim($data['nombre_postes']),
        'description_besoin' => trim($data['description_besoin'])
    ];

    // Initialiser EmailService
    $emailService = new EmailService();

    // Envoyer les emails
    try {
        error_log("📧 Envoi des emails Recruteur...");
        
        $adminEmailSent = false;
        $confirmationEmailSent = false;
        
        // Email à l'administration (recrutement@emergencemag.net)
        try {
            $adminEmailSent = $emailService->sendRecruiterEmail($sanitizedData);
            error_log("✅ Email envoyé à l'administration Recruteur: " . ($adminEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $adminError) {
            error_log("❌ Erreur envoi email administration Recruteur: " . $adminError->getMessage());
        }
        
        // Email de confirmation à l'entreprise
        try {
            $confirmationEmailSent = $emailService->sendRecruiterConfirmation($sanitizedData);
            error_log("✅ Email de confirmation Recruteur envoyé à l'entreprise: " . ($confirmationEmailSent ? 'Succès' : 'Échec'));
        } catch (Exception $confirmError) {
            error_log("❌ Erreur envoi email de confirmation Recruteur: " . $confirmError->getMessage());
        }
        
        // Déterminer le message de réponse selon les résultats
        if ($confirmationEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre demande a été envoyée avec succès ! Un email de confirmation vous a été envoyé. Notre équipe vous contactera dans les 48 heures.';
        } elseif ($adminEmailSent) {
            $response['success'] = true;
            $response['message'] = 'Votre demande a été envoyée avec succès ! Notre équipe vous contactera dans les 48 heures.';
            error_log("⚠️ Demande envoyée mais confirmation email échouée");
        } else {
            throw new Exception('Erreur lors de l\'envoi de l\'email. Veuillez réessayer plus tard ou nous contacter directement par téléphone.');
        }
        
    } catch (Exception $emailError) {
        error_log("⚠️ Erreur envoi email Recruteur: " . $emailError->getMessage());
        throw new Exception($emailError->getMessage());
    }
    
    error_log("=== FIN TRAITEMENT FORMULAIRE RECRUTEUR (SUCCÈS) ===");

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
    
    error_log("=== FIN TRAITEMENT FORMULAIRE RECRUTEUR (ERREUR) ===");
}

// Nettoyer le buffer et envoyer le JSON
ob_end_clean();
echo json_encode($response);
exit;
