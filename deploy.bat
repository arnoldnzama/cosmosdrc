@echo off
REM ============================================================================
REM Script de déploiement - Formulaire de candidature simplifié
REM Cosmos Group - Système de recrutement
REM Version: 1.0
REM Date: 11 février 2026
REM ============================================================================

setlocal enabledelayedexpansion

echo.
echo ============================================================================
echo   Deploiement du formulaire de candidature simplifie
echo   Cosmos Group - Systeme de recrutement
echo ============================================================================
echo.

REM ============================================================================
REM ÉTAPE 0 : Vérifications préliminaires
REM ============================================================================

echo [INFO] Verification des prerequis...

REM Vérifier que nous sommes dans le bon répertoire
if not exist "js\main.js" (
    echo [ERREUR] Vous devez executer ce script depuis la racine du projet
    pause
    exit /b 1
)

echo [OK] Repertoire correct

REM Vérifier PHP
where php >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERREUR] PHP n'est pas installe ou pas dans le PATH
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
echo [OK] PHP %PHP_VERSION% detecte

REM ============================================================================
REM ÉTAPE 1 : Sauvegarde
REM ============================================================================

echo.
echo [INFO] ETAPE 1/6 : Sauvegarde des fichiers actuels
echo.

REM Créer un nom de dossier de sauvegarde avec la date
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set datetime=%%I
set BACKUP_DIR=backup_%datetime:~0,8%_%datetime:~8,6%

mkdir "%BACKUP_DIR%" 2>nul

echo [INFO] Creation du dossier de sauvegarde : %BACKUP_DIR%

REM Sauvegarder les fichiers importants
if exist "js\main.js" (
    copy "js\main.js" "%BACKUP_DIR%\main.js.backup" >nul
    echo [OK] js\main.js sauvegarde
)

if exist "uploads" (
    xcopy "uploads" "%BACKUP_DIR%\uploads_backup\" /E /I /Q >nul
    echo [OK] Dossier uploads sauvegarde
)

echo [OK] Sauvegarde terminee dans : %BACKUP_DIR%

REM ============================================================================
REM ÉTAPE 2 : Vérification de la cohérence
REM ============================================================================

echo.
echo [INFO] ETAPE 2/6 : Verification de la coherence du systeme
echo.

if exist "verify-consistency.php" (
    echo [INFO] Execution de verify-consistency.php...
    php verify-consistency.php
    if !errorlevel! neq 0 (
        echo [ATTENTION] Des avertissements ont ete detectes
        set /p CONTINUE="Voulez-vous continuer ? (O/N) : "
        if /i not "!CONTINUE!"=="O" (
            echo [ERREUR] Operation annulee
            pause
            exit /b 1
        )
    ) else (
        echo [OK] Verification reussie
    )
) else (
    echo [ATTENTION] verify-consistency.php non trouve (ignore)
)

REM ============================================================================
REM ÉTAPE 3 : Création des dossiers nécessaires
REM ============================================================================

echo.
echo [INFO] ETAPE 3/6 : Creation des dossiers necessaires
echo.

REM Créer le dossier uploads/cv s'il n'existe pas
if not exist "uploads\cv" (
    mkdir "uploads\cv"
    echo [OK] Dossier uploads\cv cree
) else (
    echo [OK] Dossier uploads\cv existe deja
)

REM Créer le fichier .htaccess pour protéger les uploads
if not exist "uploads\cv\.htaccess" (
    (
        echo # Protection des fichiers uploades
        echo Order Deny,Allow
        echo Deny from all
        echo.
        echo # Autoriser uniquement PHP a acceder
        echo ^<FilesMatch "\.(php)$"^>
        echo     Allow from all
        echo ^</FilesMatch^>
    ) > "uploads\cv\.htaccess"
    echo [OK] Fichier .htaccess cree dans uploads\cv\
) else (
    echo [OK] Fichier .htaccess existe deja
)

REM ============================================================================
REM ÉTAPE 4 : Vérification de la configuration
REM ============================================================================

echo.
echo [INFO] ETAPE 4/6 : Verification de la configuration
echo.

REM Vérifier config/database.php
if exist "config\database.php" (
    echo [OK] config\database.php trouve
    
    REM Vérifier que les constantes sont définies
    findstr /C:"define('DB_HOST'" "config\database.php" >nul
    if !errorlevel! equ 0 (
        echo [OK] Configuration BDD semble correcte
    ) else (
        echo [ATTENTION] Configuration BDD incomplete
    )
) else (
    echo [ERREUR] config\database.php manquant
    echo [INFO] Creez ce fichier avec vos parametres de connexion
    pause
    exit /b 1
)

REM Vérifier config/smtp.php
if exist "config\smtp.php" (
    echo [OK] config\smtp.php trouve
) else (
    echo [ATTENTION] config\smtp.php manquant (les emails ne seront pas envoyes)
)

REM ============================================================================
REM ÉTAPE 5 : Test du système
REM ============================================================================

echo.
echo [INFO] ETAPE 5/6 : Test du systeme
echo.

echo [INFO] Verification des fichiers JavaScript...
findstr /C:"name=\"nom\"" "js\main.js" >nul
if !errorlevel! equ 0 (
    echo [ERREUR] Le formulaire contient encore les anciens champs !
    echo [ERREUR] Assurez-vous d'avoir la derniere version de js\main.js
    pause
    exit /b 1
) else (
    echo [OK] Formulaire simplifie detecte
)

echo [INFO] Verification des champs requis...
findstr /C:"name=\"nom_complet\"" "js\main.js" >nul && ^
findstr /C:"name=\"email\"" "js\main.js" >nul && ^
findstr /C:"name=\"cv\"" "js\main.js" >nul && ^
findstr /C:"name=\"lettre_motivation\"" "js\main.js" >nul
if !errorlevel! equ 0 (
    echo [OK] Tous les champs requis sont presents
) else (
    echo [ERREUR] Des champs requis sont manquants
    pause
    exit /b 1
)

REM ============================================================================
REM ÉTAPE 6 : Finalisation
REM ============================================================================

echo.
echo [INFO] ETAPE 6/6 : Finalisation
echo.

REM Afficher les informations de déploiement
echo.
echo ============================================================================
echo   Deploiement termine avec succes !
echo ============================================================================
echo.
echo Resume :
echo   - Sauvegarde creee dans : %BACKUP_DIR%
echo   - Dossier uploads\cv : OK
echo   - Configuration : OK
echo   - Formulaire : Simplifie (4 champs)
echo.
echo Prochaines etapes :
echo   1. Testez le formulaire sur votre site
echo   2. Verifiez l'envoi des emails
echo   3. Verifiez l'enregistrement en BDD
echo   4. Consultez la documentation complete
echo.
echo Documentation :
echo   - INDEX_DOCUMENTATION.md - Index de toute la documentation
echo   - README_CANDIDATURES.md - Documentation complete
echo   - GUIDE_TEST_FORMULAIRE.md - Guide de test (70+ tests)
echo   - test-formulaire.html - Tests interactifs
echo.
echo Tests recommandes :
echo   1. Ouvrir test-formulaire.html dans un navigateur
echo   2. Tester une soumission complete
echo   3. Verifier les emails recus
echo   4. Verifier les donnees en BDD
echo.
echo Commandes utiles :
echo   - php verify-consistency.php  # Verifier la coherence
echo.
echo Support :
echo   - Email : web@emergencemag.net
echo   - Telephone : +243 999 980 902
echo.
echo ============================================================================
echo.

echo [OK] Deploiement termine !

REM Proposer d'ouvrir la page de test
set /p OPEN_TEST="Voulez-vous ouvrir la page de test dans votre navigateur ? (O/N) : "
if /i "!OPEN_TEST!"=="O" (
    start test-formulaire.html
)

pause
exit /b 0
