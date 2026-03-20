<?php
/**
 * Script de traitement de l'abonnement newsletter
 * COSMOS Group - Envoi direct à news@emergencemag.net
 * SANS STOCKAGE EN BASE DE DONNÉES - Envoi email uniquement
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/EmailService.php';

$response = [
    'success' => false,
    'message' => ''
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Méthode non autorisée';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email)) {
        $response['message'] = 'L\'adresse email est requise';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'L\'adresse email n\'est pas valide';
        ob_end_clean();
        echo json_encode($response);
        exit;
    }

    error_log("📧 Nouvelle demande d'abonnement newsletter: " . $email);
    
    // Envoi direct des emails sans stockage en base de données
    $emailService = new EmailService();
    
    $emailSent = false;
    $confirmationSent = false;
    
    try {
        // Envoyer la notification à news@emergencemag.net
        $emailSent = $emailService->sendNewsletterEmail($email);
        error_log("✅ Email envoyé à news@emergencemag.net: " . ($emailSent ? 'Succès' : 'Échec'));
    } catch (Exception $e) {
        error_log("❌ Erreur envoi email à news@emergencemag.net: " . $e->getMessage());
    }
    
    try {
        // Envoyer la confirmation à l'utilisateur
        $confirmationSent = $emailService->sendNewsletterConfirmation($email);
        error_log("✅ Email de confirmation envoyé à l'utilisateur: " . ($confirmationSent ? 'Succès' : 'Échec'));
    } catch (Exception $e) {
        error_log("❌ Erreur envoi confirmation utilisateur: " . $e->getMessage());
    }
    
    // Réponse selon le résultat
    if ($confirmationSent) {
        $response['success'] = true;
        $response['message'] = 'Abonnement réussi ! Un email de confirmation vous a été envoyé. Vérifiez votre boîte de réception et vos spams.';
        error_log("✅ Abonnement newsletter réussi pour: " . $email);
    } elseif ($emailSent) {
        $response['success'] = true;
        $response['message'] = 'Votre demande d\'abonnement a été enregistrée. Vous recevrez bientôt nos offres d\'emploi.';
        error_log("⚠️ Notification envoyée mais confirmation échouée pour: " . $email);
    } else {
        $response['success'] = false;
        $response['message'] = 'Une erreur est survenue lors de l\'envoi. Veuillez réessayer ou nous contacter directement.';
        error_log("❌ Échec complet de l'abonnement pour: " . $email);
    }

} catch (Exception $e) {
    error_log("❌ Erreur critique traitement newsletter: " . $e->getMessage());
    $response['success'] = false;
    $response['message'] = 'Erreur de connexion. Veuillez réessayer plus tard.';
}

ob_end_clean();
echo json_encode($response);
?>