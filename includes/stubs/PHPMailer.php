<?php
/**
 * Stub pour l'IDE lorsque phpmailer/phpmailer n'est pas installé (vendor absent).
 * Ne pas inclure ce fichier en production : le vrai PHPMailer est chargé via Composer.
 *
 * @see vendor/phpmailer/phpmailer/src/PHPMailer.php
 */
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public const ENCRYPTION_SMTPS = 'ssl';
    public const ENCRYPTION_STARTTLS = 'tls';

    /** @var string */
    public $CharSet;
    /** @var string */
    public $Host;
    /** @var int */
    public $Port;
    /** @var bool */
    public $SMTPAuth;
    /** @var string */
    public $Username;
    /** @var string */
    public $Password;
    /** @var string */
    public $SMTPSecure;
    /** @var string */
    public $Subject;
    /** @var string */
    public $Body;
    /** @var string */
    public $AltBody;

    public function __construct($exceptions = null)
    {
    }

    /** @return self */
    public function isSMTP() { return $this; }

    /** @return self */
    public function setFrom($email, $name = '') { return $this; }

    /** @return self */
    public function addAddress($address, $name = '') { return $this; }

    /** @return self */
    public function isHTML($isHtml = true) { return $this; }

    /** @return self */
    public function addReplyTo($address, $name = '') { return $this; }

    /** @return self */
    public function addAttachment($path, $name = '') { return $this; }

    /** @return bool */
    public function send() { return true; }
}
