<?php
/**
 * Classe Database pour la gestion de la connexion à la base de données
 * Cosmos Group - Système de recrutement
 */

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $connection;
    private $lastError = null;

    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        $this->connect();
    }

    /**
     * Obtenir l'instance unique de la base de données
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Établir la connexion à la base de données
     */
    private function connect() {
        try {
            error_log("🔌 Tentative de connexion BDD - Host: " . DB_HOST . ", DB: " . DB_NAME . ", User: " . DB_USER);
            
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5 // Timeout de 5 secondes
            ];
            
            // Ajouter l'option MySQL seulement si disponible
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci";
            }

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Définir le charset manuellement si l'option n'était pas disponible
            if (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $this->connection->exec("SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci");
            }
            $this->lastError = null;
            error_log("✅ Connexion BDD établie avec succès");
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("❌ ERREUR CONNEXION BDD: " . $e->getMessage());
            error_log("Code erreur: " . $e->getCode());
            
            // Messages d'erreur plus spécifiques
            if ($e->getCode() == 1045) {
                error_log("Erreur d'authentification - Vérifier DB_USER et DB_PASS");
            } elseif ($e->getCode() == 1049) {
                error_log("Base de données introuvable - Vérifier DB_NAME");
            } elseif ($e->getCode() == 2002) {
                error_log("Serveur MySQL inaccessible - Vérifier DB_HOST et DB_PORT");
            }
            
            throw new Exception(ERROR_MESSAGES['database_connection']);
        }
    }

    /**
     * Obtenir la connexion PDO
     */
    public function getConnection() {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Exécuter une requête préparée
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Erreur d'exécution de requête: " . $e->getMessage());
            throw new Exception("Erreur lors de l'exécution de la requête");
        }
    }

    /**
     * Insérer une candidature (version simplifiée)
     */
    public function insertApplication($data) {
        $sql = "INSERT INTO job_applications (
            cv_filename, cv_path, lettre_filename, lettre_path, 
            job_title, job_company, job_location, job_description,
            ip_address, user_agent, created_at
        ) VALUES (
            :cv_filename, :cv_path, :lettre_filename, :lettre_path,
            :job_title, :job_company, :job_location, :job_description,
            :ip_address, :user_agent, NOW()
        )";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Erreur d'insertion de candidature: " . $e->getMessage());
            throw new Exception(ERROR_MESSAGES['database_insert_failed']);
        }
    }

    /**
     * Insérer un message de contact
     */
    public function insertContactMessage($data) {
        $sql = "INSERT INTO contact_messages (
            firstName, lastName, email, phone, subject, message, 
            newsletter, privacy, ip_address, user_agent, created_at
        ) VALUES (
            :firstName, :lastName, :email, :phone, :subject, :message,
            :newsletter, :privacy, :ip_address, :user_agent, NOW()
        )";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Erreur d'insertion de message de contact: " . $e->getMessage());
            throw new Exception("Erreur lors de l'enregistrement du message");
        }
    }

    /**
     * Insérer un abonnement newsletter
     */
    public function insertNewsletterSubscription($email) {
        $sql = "INSERT INTO newsletter_subscriptions (
            email, ip_address, user_agent, subscribed_at
        ) VALUES (
            :email, :ip_address, :user_agent, NOW()
        )";

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'email' => $email,
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            return $this->connection->lastInsertId();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            error_log("Erreur d'insertion d'abonnement newsletter: " . $e->getMessage());
            throw new Exception("Erreur lors de l'enregistrement de l'abonnement");
        }
    }

    /**
     * Obtenir l'adresse IP du client
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Obtenir la dernière erreur
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Fermer la connexion
     */
    public function close() {
        $this->connection = null;
    }

    /**
     * Destructeur pour fermer la connexion
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Empêcher le clonage de l'instance
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
