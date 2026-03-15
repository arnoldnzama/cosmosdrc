<?php
/**
 * Classe Validator pour la validation et la sécurité des données
 * Cosmos Group - Système de recrutement
 */

require_once __DIR__ . '/../config/database.php';

class Validator {
    private $errors = [];
    private $data = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Valider toutes les données du formulaire de candidature
     * Version simplifiée : seuls les documents et infos du poste sont requis
     */
    public function validateApplicationForm() {
        $this->errors = [];
        
        // Validation des informations du poste (obligatoires)
        $this->validateRequired('job_title', 'Titre du poste');
        $this->validateRequired('job_company', 'Entreprise');
        $this->validateRequired('job_location', 'Lieu');
        
        // Validation des informations de contact (pour l'email de confirmation uniquement)
        $this->validateRequired('nom_complet', 'Nom complet');
        $this->validateRequired('email', 'Email');
        $this->validateEmail('email');
        
        // Validation des fichiers obligatoires
        $this->validateCVFile();
        $this->validateLettreFile();
        
        return empty($this->errors);
    }
    
    /**
     * Valider le fichier lettre de motivation
     */
    private function validateLettreFile() {
        if (!isset($_FILES['lettre']) || $_FILES['lettre']['error'] !== UPLOAD_ERR_OK) {
            $this->errors['lettre'] = "Veuillez télécharger votre lettre de motivation.";
            return;
        }

        $file = $_FILES['lettre'];
        
        // Vérifier la taille
        if ($file['size'] > MAX_FILE_SIZE) {
            $this->errors['lettre'] = ERROR_MESSAGES['file_too_large'];
            return;
        }

        // Vérifier le type de fichier
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, ALLOWED_FILE_TYPES)) {
            $this->errors['lettre'] = ERROR_MESSAGES['invalid_file_type'];
            return;
        }

        // Vérifier le type MIME
        $mimeType = $this->getMimeType($file['tmp_name']);
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            if (!in_array($extension, ['pdf', 'doc', 'docx'])) {
                $this->errors['lettre'] = ERROR_MESSAGES['invalid_file_type'];
                return;
            }
        }

        // Vérifier les premiers octets pour PDF
        if ($extension === 'pdf' && !$this->isValidPDF($file['tmp_name'])) {
            $this->errors['lettre'] = "Le fichier PDF semble corrompu ou invalide.";
            return;
        }
    }

    /**
     * Valider les données du formulaire de contact
     */
    public function validateContactForm() {
        $this->errors = [];
        
        $this->validateRequired('firstName', 'Prénom');
        $this->validateRequired('lastName', 'Nom');
        $this->validateRequired('email', 'Email');
        $this->validateRequired('subject', 'Sujet');
        $this->validateRequired('message', 'Message');
        
        $this->validateEmail('email');
        
        if (isset($this->data['phone']) && !empty($this->data['phone'])) {
            $this->validatePhone('phone');
        }
        
        return empty($this->errors);
    }

    /**
     * Valider un champ obligatoire
     */
    private function validateRequired($field, $label) {
        if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
            $this->errors[$field] = "Le champ '$label' est obligatoire.";
        }
    }

    /**
     * Valider une adresse email
     */
    private function validateEmail($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $email = filter_var(trim($this->data[$field]), FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = ERROR_MESSAGES['invalid_email'];
            }
        }
    }

    /**
     * Valider un numéro de téléphone
     */
    private function validatePhone($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[^0-9+\-\(\)\s]/', '', $this->data[$field]);
            if (strlen($phone) < 8 || strlen($phone) > 20) {
                $this->errors[$field] = ERROR_MESSAGES['invalid_phone'];
            }
        }
    }

    /**
     * Valider un nom (lettres, espaces, tirets, apostrophes)
     */
    private function validateName($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $name = trim($this->data[$field]);
            if (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]{2,50}$/u', $name)) {
                $this->errors[$field] = "Le nom doit contenir entre 2 et 50 caractères (lettres, espaces, tirets, apostrophes).";
            }
        }
    }

    /**
     * Valider le fichier CV
     */
    private function validateCVFile() {
        if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            $this->errors['cv'] = "Veuillez télécharger votre CV.";
            return;
        }

        $file = $_FILES['cv'];
        
        // Vérifier la taille
        if ($file['size'] > MAX_FILE_SIZE) {
            $this->errors['cv'] = ERROR_MESSAGES['file_too_large'];
            return;
        }

        // Vérifier le type de fichier
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, ALLOWED_FILE_TYPES)) {
            $this->errors['cv'] = ERROR_MESSAGES['invalid_file_type'];
            return;
        }

        // Vérifier le type MIME (accepter PDF, DOC et DOCX)
        $mimeType = $this->getMimeType($file['tmp_name']);
        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            // Vérification supplémentaire basée sur l'extension si le MIME type n'est pas reconnu
            if (!in_array($extension, ['pdf', 'doc', 'docx'])) {
                $this->errors['cv'] = ERROR_MESSAGES['invalid_file_type'];
                return;
            }
        }

        // Vérifier les premiers octets pour s'assurer que c'est un fichier valide
        if ($extension === 'pdf' && !$this->isValidPDF($file['tmp_name'])) {
            $this->errors['cv'] = "Le fichier PDF semble corrompu ou invalide.";
            return;
        }
    }

    /**
     * Obtenir le type MIME d'un fichier
     */
    public function getMimeType($filePath) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType;
        }
        
        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream';
    }

    /**
     * Vérifier si un fichier est un PDF valide
     */
    public function isValidPDF($filePath) {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 8);
        fclose($handle);
        
        return strpos($header, '%PDF-') === 0;
    }

    /**
     * Nettoyer et sécuriser les données
     */
    public function sanitizeData() {
        $sanitized = [];
        
        foreach ($this->data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Obtenir les erreurs de validation
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Vérifier s'il y a des erreurs
     */
    public function hasErrors() {
        return !empty($this->errors);
    }

    /**
     * Valider et traiter le fichier uploadé
     */
    public function processUploadedFile($fileField, $uploadDir) {
        if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $file = $_FILES[$fileField];
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        $safeName = $this->generateSafeFileName($fileInfo['filename'], $extension);
        
        $destination = $uploadDir . '/' . $safeName;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $relativePath = 'uploads/cv/' . $safeName;
            
            return [
                'filename' => $file['name'],
                'path' => $relativePath,
                'absolute_path' => $destination,
                'size' => $file['size'],
                'type' => $file['type']
            ];
        }
        
        return false;
    }

    /**
     * Générer un nom de fichier sécurisé
     */
    private function generateSafeFileName($originalName, $extension) {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $safeName = substr($safeName, 0, 50);
        
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        
        return $safeName . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Nettoyer une chaîne de caractères
     */
    public function sanitizeString($string) {
        $string = trim($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        $string = preg_replace('/[\x00-\x1F\x7F]/', '', $string);
        return $string;
    }
}
?>
