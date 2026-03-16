<?php
/**
 * Classe EmailService pour l'envoi d'emails
 * Cosmos Group - Système de recrutement
 * Multi-fournisseur SMTP : Gmail, Outlook, Yahoo, serveur custom, etc.
 */


// Charger database.php seulement s'il existe et si les constantes ne sont pas déjà définies
if (!defined('SITE_NAME') && file_exists(__DIR__ . '/../config/database.php')) {
    require_once __DIR__ . '/../config/database.php';
}


if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;

class EmailService {
    private $config;
    private const CHANNEL_CANDIDATURE = 'candidature';
    private const CHANNEL_NEWSLETTER = 'newsletter';
    private const CHANNEL_CONTACT = 'contact';

    public function __construct() {
        $this->config = require __DIR__ . '/../config/smtp.php';
    }

    /**
     * Retourne la config du fournisseur pour un canal (multifournisseur)
     */
    private function getProviderConfig($channel) {
        if (!empty($this->config['providers'][$channel])) {
            $p = $this->config['providers'][$channel];
            return [
                'to_email'   => $p['to_email'] ?? null,
                'from_email' => $p['from_email'] ?? $this->config['from_email'],
                'from_name'  => $p['from_name'] ?? $this->config['from_name'],
                'providers'  => $p['providers'] ?? []
            ];
        }
        return [
            'to_email'   => $this->config[$channel === self::CHANNEL_CANDIDATURE ? 'admin_email' : ($channel === self::CHANNEL_CONTACT ? 'contact_email' : 'news_email')] ?? null,
            'from_email' => $this->config['from_email'],
            'from_name'  => $this->config['from_name'],
            'providers'  => []
        ];
    }

    /**
     * Obtient le meilleur fournisseur disponible pour un canal
     */
    private function getBestProvider($channel) {
        $channelConfig = $this->getProviderConfig($channel);
        $providers = $channelConfig['providers'] ?? [];
        
        // Trier par priorité (1 = plus haute priorité)
        usort($providers, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        
        // Retourner le premier fournisseur activé
        foreach ($providers as $provider) {
            if ($provider['enabled'] ?? false) {
                $providerType = $provider['type'] ?? 'custom';
                $baseConfig = $this->config['available_providers'][$providerType] ?? [];
                
                return array_merge($baseConfig, [
                    'type' => $providerType,
                    'username' => $provider['username'] ?? '',
                    'password' => $provider['password'] ?? '',
                    'from_email' => $channelConfig['from_email'],
                    'from_name' => $channelConfig['from_name'],
                    'to_email' => $channelConfig['to_email']
                ]);
            }
        }
        
        // Fallback vers la configuration globale
        return [
            'type' => 'custom',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'username' => $this->config['username'],
            'password' => $this->config['password'],
            'encryption' => $this->config['encryption'],
            'from_email' => $channelConfig['from_email'],
            'from_name' => $channelConfig['from_name'],
            'to_email' => $channelConfig['to_email']
        ];
    }

    /**
     * Obtient tous les fournisseurs disponibles pour un canal (pour le basculement)
     */
    private function getAllProviders($channel) {
        $channelConfig = $this->getProviderConfig($channel);
        $providers = $channelConfig['providers'] ?? [];
        $availableProviders = [];
        
        // Trier par priorité
        usort($providers, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        
        foreach ($providers as $provider) {
            if ($provider['enabled'] ?? false) {
                $providerType = $provider['type'] ?? 'custom';
                $baseConfig = $this->config['available_providers'][$providerType] ?? [];
                
                $availableProviders[] = array_merge($baseConfig, [
                    'type' => $providerType,
                    'username' => $provider['username'] ?? '',
                    'password' => $provider['password'] ?? '',
                    'from_email' => $channelConfig['from_email'],
                    'from_name' => $channelConfig['from_name'],
                    'to_email' => $channelConfig['to_email']
                ]);
            }
        }
        
        // Ajouter le fallback si aucun fournisseur configuré
        if (empty($availableProviders)) {
            $availableProviders[] = [
                'type' => 'custom',
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'username' => $this->config['username'],
                'password' => $this->config['password'],
                'encryption' => $this->config['encryption'],
                'from_email' => $channelConfig['from_email'],
                'from_name' => $channelConfig['from_name'],
                'to_email' => $channelConfig['to_email']
            ];
        }
        
        return $availableProviders;
    }

    /**
     * Envoi un email avec système multi-fournisseur et basculement automatique
     * @param string $channel candidature|newsletter|contact
     * @param string $to Destinataire
     * @param string $subject Sujet
     * @param string $bodyText Corps texte
     * @param string|null $bodyHtml Corps HTML (optionnel)
     * @param array $attachments [ ['path' => chemin, 'name' => nom fichier], ... ]
     * @param string|null $replyTo
     * @param string|null $replyToName
     * @return bool
     */
    private function sendMail($channel, $to, $subject, $bodyText = '', $bodyHtml = null, array $attachments = [], $replyTo = null, $replyToName = null) {
        $options = $this->config['options'] ?? [];
        $enableFallback = $options['enable_fallback'] ?? true;
        $maxRetryAttempts = $options['max_retry_attempts'] ?? 3;
        $retryDelay = $options['retry_delay'] ?? 1;
        $simulateSending = $options['simulate_sending'] ?? false;
        
        // Mode simulation pour le développement
        if ($simulateSending) {
            error_log("📧 [SIMULATION] Email vers: $to");
            error_log("📧 [SIMULATION] Sujet: $subject");
            error_log("📧 [SIMULATION] Canal: $channel");
            return true;
        }
        
        $providers = $this->getAllProviders($channel);
        $lastError = null;
        
        foreach ($providers as $providerIndex => $provider) {
            $providerName = $provider['name'] ?? $provider['type'] ?? 'Inconnu';
            error_log("📧 [Fournisseur $channel] Tentative avec: $providerName");
            
            for ($attempt = 1; $attempt <= $maxRetryAttempts; $attempt++) {
                try {
                    if ($attempt > 1) {
                        error_log("🔄 Tentative $attempt/$maxRetryAttempts avec $providerName");
                        sleep($retryDelay);
                    }
                    
                    $result = $this->sendWithProvider($provider, $to, $subject, $bodyText, $bodyHtml, $attachments, $replyTo, $replyToName);
                    
                    if ($result) {
                        error_log("✅ Email envoyé avec succès via $providerName (tentative $attempt)");
                        return true;
                    }
                    
                } catch (Exception $e) {
                    $lastError = $e->getMessage();
                    error_log("❌ Erreur avec $providerName (tentative $attempt): " . $lastError);
                    
                    // Si c'est une erreur d'authentification, ne pas réessayer avec ce fournisseur
                    if (strpos($lastError, 'authenticate') !== false || strpos($lastError, 'authentication') !== false) {
                        error_log("🚫 Erreur d'authentification avec $providerName, passage au fournisseur suivant");
                        break;
                    }
                }
            }
            
            // Si le basculement est désactivé, arrêter après le premier fournisseur
            if (!$enableFallback) {
                break;
            }
        }
        
        // Tous les fournisseurs ont échoué
        error_log("❌ Échec de tous les fournisseurs pour le canal $channel");
        error_log("❌ Dernière erreur: " . ($lastError ?? 'Aucune erreur spécifique'));
        
        // En dernier recours, essayer la fonction mail() native
        try {
            error_log("🔄 Tentative avec la fonction mail() native en dernier recours");
            return $this->sendViaMailFunction($providers[0] ?? [], $to, $subject, $bodyText, $bodyHtml, $attachments, $replyTo, $replyToName);
        } catch (Exception $e) {
            error_log("❌ Échec final avec mail() native: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoie un email avec un fournisseur spécifique
     */
    private function sendWithProvider($provider, $to, $subject, $bodyText, $bodyHtml, $attachments, $replyTo, $replyToName) {
        $useSmtp = !empty($provider['host']) && !empty($provider['username']) && class_exists('PHPMailer\PHPMailer\PHPMailer');
        
        if ($useSmtp) {
            return $this->sendViaPhpMailer($provider, $to, $subject, $bodyText, $bodyHtml, $attachments, $replyTo, $replyToName);
        } else {
            return $this->sendViaMailFunction($provider, $to, $subject, $bodyText, $bodyHtml, $attachments, $replyTo, $replyToName);
        }
    }

    private function sendViaPhpMailer(array $provider, $to, $subject, $bodyText, $bodyHtml, array $attachments, $replyTo, $replyToName) {
        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            
            // Configuration SMTP
            $mail->Host       = $provider['host'] ?? '';
            $mail->Port       = (int)($provider['port'] ?? 587);
            $mail->SMTPAuth   = $provider['auth_required'] ?? true;
            $mail->Username   = $provider['username'] ?? '';
            $mail->Password   = $provider['password'] ?? '';
            
            // Log de la configuration (sans le mot de passe)
            $providerName = $provider['name'] ?? $provider['type'] ?? 'custom';
            error_log("📧 Configuration SMTP ($providerName):");
            error_log("   Host: " . $mail->Host);
            error_log("   Port: " . $mail->Port);
            error_log("   Username: " . $mail->Username);
            error_log("   From: " . ($provider['from_email'] ?? 'non défini'));
            error_log("   To: " . $to);
            
            // Configuration du chiffrement
            $encryption = strtolower($provider['encryption'] ?? 'tls');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Configuration des timeouts
            $timeout = $this->config['options']['timeout'] ?? 30;
            $mail->Timeout = $timeout;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Debug SMTP (niveau 2 pour voir les détails)
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP Debug [$level]: " . trim($str));
            };
            
            // Expéditeur et destinataire
            $mail->setFrom($provider['from_email'] ?? '', $provider['from_name'] ?? '');
            $mail->addAddress($to);
            
            // Sujet et contenu
            $mail->Subject = $subject;
            if ($bodyHtml !== null && $bodyHtml !== '') {
                $mail->isHTML(true);
                $mail->Body    = $bodyHtml;
                $mail->AltBody = $bodyText;
            } else {
                $mail->isHTML(false);
                $mail->Body = $bodyText;
            }
            
            // Reply-To
            if ($replyTo) {
                $replyName = $replyToName ?? '';
                $mail->addReplyTo($replyTo, $replyName);
            }
            
            // Pièces jointes
            foreach ($attachments as $att) {
                $path = $att['path'] ?? $att[0];
                $name = $att['name'] ?? $att['filename'] ?? basename($path);
                if (file_exists($path)) {
                    $mail->addAttachment($path, $name);
                    error_log("📎 Pièce jointe ajoutée: $name");
                } else {
                    error_log("⚠️ Pièce jointe introuvable: $path");
                }
            }
            
            error_log("📤 Tentative d'envoi via PHPMailer...");
            $mail->send();
            error_log("✅ Email envoyé avec succès via PHPMailer ($providerName)");
            return true;
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $errorMsg = $e->getMessage();
            
            // Messages d'erreur plus explicites selon le fournisseur
            $providerType = $provider['type'] ?? 'custom';
            $providerName = $provider['name'] ?? $providerType;
            
            if (strpos($errorMsg, 'authenticate') !== false) {
                error_log("❌ PHPMailer ($providerName): Erreur d'authentification - Vérifiez username/password");
            } elseif (strpos($errorMsg, 'connect') !== false) {
                error_log("❌ PHPMailer ($providerName): Impossible de se connecter au serveur SMTP");
            } elseif (strpos($errorMsg, 'tls') !== false || strpos($errorMsg, 'ssl') !== false) {
                error_log("❌ PHPMailer ($providerName): Erreur de chiffrement TLS/SSL");
            } else {
                error_log("❌ PHPMailer ($providerName): " . $errorMsg);
            }
            
            throw new Exception("Erreur PHPMailer avec $providerName: " . $errorMsg);
            
        } catch (\Exception $e) {
            error_log("❌ Erreur générale PHPMailer: " . $e->getMessage());
            throw new Exception("Erreur générale lors de l'envoi: " . $e->getMessage());
        }
    }

    private function sendViaMailFunction(array $provider, $to, $subject, $bodyText, $bodyHtml, array $attachments, $replyTo, $replyToName) {
        $headers = $this->buildEmailHeaders($replyTo, $replyToName, $bodyHtml !== null && $bodyHtml !== '', $provider);
        $body = $bodyHtml !== null && $bodyHtml !== '' ? $bodyHtml : $bodyText;
        if (!empty($attachments)) {
            $boundary = md5(time());
            $headers = $this->buildEmailHeadersWithAttachments($boundary, $provider);
            $body = $this->buildMultipartGeneric($bodyText, $attachments, $boundary);
        }
        
        error_log("📧 [mail() PHP] Envoi email vers: $to");
        error_log("📧 [mail() PHP] Sujet: $subject");
        error_log("📧 [mail() PHP] De: " . ($provider['from_email'] ?? 'non défini'));
        
        // Envoi réel via la fonction mail() native de PHP
        $res = @mail($to, $subject, $body, $headers);
        
        if (!$res) {
            error_log("❌ Erreur mail() PHP pour: " . $to);
            throw new Exception("L'envoi de l'email via la fonction standard PHP a échoué.");
        }
        
        error_log("✅ Email envoyé avec succès via mail() PHP");
        return true;
    }

    private function buildMultipartGeneric($bodyText, array $attachments, $boundary) {
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $bodyText . "\r\n\r\n";
        foreach ($attachments as $att) {
            $path = $att['path'] ?? $att[0];
            $name = $att['name'] ?? $att['filename'] ?? basename($path);
            if (file_exists($path)) {
                $message .= $this->attachFile($path, $boundary, $name);
            }
        }
        $message .= "--{$boundary}--\r\n";
        return $message;
    }

    /**
     * Envoyer un email de candidature à l'administration avec pièces jointes (fournisseur candidature)
     */
    public function sendApplicationEmail($applicationData, $cvPath = null, $lettrePath = null) {
        $provider = $this->getProviderConfig(self::CHANNEL_CANDIDATURE);
        $to = $provider['to_email'];
        $subject = 'Nouvelle candidature - ' . $applicationData['job_title'];
        
        error_log("📧 [Fournisseur candidature] Envoi email vers: " . $to);
        error_log("📎 CV Path: " . ($cvPath ?? 'non fourni'));
        error_log("📎 Lettre Path: " . ($lettrePath ?? 'non fournie'));
        
        $bodyText = $this->buildApplicationEmailMessage($applicationData);
        $attachments = [];
        if ($cvPath && file_exists($cvPath)) {
            $attachments[] = ['path' => $cvPath, 'name' => $applicationData['cv_filename'] ?? 'CV.pdf'];
        }
        if ($lettrePath && file_exists($lettrePath)) {
            $attachments[] = ['path' => $lettrePath, 'name' => $applicationData['lettre_filename'] ?? 'Lettre_de_motivation.pdf'];
        }
        
        $result = $this->sendMail(self::CHANNEL_CANDIDATURE, $to, $subject, $bodyText, null, $attachments);
        
        if ($result) {
            error_log("✅ Email candidature envoyé avec succès (avec pièces jointes)");
        } else {
            error_log("❌ Échec envoi email candidature");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de confirmation au candidat (expéditeur = fournisseur candidature)
     */
    public function sendCandidateConfirmation($applicationData) {
        $to = $applicationData['email'];
        $subject = 'Confirmation de votre candidature - Cosmos Group';
        $bodyHtml = $this->buildCandidateConfirmationMessage($applicationData);
        $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        
        error_log("📧 Envoi confirmation candidat vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CANDIDATURE, $to, $subject, $bodyText, $bodyHtml);
        
        if ($result) {
            error_log("✅ Email confirmation envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email confirmation");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de contact à l'administration (fournisseur contact)
     */
    public function sendContactEmail($contactData) {
        $provider = $this->getProviderConfig(self::CHANNEL_CONTACT);
        $to = $provider['to_email'];
        $subject = 'Nouveau message de contact - ' . $contactData['subject'];
        $bodyText = $this->buildContactEmailMessage($contactData);
        $replyTo = $contactData['email'];
        $replyToName = ($contactData['firstName'] ?? '') . ' ' . ($contactData['lastName'] ?? '');
        
        error_log("📧 [Fournisseur contact] Envoi email vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, null, [], $replyTo, trim($replyToName));
        
        if ($result) {
            error_log("✅ Email contact envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email contact");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de confirmation de contact (expéditeur = fournisseur contact)
     */
    public function sendContactConfirmation($contactData) {
        $to = $contactData['email'];
        $subject = 'Confirmation de réception - Cosmos Group';
        $bodyHtml = $this->buildContactConfirmationMessage($contactData);
        $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        
        error_log("📧 Envoi confirmation contact vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, $bodyHtml);
        
        if ($result) {
            error_log("✅ Email confirmation contact envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email confirmation contact");
        }
        
        return $result;
    }

    /**
     * Envoyer un email d'abonnement newsletter (fournisseur newsletter)
     */
    public function sendNewsletterEmail($email) {
        $provider = $this->getProviderConfig(self::CHANNEL_NEWSLETTER);
        $to = $provider['to_email'];
        $subject = 'Nouvel abonnement newsletter - Cosmos Group';
        $bodyText = "Nouvel abonnement à la newsletter\n\nEmail: " . $email . "\nDate: " . date('d/m/Y H:i:s') . "\n";
        
        error_log("📧 [Fournisseur newsletter] Envoi email vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_NEWSLETTER, $to, $subject, $bodyText);
        
        if ($result) {
            error_log("✅ Email newsletter envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email newsletter");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de confirmation d'abonnement newsletter (expéditeur = fournisseur newsletter)
     */
    public function sendNewsletterConfirmation($email) {
        $provider = $this->getProviderConfig(self::CHANNEL_NEWSLETTER);
        $to = $email;
        $subject = 'Confirmation d\'abonnement - Newsletter Cosmos Group';
        
        $emailSafe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $newsletterEmail = htmlspecialchars($provider['to_email'], ENT_QUOTES, 'UTF-8');
        
        $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation Newsletter</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <img src="' . SITE_URL . '/logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png" alt="Cosmos Group" style="height: 42px; margin-bottom: 15px;" />
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Cosmos Group</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Newsletter</p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <span style="color: #ffffff; font-size: 48px; line-height: 80px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; text-align: center;">Inscription confirmée !</h2>
                            
                            <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Bonjour,
                            </p>
                            
                            <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Merci pour votre inscription à la newsletter Cosmos Group ! Vous recevrez désormais nos dernières offres d\'emploi et actualités directement dans votre boîte mail.
                            </p>
                            
                            <!-- Benefits Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">Ce que vous recevrez :</h3>
                                        
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                                    📧 Les dernières offres d\'emploi
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                                    💼 Conseils carrière et recrutement
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                                    🎯 Actualités du marché de l\'emploi
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #4b5563; font-size: 14px; line-height: 1.6;">
                                                     Événements et formations
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Important Notice -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                    <strong>⚠️ Important :</strong><br>
                                    Pensez à vérifier votre dossier "Spam" ou "Promotions" pour ne rien manquer de nos communications.
                                </p>
                            </div>
                            
                            <p style="margin: 25px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; text-align: center;">
                                Si vous ne vous êtes pas inscrit ou souhaitez vous désabonner,<br>
                                contactez-nous à <a href="mailto:' . $newsletterEmail . '" style="color: #667eea; text-decoration: none;">' . $newsletterEmail . '</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                L\'équipe Cosmos Group
                            </p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                                39 Boulevard du 30 juin, Gombe/Kinshasa
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="' . SITE_URL . '" style="color: #667eea; text-decoration: none; font-size: 14px;">' . SITE_URL . '</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        error_log("📧 Envoi confirmation newsletter vers: " . $to);
        
        $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        $result = $this->sendMail(self::CHANNEL_NEWSLETTER, $to, $subject, $bodyText, $message);
        
        if ($result) {
            error_log("✅ Email confirmation newsletter envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email confirmation newsletter");
        }
        
        return $result;
    }

    /**
     * Construire le message email pour une candidature
     * Version simplifiée : focus sur le poste et les documents
     */
    private function buildApplicationEmailMessage($data) {
        $message = "Bonjour,\n\n";
        $message .= "Une nouvelle candidature a été reçue pour le poste : " . $data['job_title'] . "\n\n";
        
        $message .= "Informations du poste :\n";
        $message .= "Titre : " . $data['job_title'] . "\n";
        $message .= "Entreprise : " . $data['job_company'] . "\n";
        $message .= "Lieu : " . $data['job_location'] . "\n";
        if (!empty($data['job_description'])) {
            $message .= "Description : " . substr($data['job_description'], 0, 200) . "...\n";
        }
        $message .= "\n";
        
        $message .= "Documents joints :\n";
        $message .= "- CV : " . ($data['cv_filename'] ?? 'CV.pdf') . "\n";
        $message .= "- Lettre de motivation : " . ($data['lettre_filename'] ?? 'Lettre.pdf') . "\n";
        $message .= "\n";
        
        $message .= "ℹ️ Les informations complètes du candidat (nom, email, téléphone, etc.) sont disponibles dans les documents joints.\n\n";
        
        $message .= "Date de candidature : " . date('d/m/Y H:i:s') . "\n";
        if (!empty($data['ip_address'])) {
            $message .= "IP : " . $data['ip_address'] . "\n";
        }
        $message .= "\n";
        
        $message .= "Cordialement,\n";
        $message .= "Système de candidature " . SITE_NAME . "\n";
        $message .= SITE_URL;
        
        return $message;
    }

    /**
     * Construire le message de confirmation pour le candidat
     */
    private function buildCandidateConfirmationMessage($data) {
        $nomComplet = htmlspecialchars($data['nom_complet'], ENT_QUOTES, 'UTF-8');
        $jobTitle = htmlspecialchars($data['job_title'], ENT_QUOTES, 'UTF-8');
        $jobCompany = htmlspecialchars($data['job_company'], ENT_QUOTES, 'UTF-8');
        $jobLocation = htmlspecialchars($data['job_location'], ENT_QUOTES, 'UTF-8');
        $date = date('d/m/Y à H:i');
        
        $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de candidature</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <img src="' . SITE_URL . '/logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png" alt="Cosmos Group" style="height: 42px; margin-bottom: 15px;" />
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Cosmos Group</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Votre partenaire RH de confiance</p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <span style="color: #ffffff; font-size: 48px; line-height: 80px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; text-align: center;">Candidature bien reçue !</h2>
                            
                            <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Bonjour <strong>' . $nomComplet . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Nous avons bien reçu votre candidature et nous vous en remercions. Votre profil a été enregistré avec succès et sera examiné attentivement par notre équipe de recrutement.
                            </p>
                            
                            <!-- Application Details Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">Détails de votre candidature</h3>
                                        
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 120px;">
                                                    <strong>Poste :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $jobTitle . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Entreprise :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $jobCompany . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Lieu :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $jobLocation . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Date :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $date . '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;">
                                    <strong>📋 Prochaines étapes :</strong><br>
                                    Notre équipe RH examinera votre candidature dans les plus brefs délais. Si votre profil correspond à nos besoins, nous vous recontacterons pour un entretien.
                                </p>
                            </div>
                            
                            <p style="margin: 25px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Nous vous remercions de l\'intérêt que vous portez à notre entreprise et vous souhaitons bonne chance pour la suite du processus de recrutement.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                L\'équipe RH de Cosmos Group
                            </p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                                39 Boulevard du 30 juin, Gombe/Kinshasa
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="' . SITE_URL . '" style="color: #667eea; text-decoration: none; font-size: 14px;">' . SITE_URL . '</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $message;
    }

    /**
     * Construire le message email pour un contact
     */
    private function buildContactEmailMessage($data) {
        $message = "Nouveau message via le formulaire de contact\n\n";
        $message .= "Nom: " . $data['firstName'] . " " . $data['lastName'] . "\n";
        $message .= "Email: " . $data['email'] . "\n";
        $message .= "Téléphone: " . ($data['phone'] ?? '(non fourni)') . "\n";
        $message .= "Sujet: " . $data['subject'] . "\n\n";
        $message .= "Message:\n" . $data['message'] . "\n\n";
        $message .= "Newsletter: " . (isset($data['newsletter']) && $data['newsletter'] ? 'Oui' : 'Non') . "\n";
        $message .= "Date: " . date('d/m/Y H:i:s') . "\n";
        
        return $message;
    }

    /**
     * Construire le message de confirmation de contact
     */
    private function buildContactConfirmationMessage($data) {
        $firstName = htmlspecialchars($data['firstName'], ENT_QUOTES, 'UTF-8');
        $subject = htmlspecialchars($data['subject'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8');
        $message = nl2br($message);
        $date = date('d/m/Y à H:i');
        
        $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de contact</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <img src="' . SITE_URL . '/logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png" alt="Cosmos Group" style="height: 42px; margin-bottom: 15px;" />
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Cosmos Group</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Votre partenaire RH de confiance</p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <span style="color: #ffffff; font-size: 48px; line-height: 80px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; text-align: center;">Message bien reçu !</h2>
                            
                            <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Bonjour <strong>' . $firstName . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Merci de nous avoir contactés. Votre message a bien été reçu et enregistré. Notre équipe vous répondra dans les plus brefs délais.
                            </p>
                            
                            <!-- Message Details Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">Récapitulatif de votre message</h3>
                                        
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 100px; vertical-align: top;">
                                                    <strong>Sujet :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $subject . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px; vertical-align: top;">
                                                    <strong>Date :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $date . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0 0 0; color: #6b7280; font-size: 14px; vertical-align: top;" colspan="2">
                                                    <strong>Votre message :</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #4b5563; font-size: 14px; line-height: 1.6;" colspan="2">
                                                    ' . $message . '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Response Time Info -->
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;">
                                    <strong>⏱️ Délai de réponse :</strong><br>
                                    Nous nous efforçons de répondre à tous les messages dans un délai de 24 à 48 heures ouvrables.
                                </p>
                            </div>
                            
                            <p style="margin: 25px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Si votre demande est urgente, n\'hésitez pas à nous contacter directement par téléphone.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                L\'équipe Cosmos Group
                            </p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                                39 Boulevard du 30 juin, Gombe/Kinshasa
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="' . SITE_URL . '" style="color: #667eea; text-decoration: none; font-size: 14px;">' . SITE_URL . '</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $html;
    }

    /**
     * Construire les en-têtes email (multifournisseur : optionnel provider pour From)
     */
    private function buildEmailHeaders($replyTo = null, $replyToName = null, $isHtml = false, ?array $providerConfig = null) {
        $headers = [];
        
        $fromEmail = ($providerConfig && isset($providerConfig['from_email'])) ? $providerConfig['from_email'] : $this->config['from_email'];
        $fromName  = ($providerConfig && isset($providerConfig['from_name'])) ? $providerConfig['from_name'] : $this->config['from_name'];
        
        $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
        
        if ($replyTo) {
            $headers[] = "Reply-To: " . ($replyToName ? $replyToName . " <" . $replyTo . ">" : $replyTo);
        } else {
            $headers[] = "Reply-To: " . $fromEmail;
        }
        
        $headers[] = "X-Mailer: PHP/" . phpversion();
        
        if ($isHtml) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
        } else {
            $headers[] = "Content-Type: text/plain; charset=UTF-8";
        }
        
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "MIME-Version: 1.0";
        
        return implode("\r\n", $headers);
    }

    /**
     * Construire les en-têtes email avec support des pièces jointes (multifournisseur)
     */
    private function buildEmailHeadersWithAttachments($boundary, ?array $providerConfig = null) {
        $headers = [];
        
        $fromEmail = ($providerConfig && isset($providerConfig['from_email'])) ? $providerConfig['from_email'] : $this->config['from_email'];
        $fromName  = ($providerConfig && isset($providerConfig['from_name'])) ? $providerConfig['from_name'] : $this->config['from_name'];
        
        $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
        $headers[] = "Reply-To: " . $fromEmail;
        $headers[] = "X-Mailer: PHP/" . phpversion();
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        
        return implode("\r\n", $headers);
    }

    /**
     * Construire un message multipart avec pièces jointes
     */
    private function buildMultipartMessage($applicationData, $cvPath, $lettrePath, $boundary) {
        $message = "";
        
        // Partie texte du message
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $this->buildApplicationEmailMessage($applicationData);
        $message .= "\r\n\r\n";
        
        // Pièce jointe : CV
        if ($cvPath && file_exists($cvPath)) {
            $message .= $this->attachFile($cvPath, $boundary, $applicationData['cv_filename'] ?? 'CV.pdf');
        } else {
            error_log("⚠️ Fichier CV introuvable: " . ($cvPath ?? 'null'));
        }
        
        // Pièce jointe : Lettre de motivation (si présente)
        if ($lettrePath && file_exists($lettrePath)) {
            $message .= $this->attachFile($lettrePath, $boundary, $applicationData['lettre_filename'] ?? 'Lettre_de_motivation.pdf');
        } else {
            error_log("ℹ️ Pas de lettre de motivation ou fichier introuvable");
        }
        
        // Fin du message
        $message .= "--{$boundary}--\r\n";
        
        return $message;
    }

    /**
     * Attacher un fichier au message email
     */
    private function attachFile($filePath, $boundary, $filename) {
        if (!file_exists($filePath)) {
            error_log("❌ Fichier introuvable pour attachement: " . $filePath);
            return "";
        }
        
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            error_log("❌ Impossible de lire le fichier: " . $filePath);
            return "";
        }
        
        $encodedContent = chunk_split(base64_encode($fileContent));
        
        // Déterminer le type MIME (finfo est libéré automatiquement en PHP 8.1+)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $filePath) : false;
        } else {
            $mimeType = false;
        }
        
        if (!$mimeType) {
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeType = $this->getMimeTypeByExtension($extension);
        }
        
        $attachment = "";
        $attachment .= "--{$boundary}\r\n";
        $attachment .= "Content-Type: {$mimeType}; name=\"{$filename}\"\r\n";
        $attachment .= "Content-Transfer-Encoding: base64\r\n";
        $attachment .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
        $attachment .= $encodedContent . "\r\n";
        
        error_log("✅ Fichier attaché: " . $filename . " (" . $mimeType . ")");
        
        return $attachment;
    }

    /**
     * Obtenir le type MIME par extension
     */
    private function getMimeTypeByExtension($extension) {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Valider une adresse email
     */
    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Envoyer un email d'inscription Academy à l'administration (academie@emergencemag.net)
     */
    public function sendAcademyEmail($academyData) {
        // Utiliser le canal contact mais avec une adresse spécifique
        $to = 'academie@emergencemag.net';
        $subject = 'Nouvelle inscription Cosmos Academy - ' . $academyData['programme'];
        $bodyText = $this->buildAcademyEmailMessage($academyData);
        $replyTo = $academyData['email'];
        $replyToName = $academyData['nom_prenom'];
        
        error_log("📧 [Academy] Envoi email vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, null, [], $replyTo, trim($replyToName));
        
        if ($result) {
            error_log("✅ Email Academy envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email Academy");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de confirmation d'inscription Academy
     */
    public function sendAcademyConfirmation($academyData) {
        $to = $academyData['email'];
        $subject = 'Confirmation d\'inscription - Cosmos Academy';
        $bodyHtml = $this->buildAcademyConfirmationMessage($academyData);
        $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        
        error_log("📧 Envoi confirmation Academy vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, $bodyHtml);
        
        if ($result) {
            error_log("✅ Email confirmation Academy envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email confirmation Academy");
        }
        
        return $result;
    }

    /**
     * Construire le message email pour une inscription Academy
     */
    private function buildAcademyEmailMessage($data) {
        $programmeLabels = [
            'orientation' => 'Orientation académique et choix de carrière',
            'formations_pratiques' => 'Formations pratiques et compétences essentielles',
            'marche_travail' => 'Préparation au marché du travail',
            'employabilite' => 'Employabilité et accompagnement professionnel',
            'developpement_personnel' => 'Développement personnel et leadership',
            'immersion' => 'Immersion professionnelle et stages'
        ];
        
        $niveauLabels = [
            '5eme_humanite' => '5ᵉ Humanité',
            '6eme_humanite' => '6ᵉ Humanité',
            'bac' => 'Baccalauréat',
            'licence' => 'Licence / Bachelor',
            'master' => 'Master',
            'autre' => 'Autre'
        ];
        
        $message = "Nouvelle inscription à Cosmos Academy\n\n";
        $message .= "=== INFORMATIONS DU CANDIDAT ===\n\n";
        $message .= "Nom et prénom : " . $data['nom_prenom'] . "\n";
        $message .= "Âge : " . $data['age'] . " ans\n";
        $message .= "Email : " . $data['email'] . "\n";
        $message .= "Téléphone : " . $data['whatsapp'] . "\n";
        $message .= "Ville : " . $data['ville'] . "\n\n";
        
        $message .= "=== PARCOURS ACADÉMIQUE ===\n\n";
        $message .= "Niveau d'étude : " . ($niveauLabels[$data['niveau_etude']] ?? $data['niveau_etude']) . "\n";
        if (!empty($data['etablissement'])) {
            $message .= "Établissement : " . $data['etablissement'] . "\n";
        }
        $message .= "\n";
        
        $message .= "=== PROGRAMME SOUHAITÉ ===\n\n";
        $message .= $programmeLabels[$data['programme']] ?? $data['programme'];
        $message .= "\n\n";
        
        if (!empty($data['motivation'])) {
            $message .= "=== MOTIVATION ===\n\n";
            $message .= $data['motivation'] . "\n\n";
        }
        
        $message .= "Date d'inscription : " . date('d/m/Y H:i:s') . "\n\n";
        
        $message .= "---\n";
        $message .= "Cosmos Academy\n";
        $message .= SITE_URL . "\n";
        
        return $message;
    }

    /**
     * Construire le message de confirmation pour l'inscription Academy
     */
    private function buildAcademyConfirmationMessage($data) {
        $nomPrenom = htmlspecialchars($data['nom_prenom'], ENT_QUOTES, 'UTF-8');
        
        $programmeLabels = [
            'orientation' => 'Orientation académique et choix de carrière',
            'formations_pratiques' => 'Formations pratiques et compétences essentielles',
            'marche_travail' => 'Préparation au marché du travail',
            'employabilite' => 'Employabilité et accompagnement professionnel',
            'developpement_personnel' => 'Développement personnel et leadership',
            'immersion' => 'Immersion professionnelle et stages'
        ];
        
        $programme = htmlspecialchars($programmeLabels[$data['programme']] ?? $data['programme'], ENT_QUOTES, 'UTF-8');
        $date = date('d/m/Y à H:i');
        
        $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation inscription Academy</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center;">
                            <img src="' . SITE_URL . '/logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png" alt="Cosmos Group" style="height: 42px; margin-bottom: 15px;" />
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Cosmos Academy</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Programme d\'accompagnement des jeunes</p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <span style="color: #ffffff; font-size: 48px; line-height: 80px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; text-align: center;">Inscription confirmée !</h2>
                            
                            <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Bonjour <strong>' . $nomPrenom . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Nous avons bien reçu votre inscription à Cosmos Academy et nous vous en remercions. Votre candidature sera examinée attentivement par notre équipe.
                            </p>
                            
                            <!-- Program Details Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">Programme sélectionné</h3>
                                        
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    📚 ' . $programme . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    Date d\'inscription : ' . $date . '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;">
                                    <strong>📋 Prochaines étapes :</strong><br>
                                    Notre équipe vous contactera dans les plus brefs délais pour vous communiquer les détails du programme, les dates de formation et les modalités pratiques.
                                </p>
                            </div>
                            
                            <!-- Contact Info -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                    <strong>📞 Besoin d\'informations ?</strong><br>
                                    N\'hésitez pas à nous contacter :<br>
                                    Email : <a href="mailto:academie@emergencemag.net" style="color: #92400e; text-decoration: underline;">academie@emergencemag.net</a><br>
                                    Téléphone : +243 98 21 61 066 / +243 82 07 07 070
                                </p>
                            </div>
                            
                            <p style="margin: 25px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Nous sommes ravis de vous accompagner dans votre parcours professionnel et nous vous souhaitons plein succès dans cette aventure !
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                L\'équipe Cosmos Academy
                            </p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                                39 Boulevard du 30 juin, Gombe/Kinshasa
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="' . SITE_URL . '" style="color: #667eea; text-decoration: none; font-size: 14px;">' . SITE_URL . '</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $message;
    }

    /**
     * Envoyer un email de demande de recrutement à l'administration (recruter@emergencemag.net)
     */
    public function sendRecruiterEmail($recruiterData) {
        $to = 'recruter@emergencemag.net';
        $subject = 'Nouvelle demande de recrutement - ' . $recruiterData['intitule_poste'];
        $bodyText = $this->buildRecruiterEmailMessage($recruiterData);
        $replyTo = $recruiterData['email'];
        $replyToName = $recruiterData['nom_responsable'];
        
        error_log("📧 [Recruteur] Envoi email vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, null, [], $replyTo, trim($replyToName));
        
        if ($result) {
            error_log("✅ Email Recruteur envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email Recruteur");
        }
        
        return $result;
    }

    /**
     * Envoyer un email de confirmation de demande de recrutement
     */
    public function sendRecruiterConfirmation($recruiterData) {
        $to = $recruiterData['email'];
        $subject = 'Confirmation de votre demande de recrutement - Cosmos Group';
        $bodyHtml = $this->buildRecruiterConfirmationMessage($recruiterData);
        $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));
        
        error_log("📧 Envoi confirmation Recruteur vers: " . $to);
        
        $result = $this->sendMail(self::CHANNEL_CONTACT, $to, $subject, $bodyText, $bodyHtml);
        
        if ($result) {
            error_log("✅ Email confirmation Recruteur envoyé avec succès");
        } else {
            error_log("❌ Échec envoi email confirmation Recruteur");
        }
        
        return $result;
    }

    /**
     * Construire le message email pour une demande de recrutement
     */
    private function buildRecruiterEmailMessage($data) {
        $typeRecrutementLabels = [
            'recrutement_permanent' => 'Recrutement permanent',
            'chasse_tete' => 'Chasse de têtes',
            'externalisation_rh' => 'Externalisation RH',
            'conseil_rh' => 'Conseil RH'
        ];
        
        $niveauLabels = [
            'junior' => 'Junior (0-2 ans)',
            'intermediaire' => 'Intermédiaire (3-5 ans)',
            'senior' => 'Senior (5-10 ans)',
            'expert' => 'Expert (10+ ans)'
        ];
        
        $message = "Nouvelle demande de recrutement\n\n";
        $message .= "=== INFORMATIONS ENTREPRISE ===\n\n";
        $message .= "Entreprise : " . $data['entreprise'] . "\n";
        $message .= "Responsable : " . $data['nom_responsable'] . "\n";
        $message .= "Fonction : " . $data['fonction'] . "\n";
        $message .= "Email : " . $data['email'] . "\n";
        $message .= "Téléphone : " . $data['telephone'] . "\n";
        $message .= "Ville/Pays : " . $data['ville_pays'] . "\n\n";
        
        $message .= "=== TYPE DE RECRUTEMENT ===\n\n";
        foreach ($data['type_recrutement'] as $type) {
            $message .= "• " . ($typeRecrutementLabels[$type] ?? $type) . "\n";
        }
        $message .= "\n";
        
        $message .= "=== INFORMATIONS SUR LE POSTE ===\n\n";
        $message .= "Intitulé du poste : " . $data['intitule_poste'] . "\n";
        $message .= "Secteur d'activité : " . $data['secteur_activite'] . "\n";
        $message .= "Localisation : " . $data['localisation'] . "\n";
        $message .= "Niveau d'expérience : " . ($niveauLabels[$data['niveau_experience']] ?? $data['niveau_experience']) . "\n";
        $message .= "Nombre de postes : " . $data['nombre_postes'] . "\n\n";
        
        $message .= "=== DESCRIPTION DU BESOIN ===\n\n";
        $message .= $data['description_besoin'] . "\n\n";
        
        $message .= "---\n";
        $message .= "Date de la demande : " . date('d/m/Y à H:i') . "\n";
        
        return $message;
    }

    /**
     * Construire le message de confirmation pour l'entreprise
     */
    private function buildRecruiterConfirmationMessage($data) {
        $entreprise = htmlspecialchars($data['entreprise'], ENT_QUOTES, 'UTF-8');
        $nomResponsable = htmlspecialchars($data['nom_responsable'], ENT_QUOTES, 'UTF-8');
        $intitulePoste = htmlspecialchars($data['intitule_poste'], ENT_QUOTES, 'UTF-8');
        $secteurActivite = htmlspecialchars($data['secteur_activite'], ENT_QUOTES, 'UTF-8');
        $localisation = htmlspecialchars($data['localisation'], ENT_QUOTES, 'UTF-8');
        $nombrePostes = htmlspecialchars($data['nombre_postes'], ENT_QUOTES, 'UTF-8');
        $date = date('d/m/Y à H:i');
        
        $typeRecrutementLabels = [
            'recrutement_permanent' => 'Recrutement permanent',
            'chasse_tete' => 'Chasse de têtes',
            'externalisation_rh' => 'Externalisation RH',
            'conseil_rh' => 'Conseil RH'
        ];
        
        $typesRecrutement = '';
        foreach ($data['type_recrutement'] as $type) {
            $typesRecrutement .= '<li style="padding: 4px 0; color: #4b5563; font-size: 14px;">' . ($typeRecrutementLabels[$type] ?? $type) . '</li>';
        }
        
        $message = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de demande de recrutement</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #064795 0%, #3fa7de 100%); padding: 40px 30px; text-align: center;">
                            <img src="' . SITE_URL . '/logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png" alt="Cosmos Group" style="height: 42px; margin-bottom: 15px;" />
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600;">Cosmos Group</h1>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 14px; opacity: 0.9;">Votre partenaire RH de confiance</p>
                        </td>
                    </tr>
                    
                    <!-- Success Icon -->
                    <tr>
                        <td style="padding: 30px 30px 20px 30px; text-align: center;">
                            <div style="width: 80px; height: 80px; margin: 0 auto; background-color: #10b981; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;">
                                <span style="color: #ffffff; font-size: 48px; line-height: 80px;">✓</span>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 24px; text-align: center;">Demande bien reçue !</h2>
                            
                            <p style="margin: 0 0 20px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Bonjour <strong>' . $nomResponsable . '</strong>,
                            </p>
                            
                            <p style="margin: 0 0 25px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Nous avons bien reçu votre demande de recrutement pour <strong>' . $entreprise . '</strong>. Notre équipe d\'experts RH va analyser vos besoins et vous contactera dans les 48 heures pour vous proposer les meilleurs profils.
                            </p>
                            
                            <!-- Request Details Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f9fafb; border-radius: 8px; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <h3 style="margin: 0 0 15px 0; color: #1f2937; font-size: 18px;">Récapitulatif de votre demande</h3>
                                        
                                        <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px; width: 150px;">
                                                    <strong>Poste recherché :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $intitulePoste . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Secteur :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $secteurActivite . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Localisation :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $localisation . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Nombre de postes :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $nombrePostes . '
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 12px 0 8px 0; color: #6b7280; font-size: 14px; vertical-align: top;">
                                                    <strong>Type de service :</strong>
                                                </td>
                                                <td style="padding: 12px 0 8px 0; color: #1f2937; font-size: 14px;">
                                                    <ul style="margin: 0; padding-left: 20px;">
                                                        ' . $typesRecrutement . '
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #6b7280; font-size: 14px;">
                                                    <strong>Date :</strong>
                                                </td>
                                                <td style="padding: 8px 0; color: #1f2937; font-size: 14px;">
                                                    ' . $date . '
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Next Steps -->
                            <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #1e40af; font-size: 14px; line-height: 1.6;">
                                    <strong>📋 Prochaines étapes :</strong><br>
                                    • Analyse de votre besoin par notre équipe RH<br>
                                    • Identification des profils correspondants dans notre base de 100 000+ candidats<br>
                                    • Contact sous 48h pour vous présenter les candidats sélectionnés
                                </p>
                            </div>
                            
                            <!-- Stats Box -->
                            <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 25px 0;">
                                <tr>
                                    <td style="padding: 15px; background-color: #fef3c7; border-radius: 8px; text-align: center; width: 33%;">
                                        <div style="font-size: 24px; font-weight: 700; color: #92400e;">100K+</div>
                                        <div style="font-size: 12px; color: #92400e;">Profils qualifiés</div>
                                    </td>
                                    <td style="width: 2%;"></td>
                                    <td style="padding: 15px; background-color: #dbeafe; border-radius: 8px; text-align: center; width: 33%;">
                                        <div style="font-size: 24px; font-weight: 700; color: #1e40af;">600+</div>
                                        <div style="font-size: 12px; color: #1e40af;">Recrutements réussis</div>
                                    </td>
                                    <td style="width: 2%;"></td>
                                    <td style="padding: 15px; background-color: #d1fae5; border-radius: 8px; text-align: center; width: 33%;">
                                        <div style="font-size: 24px; font-weight: 700; color: #065f46;">48h</div>
                                        <div style="font-size: 12px; color: #065f46;">Délai de réponse</div>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Contact Info -->
                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 25px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                    <strong>📞 Besoin d\'informations ?</strong><br>
                                    N\'hésitez pas à nous contacter :<br>
                                    Email : <a href="mailto:recruter@emergencemag.net" style="color: #92400e; text-decoration: underline;">recruter@emergencemag.net</a><br>
                                    Téléphone : +243 98 21 61 066 / +243 82 07 07 070
                                </p>
                            </div>
                            
                            <p style="margin: 25px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.6;">
                                Merci de votre confiance. Nous sommes impatients de vous accompagner dans votre recherche de talents !
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 10px 0; color: #1f2937; font-size: 16px; font-weight: 600;">
                                L\'équipe Cosmos Group
                            </p>
                            <p style="margin: 0 0 15px 0; color: #6b7280; font-size: 14px;">
                                39 Boulevard du 30 juin, Gombe/Kinshasa
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="' . SITE_URL . '" style="color: #667eea; text-decoration: none; font-size: 14px;">' . SITE_URL . '</a>
                            </p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        return $message;
    }
}
?>
