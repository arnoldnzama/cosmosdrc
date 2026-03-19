<?php
/**
 * Script de soumission de candidature spontanée
 * Cosmos Group - Candidature spontanée
 * Destinataire: cs@emergencemag.net
 */

while (ob_get_level()) { ob_end_clean(); }
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false, 'message' => 'Une erreur imprévue est survenue.', 'errors' => []];

function send_response($success, $message, $errors = []) {
    global $response;
    if (ob_get_level()) ob_end_clean();
    echo json_encode(['success' => $success, 'message' => $message, 'errors' => $errors]);
    exit;
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) return false;
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    return false;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        send_response(false, 'Une erreur technique est survenue sur le serveur.');
    }
});

try {
    error_log("=== DÉBUT CANDIDATURE SPONTANÉE ===");

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Charger l'environnement et PHPMailer
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile) && file_exists(__DIR__ . '/includes/LoadEnv.php')) {
        require_once __DIR__ . '/includes/LoadEnv.php';
        LoadEnv::load($envFile);
    }

    date_default_timezone_set('Africa/Kinshasa');

    // Charger PHPMailer
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('Autoloader Composer introuvable');
    }
    require_once $autoload;

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception as MailException;

    // ===== VALIDATION =====
    $errors = [];

    $nom       = trim($_POST['nom_complet'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $domaine   = trim($_POST['domaine'] ?? '');
    $poste     = trim($_POST['poste'] ?? '');
    $experience    = trim($_POST['experience'] ?? '');
    $disponibilite = trim($_POST['disponibilite'] ?? '');
    $whatsapp      = trim($_POST['whatsapp'] ?? '');
    $niveau_etude  = trim($_POST['niveau_etude'] ?? '');
    $salaire       = trim($_POST['salaire'] ?? '');
    $message_text  = trim($_POST['message'] ?? '');
    $consent       = !empty($_POST['consent']);

    if (!$nom)       $errors['nom_complet']  = 'Le nom complet est obligatoire.';
    if (!$email)     $errors['email']        = "L'email est obligatoire.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Adresse email invalide.';
    if (!$telephone) $errors['telephone']    = 'Le téléphone est obligatoire.';
    if (!$domaine)   $errors['domaine']      = 'Veuillez sélectionner un domaine.';
    if (!$poste)     $errors['poste']        = 'Le poste recherché est obligatoire.';
    if (!$experience)    $errors['experience']    = "Veuillez sélectionner votre expérience.";
    if (!$disponibilite) $errors['disponibilite'] = 'Veuillez indiquer votre disponibilité.';
    if (!$consent)   $errors['consent']      = 'Vous devez accepter les conditions.';

    // Validation CV
    if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
        $errors['cv'] = 'Le CV est obligatoire.';
    } elseif ($_FILES['cv']['size'] > 5 * 1024 * 1024) {
        $errors['cv'] = 'Le fichier CV dépasse 5 Mo.';
    } else {
        $cvExt = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        if (!in_array($cvExt, ['pdf', 'doc', 'docx'])) {
            $errors['cv'] = 'Format CV invalide (PDF, DOC, DOCX uniquement).';
        }
    }

    // Validation lettre (optionnelle mais vérifier si fournie)
    $hasLettre = isset($_FILES['lettre']) && $_FILES['lettre']['error'] === UPLOAD_ERR_OK;
    if ($hasLettre) {
        if ($_FILES['lettre']['size'] > 5 * 1024 * 1024) {
            $errors['lettre'] = 'La lettre de motivation dépasse 5 Mo.';
        } else {
            $lettreExt = strtolower(pathinfo($_FILES['lettre']['name'], PATHINFO_EXTENSION));
            if (!in_array($lettreExt, ['pdf', 'doc', 'docx'])) {
                $errors['lettre'] = 'Format lettre invalide (PDF, DOC, DOCX uniquement).';
            }
        }
    }

    if (!empty($errors)) {
        send_response(false, 'Veuillez corriger les erreurs dans le formulaire.', $errors);
    }

    // ===== UPLOAD DES FICHIERS =====
    $uploadDir = __DIR__ . '/uploads/spontaneous';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Impossible de créer le dossier uploads/spontaneous');
        }
    }
    if (!is_writable($uploadDir)) {
        throw new Exception('Dossier uploads/spontaneous non accessible en écriture');
    }

    // Upload CV
    $cvOrigName = basename($_FILES['cv']['name']);
    $cvSafeName = uniqid('cv_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $cvOrigName);
    $cvPath = $uploadDir . '/' . $cvSafeName;
    if (!move_uploaded_file($_FILES['cv']['tmp_name'], $cvPath)) {
        throw new Exception('Erreur lors du déplacement du fichier CV');
    }
    error_log("✅ CV uploadé: $cvSafeName");

    // Upload lettre (optionnel)
    $lettrePath = null;
    $lettreSafeName = null;
    if ($hasLettre) {
        $lettreOrigName = basename($_FILES['lettre']['name']);
        $lettreSafeName = uniqid('lettre_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $lettreOrigName);
        $lettrePath = $uploadDir . '/' . $lettreSafeName;
        if (!move_uploaded_file($_FILES['lettre']['tmp_name'], $lettrePath)) {
            error_log("⚠️ Erreur upload lettre, on continue sans");
            $lettrePath = null;
        } else {
            error_log("✅ Lettre uploadée: $lettreSafeName");
        }
    }

    // ===== ENVOI EMAIL VIA PHPMAILER =====
    $smtpConfig = require __DIR__ . '/config/smtp.php';

    $mail = new PHPMailer(true);
    $mail->CharSet  = 'UTF-8';
    $mail->isSMTP();
    $mail->Host       = $smtpConfig['host'];
    $mail->Port       = (int)$smtpConfig['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConfig['username'];
    $mail->Password   = $smtpConfig['password'];
    $mail->SMTPSecure = strtolower($smtpConfig['encryption']) === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Timeout    = 30;
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];
    $mail->SMTPDebug  = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP Debug [$level]: " . trim($str));
    };

    $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
    $mail->addAddress('cs@emergencemag.net', 'Cosmos Group - Candidatures Spontanées');
    $mail->addReplyTo($email, $nom);

    $mail->Subject = "Candidature spontanée - $poste ($domaine) - $nom";

    // Corps de l'email
    $body  = "Bonjour,\n\n";
    $body .= "Une nouvelle candidature spontanée a été reçue.\n\n";
    $body .= "=== INFORMATIONS DU CANDIDAT ===\n";
    $body .= "Nom complet   : $nom\n";
    $body .= "Email         : $email\n";
    $body .= "Téléphone     : $telephone\n";
    if ($whatsapp) $body .= "WhatsApp      : $whatsapp\n";
    $body .= "\n=== PROFIL PROFESSIONNEL ===\n";
    $body .= "Domaine       : $domaine\n";
    $body .= "Poste visé    : $poste\n";
    $body .= "Expérience    : $experience\n";
    if ($niveau_etude) $body .= "Niveau étude  : $niveau_etude\n";
    $body .= "\n=== INFORMATIONS CLÉS ===\n";
    if ($salaire) $body .= "Prétention salariale : $salaire\n";
    $body .= "Disponibilité : $disponibilite\n";
    if ($message_text) {
        $body .= "\n=== MESSAGE DU CANDIDAT ===\n$message_text\n";
    }
    $body .= "\n=== DOCUMENTS JOINTS ===\n";
    $body .= "- CV : $cvOrigName\n";
    if ($lettreSafeName) $body .= "- Lettre de motivation : " . basename($_FILES['lettre']['name']) . "\n";
    $body .= "\nDate : " . date('d/m/Y H:i:s') . "\n";
    $body .= "IP   : " . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue') . "\n\n";
    $body .= "Cordialement,\nSystème de candidature Cosmos Group\nhttps://cosmos.emergencemag.net";

    $mail->isHTML(false);
    $mail->Body = $body;

    // Pièces jointes
    $mail->addAttachment($cvPath, $cvOrigName);
    if ($lettrePath && file_exists($lettrePath)) {
        $mail->addAttachment($lettrePath, basename($_FILES['lettre']['name']));
    }

    $mail->send();
    error_log("✅ Email candidature spontanée envoyé vers cs@emergencemag.net");

    // Email de confirmation au candidat
    try {
        $mailConfirm = new PHPMailer(true);
        $mailConfirm->CharSet  = 'UTF-8';
        $mailConfirm->isSMTP();
        $mailConfirm->Host       = $smtpConfig['host'];
        $mailConfirm->Port       = (int)$smtpConfig['port'];
        $mailConfirm->SMTPAuth   = true;
        $mailConfirm->Username   = $smtpConfig['username'];
        $mailConfirm->Password   = $smtpConfig['password'];
        $mailConfirm->SMTPSecure = strtolower($smtpConfig['encryption']) === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mailConfirm->Timeout    = 30;
        $mailConfirm->SMTPOptions = [
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]
        ];
        $mailConfirm->SMTPDebug  = 0;

        $mailConfirm->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mailConfirm->addAddress($email, $nom);

        $mailConfirm->Subject = "Confirmation de votre candidature spontanée - Cosmos Group";

        $confirmBody  = "Bonjour $nom,\n\n";
        $confirmBody .= "Nous avons bien reçu votre candidature spontanée pour le poste de $poste dans le domaine $domaine.\n\n";
        $confirmBody .= "Votre profil a été intégré dans notre vivier de talents. Nos recruteurs l'analyseront et vous contacteront dès qu'une opportunité correspondant à votre profil se présente.\n\n";
        $confirmBody .= "Récapitulatif de votre candidature :\n";
        $confirmBody .= "- Poste visé    : $poste\n";
        $confirmBody .= "- Domaine       : $domaine\n";
        $confirmBody .= "- Expérience    : $experience\n";
        $confirmBody .= "- Disponibilité : $disponibilite\n\n";
        $confirmBody .= "Pour toute question, contactez-nous :\n";
        $confirmBody .= "- Email     : hello@cosmosdrc.com\n";
        $confirmBody .= "- WhatsApp  : +243 98 21 61 066\n\n";
        $confirmBody .= "Cordialement,\nL'équipe Cosmos Group\nhttps://cosmos.emergencemag.net";

        $mailConfirm->isHTML(false);
        $mailConfirm->Body = $confirmBody;
        $mailConfirm->send();
        error_log("✅ Email de confirmation envoyé au candidat: $email");

    } catch (Exception $confirmErr) {
        error_log("⚠️ Erreur confirmation candidat: " . $confirmErr->getMessage());
        // Non bloquant
    }

    error_log("=== FIN CANDIDATURE SPONTANÉE (SUCCÈS) ===");
    send_response(true, "Votre candidature a bien été reçue. Nous vous contacterons dès qu'une opportunité se présente.");

} catch (Exception $e) {
    error_log("❌ ERREUR candidature spontanée: " . $e->getMessage());

    $msg = 'Une erreur est survenue lors du traitement de votre candidature. Veuillez réessayer.';
    if (strpos($e->getMessage(), 'upload') !== false || strpos($e->getMessage(), 'fichier') !== false) {
        $msg = 'Erreur lors du traitement des fichiers. Vérifiez le format et la taille (max 5 Mo).';
    } elseif (strpos($e->getMessage(), 'SMTP') !== false || strpos($e->getMessage(), 'PHPMailer') !== false) {
        $msg = 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer ou nous contacter directement.';
    }

    send_response(false, $msg, ['general' => $e->getMessage()]);
}
