<?php
/**
 * Alias pour application-submit.php
 * COSMOS Group - Système de recrutement
 * 
 * Ce fichier existe pour maintenir la compatibilité avec les formulaires
 * qui utilisent submit.php comme action. Il redirige simplement vers
 * application-submit.php qui contient la logique complète.
 */

// Inclure le fichier de traitement principal
require_once __DIR__ . '/application-submit.php';
?>
