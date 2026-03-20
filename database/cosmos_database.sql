-- ============================================================================
-- Base de données COSMOS Group - Système de recrutement
-- Version: 3.0 Simplifié
-- Date: 11 février 2026
-- Description: Schéma adapté au formulaire modal simplifié
-- ============================================================================

-- ============================================================================
-- Configuration de la base de données
-- ============================================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- Encodage UTF-8
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- ============================================================================
-- Création de la base de données (optionnel - décommenter si nécessaire)
-- ============================================================================
-- CREATE DATABASE IF NOT EXISTS `cosmos_recruitment` 
--   DEFAULT CHARACTER SET utf8mb4 
--   COLLATE utf8mb4_unicode_ci;
-- USE `cosmos_recruitment`;

-- ============================================================================
-- Table principale : job_applications
-- Stocke les candidatures avec documents et informations du poste
-- ============================================================================
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  
  -- Documents de candidature (OBLIGATOIRES)
  `cv_filename` varchar(255) NOT NULL COMMENT 'Nom original du fichier CV',
  `cv_path` varchar(500) NOT NULL COMMENT 'Chemin relatif du CV stocké',
  `lettre_filename` varchar(255) NOT NULL COMMENT 'Nom original de la lettre de motivation',
  `lettre_path` varchar(500) NOT NULL COMMENT 'Chemin relatif de la lettre stockée',
  
  -- Informations sur le poste (OBLIGATOIRES)
  `job_title` varchar(255) NOT NULL COMMENT 'Titre du poste',
  `job_company` varchar(255) NOT NULL COMMENT 'Nom de l\'entreprise',
  `job_location` varchar(255) NOT NULL COMMENT 'Localisation du poste',
  `job_description` text DEFAULT NULL COMMENT 'Description complète du poste',
  
  -- Statut et suivi
  `status` enum('nouveau','en_cours','accepte','refuse') NOT NULL DEFAULT 'nouveau' COMMENT 'Statut de la candidature',
  
  -- Informations RH complémentaires
  `disponibilite` varchar(50) DEFAULT NULL COMMENT 'Disponibilité / préavis du candidat',
  `pretention_salariale` decimal(10,2) DEFAULT NULL COMMENT 'Prétention salariale en USD',
  
  -- Informations techniques
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Adresse IP du candidat',
  `user_agent` text DEFAULT NULL COMMENT 'User agent du navigateur',
  
  -- Horodatage
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date de création',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date de dernière modification',
  
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_job_title` (`job_title`),
  KEY `idx_job_company` (`job_company`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Candidatures avec CV et lettre de motivation';

-- ============================================================================
-- Table : email_logs (optionnel)
-- Logs des emails envoyés
-- ============================================================================
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) DEFAULT NULL COMMENT 'ID de la candidature associée',
  `email_type` varchar(50) NOT NULL COMMENT 'Type d\'email (notification, confirmation)',
  `recipient_email` varchar(255) NOT NULL COMMENT 'Email du destinataire',
  `subject` varchar(255) NOT NULL COMMENT 'Sujet de l\'email',
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent' COMMENT 'Statut de l\'envoi',
  `error_message` text DEFAULT NULL COMMENT 'Message d\'erreur si échec',
  `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date d\'envoi',
  
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_status` (`status`),
  KEY `idx_sent_at` (`sent_at`),
  
  CONSTRAINT `fk_email_logs_application` 
    FOREIGN KEY (`application_id`) 
    REFERENCES `job_applications` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Logs des emails envoyés';

-- ============================================================================
-- Vues utiles pour les statistiques
-- ============================================================================

-- Vue : Statistiques des candidatures par statut
CREATE OR REPLACE VIEW `v_applications_stats` AS
SELECT 
    status,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM job_applications), 2) as pourcentage
FROM job_applications
GROUP BY status;

-- Vue : Candidatures récentes (7 derniers jours)
CREATE OR REPLACE VIEW `v_recent_applications` AS
SELECT 
    id,
    job_title,
    job_company,
    job_location,
    status,
    created_at,
    cv_filename,
    lettre_filename
FROM job_applications
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;

-- Vue : Statistiques par entreprise
CREATE OR REPLACE VIEW `v_applications_by_company` AS
SELECT 
    job_company,
    COUNT(*) as total_candidatures,
    COUNT(CASE WHEN status = 'nouveau' THEN 1 END) as nouveaux,
    COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as en_cours,
    COUNT(CASE WHEN status = 'accepte' THEN 1 END) as acceptes,
    COUNT(CASE WHEN status = 'refuse' THEN 1 END) as refuses
FROM job_applications
GROUP BY job_company
ORDER BY total_candidatures DESC;

-- Vue : Statistiques par poste
CREATE OR REPLACE VIEW `v_applications_by_job` AS
SELECT 
    job_title,
    job_company,
    COUNT(*) as total_candidatures,
    COUNT(CASE WHEN status = 'nouveau' THEN 1 END) as nouveaux,
    MAX(created_at) as derniere_candidature
FROM job_applications
GROUP BY job_title, job_company
ORDER BY total_candidatures DESC;

-- ============================================================================
-- Requêtes utiles pour l'administration
-- ============================================================================

-- Afficher toutes les candidatures avec détails
-- SELECT 
--     id,
--     job_title as 'Poste',
--     job_company as 'Entreprise',
--     job_location as 'Localisation',
--     status as 'Statut',
--     DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as 'Date candidature',
--     cv_filename as 'CV',
--     lettre_filename as 'Lettre'
-- FROM job_applications
-- ORDER BY created_at DESC;

-- Statistiques globales
-- SELECT 
--     COUNT(*) as total_candidatures,
--     COUNT(CASE WHEN status = 'nouveau' THEN 1 END) as nouveaux,
--     COUNT(CASE WHEN status = 'en_cours' THEN 1 END) as en_cours,
--     COUNT(CASE WHEN status = 'accepte' THEN 1 END) as acceptes,
--     COUNT(CASE WHEN status = 'refuse' THEN 1 END) as refuses,
--     COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as aujourdhui,
--     COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as cette_semaine
-- FROM job_applications;

-- Candidatures par mois
-- SELECT 
--     DATE_FORMAT(created_at, '%Y-%m') as mois,
--     COUNT(*) as total,
--     COUNT(CASE WHEN status = 'accepte' THEN 1 END) as acceptes
-- FROM job_applications
-- GROUP BY DATE_FORMAT(created_at, '%Y-%m')
-- ORDER BY mois DESC
-- LIMIT 12;

-- Top 10 des postes les plus demandés
-- SELECT 
--     job_title,
--     job_company,
--     COUNT(*) as total_candidatures
-- FROM job_applications
-- GROUP BY job_title, job_company
-- ORDER BY total_candidatures DESC
-- LIMIT 10;

-- ============================================================================
-- Données de test (optionnel - décommenter pour tester)
-- ============================================================================

-- INSERT INTO job_applications (
--     cv_filename, cv_path, 
--     lettre_filename, lettre_path,
--     job_title, job_company, job_location, job_description,
--     status, ip_address
-- ) VALUES (
--     'cv_jean_kabongo.pdf', 'uploads/cv/cv_1234567890_abc123.pdf',
--     'lettre_jean_kabongo.pdf', 'uploads/cv/lettre_1234567890_def456.pdf',
--     'Opérateur du pont-bascule (Weighbridge Operator)', 'COSMOS Group', 'Kinshasa, RDC',
--     'Dans le cadre du renforcement de ses équipes, COSMOS Group recrute un Opérateur du pont-bascule.',
--     'nouveau', '127.0.0.1'
-- );

-- ============================================================================
-- Procédures stockées utiles
-- ============================================================================

-- Procédure pour changer le statut d'une candidature
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_update_application_status`(
    IN p_application_id INT,
    IN p_new_status ENUM('nouveau','en_cours','accepte','refuse')
)
BEGIN
    UPDATE job_applications 
    SET status = p_new_status,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = p_application_id;
    
    SELECT ROW_COUNT() as affected_rows;
END$$
DELIMITER ;

-- Utilisation : CALL sp_update_application_status(1, 'en_cours');

-- Procédure pour obtenir les statistiques du jour
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `sp_daily_stats`()
BEGIN
    SELECT 
        COUNT(*) as total_aujourdhui,
        COUNT(CASE WHEN HOUR(created_at) >= 8 AND HOUR(created_at) < 12 THEN 1 END) as matin,
        COUNT(CASE WHEN HOUR(created_at) >= 12 AND HOUR(created_at) < 18 THEN 1 END) as apres_midi,
        COUNT(CASE WHEN HOUR(created_at) >= 18 THEN 1 END) as soir
    FROM job_applications
    WHERE DATE(created_at) = CURDATE();
END$$
DELIMITER ;

-- Utilisation : CALL sp_daily_stats();

-- ============================================================================
-- Triggers pour audit et validation
-- ============================================================================

-- Trigger : Validation avant insertion
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS `trg_before_insert_application`
BEFORE INSERT ON `job_applications`
FOR EACH ROW
BEGIN
    -- Vérifier que les champs obligatoires ne sont pas vides
    IF NEW.cv_filename = '' OR NEW.cv_path = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Le CV est obligatoire';
    END IF;
    
    IF NEW.lettre_filename = '' OR NEW.lettre_path = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La lettre de motivation est obligatoire';
    END IF;
    
    IF NEW.job_title = '' OR NEW.job_company = '' OR NEW.job_location = '' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Les informations du poste sont obligatoires';
    END IF;
END$$
DELIMITER ;

-- ============================================================================
-- Index pour optimisation des performances
-- ============================================================================

-- Index composite pour recherche par entreprise et statut
CREATE INDEX IF NOT EXISTS `idx_company_status` 
ON `job_applications` (`job_company`, `status`);

-- Index composite pour recherche par date et statut
CREATE INDEX IF NOT EXISTS `idx_date_status` 
ON `job_applications` (`created_at`, `status`);

-- ============================================================================
-- Commit et finalisation
-- ============================================================================
COMMIT;

-- Restaurer les paramètres
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================================
-- NOTES IMPORTANTES
-- ============================================================================
-- 
-- Ce schéma est adapté au formulaire modal simplifié qui envoie :
-- - CV (fichier obligatoire)
-- - Lettre de motivation (fichier obligatoire)
-- - Informations du poste (job_title, job_company, job_location, job_description)
-- - Prénom et email (utilisés uniquement pour l'email de confirmation, NON stockés en BDD)
-- 
-- Avantages de cette approche :
-- ✅ Conformité RGPD : Moins de données personnelles stockées
-- ✅ Simplicité : Focus sur les documents essentiels
-- ✅ Sécurité : Réduction de la surface d'attaque
-- ✅ Performance : Table légère et rapide
-- 
-- Les informations du candidat (nom, prénom, email, téléphone, etc.) 
-- sont dans les fichiers CV et lettre de motivation uniquement.
-- 
-- Pour installer cette base de données :
-- 1. Créer la base de données (décommenter la section CREATE DATABASE si nécessaire)
-- 2. Exécuter ce fichier SQL complet
-- 3. Vérifier que les tables et vues sont créées : SHOW TABLES;
-- 4. Tester avec une insertion : voir section "Données de test"
-- 
-- ============================================================================
-- FIN DU SCHÉMA
-- ============================================================================
