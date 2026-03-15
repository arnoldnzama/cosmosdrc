#!/bin/bash

# ============================================================================
# Script de déploiement - Formulaire de candidature simplifié
# Cosmos Group - Système de recrutement
# Version: 1.0
# Date: 11 février 2026
# ============================================================================

set -e  # Arrêter en cas d'erreur

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
log_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

log_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Fonction pour demander confirmation
confirm() {
    read -p "$(echo -e ${YELLOW}$1${NC}) [y/N] " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Opération annulée"
        exit 1
    fi
}

# ============================================================================
# ÉTAPE 0 : Vérifications préliminaires
# ============================================================================

echo ""
echo "============================================================================"
echo "  🚀 Déploiement du formulaire de candidature simplifié"
echo "  Cosmos Group - Système de recrutement"
echo "============================================================================"
echo ""

log_info "Vérification des prérequis..."

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "js/main.js" ]; then
    log_error "Erreur : Vous devez exécuter ce script depuis la racine du projet"
    exit 1
fi

log_success "Répertoire correct"

# Vérifier PHP
if ! command -v php &> /dev/null; then
    log_error "PHP n'est pas installé"
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
log_success "PHP $PHP_VERSION détecté"

# Vérifier MySQL
if ! command -v mysql &> /dev/null; then
    log_warning "MySQL CLI n'est pas installé (optionnel)"
else
    log_success "MySQL CLI détecté"
fi

# ============================================================================
# ÉTAPE 1 : Sauvegarde
# ============================================================================

echo ""
log_info "ÉTAPE 1/6 : Sauvegarde des fichiers actuels"
echo ""

BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

log_info "Création du dossier de sauvegarde : $BACKUP_DIR"

# Sauvegarder les fichiers importants
if [ -f "js/main.js" ]; then
    cp "js/main.js" "$BACKUP_DIR/main.js.backup"
    log_success "js/main.js sauvegardé"
fi

if [ -d "uploads" ]; then
    cp -r "uploads" "$BACKUP_DIR/uploads_backup"
    log_success "Dossier uploads sauvegardé"
fi

# Sauvegarder la base de données (si MySQL CLI disponible)
if command -v mysql &> /dev/null; then
    confirm "Voulez-vous sauvegarder la base de données ?"
    
    read -p "Nom de la base de données : " DB_NAME
    read -p "Utilisateur MySQL : " DB_USER
    read -sp "Mot de passe MySQL : " DB_PASS
    echo ""
    
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/database_backup.sql" 2>/dev/null
    
    if [ $? -eq 0 ]; then
        log_success "Base de données sauvegardée"
    else
        log_warning "Erreur lors de la sauvegarde de la BDD (continuez manuellement)"
    fi
fi

log_success "Sauvegarde terminée dans : $BACKUP_DIR"

# ============================================================================
# ÉTAPE 2 : Vérification de la cohérence
# ============================================================================

echo ""
log_info "ÉTAPE 2/6 : Vérification de la cohérence du système"
echo ""

if [ -f "verify-consistency.php" ]; then
    log_info "Exécution de verify-consistency.php..."
    php verify-consistency.php
    
    if [ $? -eq 0 ]; then
        log_success "Vérification réussie"
    else
        log_warning "Des avertissements ont été détectés"
        confirm "Voulez-vous continuer malgré les avertissements ?"
    fi
else
    log_warning "verify-consistency.php non trouvé (ignoré)"
fi

# ============================================================================
# ÉTAPE 3 : Création des dossiers nécessaires
# ============================================================================

echo ""
log_info "ÉTAPE 3/6 : Création des dossiers nécessaires"
echo ""

# Créer le dossier uploads/cv s'il n'existe pas
if [ ! -d "uploads/cv" ]; then
    mkdir -p "uploads/cv"
    log_success "Dossier uploads/cv créé"
else
    log_success "Dossier uploads/cv existe déjà"
fi

# Définir les permissions
chmod 755 uploads/
chmod 755 uploads/cv/
log_success "Permissions définies (755)"

# Créer le fichier .htaccess pour protéger les uploads
if [ ! -f "uploads/cv/.htaccess" ]; then
    cat > "uploads/cv/.htaccess" << 'EOF'
# Protection des fichiers uploadés
Order Deny,Allow
Deny from all

# Autoriser uniquement PHP à accéder
<FilesMatch "\.(php)$">
    Allow from all
</FilesMatch>
EOF
    log_success "Fichier .htaccess créé dans uploads/cv/"
else
    log_success "Fichier .htaccess existe déjà"
fi

# ============================================================================
# ÉTAPE 4 : Vérification de la configuration
# ============================================================================

echo ""
log_info "ÉTAPE 4/6 : Vérification de la configuration"
echo ""

# Vérifier config/database.php
if [ -f "config/database.php" ]; then
    log_success "config/database.php trouvé"
    
    # Vérifier que les constantes sont définies
    if grep -q "define('DB_HOST'" config/database.php; then
        log_success "Configuration BDD semble correcte"
    else
        log_warning "Configuration BDD incomplète"
    fi
else
    log_error "config/database.php manquant"
    log_info "Créez ce fichier avec vos paramètres de connexion"
    exit 1
fi

# Vérifier config/smtp.php
if [ -f "config/smtp.php" ]; then
    log_success "config/smtp.php trouvé"
else
    log_warning "config/smtp.php manquant (les emails ne seront pas envoyés)"
fi

# ============================================================================
# ÉTAPE 5 : Test du système
# ============================================================================

echo ""
log_info "ÉTAPE 5/6 : Test du système"
echo ""

log_info "Vérification des fichiers JavaScript..."
if grep -q "name=\"nom\"" js/main.js; then
    log_error "Le formulaire contient encore les anciens champs !"
    log_error "Assurez-vous d'avoir la dernière version de js/main.js"
    exit 1
else
    log_success "Formulaire simplifié détecté"
fi

log_info "Vérification des champs requis..."
if grep -q "name=\"nom_complet\"" js/main.js && \
   grep -q "name=\"email\"" js/main.js && \
   grep -q "name=\"cv\"" js/main.js && \
   grep -q "name=\"lettre_motivation\"" js/main.js; then
    log_success "Tous les champs requis sont présents"
else
    log_error "Des champs requis sont manquants"
    exit 1
fi

# ============================================================================
# ÉTAPE 6 : Finalisation
# ============================================================================

echo ""
log_info "ÉTAPE 6/6 : Finalisation"
echo ""

# Afficher les informations de déploiement
echo ""
echo "============================================================================"
echo "  ✅ Déploiement terminé avec succès !"
echo "============================================================================"
echo ""
echo "📋 Résumé :"
echo "  - Sauvegarde créée dans : $BACKUP_DIR"
echo "  - Dossier uploads/cv : OK"
echo "  - Permissions : OK"
echo "  - Configuration : OK"
echo "  - Formulaire : Simplifié (4 champs)"
echo ""
echo "📝 Prochaines étapes :"
echo "  1. Testez le formulaire sur votre site"
echo "  2. Vérifiez l'envoi des emails"
echo "  3. Vérifiez l'enregistrement en BDD"
echo "  4. Consultez la documentation complète"
echo ""
echo "📚 Documentation :"
echo "  - INDEX_DOCUMENTATION.md - Index de toute la documentation"
echo "  - README_CANDIDATURES.md - Documentation complète"
echo "  - GUIDE_TEST_FORMULAIRE.md - Guide de test (70+ tests)"
echo "  - test-formulaire.html - Tests interactifs"
echo ""
echo "🧪 Tests recommandés :"
echo "  1. Ouvrir test-formulaire.html dans un navigateur"
echo "  2. Tester une soumission complète"
echo "  3. Vérifier les emails reçus"
echo "  4. Vérifier les données en BDD"
echo ""
echo "🔧 Commandes utiles :"
echo "  - php verify-consistency.php  # Vérifier la cohérence"
echo "  - tail -f /var/log/php_errors.log  # Voir les logs"
echo ""
echo "📞 Support :"
echo "  - Email : web@emergencemag.net"
echo "  - Téléphone : +243 98 21 61 066"
echo ""
echo "============================================================================"
echo ""

log_success "Déploiement terminé !"

# Proposer d'ouvrir la page de test
if command -v xdg-open &> /dev/null; then
    confirm "Voulez-vous ouvrir la page de test dans votre navigateur ?"
    xdg-open "test-formulaire.html" 2>/dev/null || true
elif command -v open &> /dev/null; then
    confirm "Voulez-vous ouvrir la page de test dans votre navigateur ?"
    open "test-formulaire.html" 2>/dev/null || true
fi

exit 0
