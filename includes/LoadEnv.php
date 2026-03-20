<?php
/**
 * Chargeur de variables d'environnement depuis .env
 * COSMOS Group - Système de recrutement
 */

class LoadEnv {
    /**
     * Charger les variables d'environnement depuis le fichier .env
     */
    public static function load($filePath) {
        if (!file_exists($filePath)) {
            error_log("⚠️ Fichier .env introuvable: " . $filePath);
            return false;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser la ligne
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Supprimer les guillemets si présents
                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                // Définir la variable d'environnement
                if (!array_key_exists($name, $_ENV)) {
                    putenv("$name=$value");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }

        error_log("✅ Variables d'environnement chargées depuis .env");
        return true;
    }
}
