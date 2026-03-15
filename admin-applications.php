<?php
/**
 * Interface d'administration des candidatures
 * Cosmos Group - Système de candidature
 */

// Démarrer la session avant toute utilisation de $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Inclure les classes nécessaires
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/Database.php';

// Vérification de sécurité basique (à améliorer en production)
$adminPassword = 'admin123'; // À changer en production
$isAuthenticated = false;

if (isset($_POST['password']) && $_POST['password'] === $adminPassword) {
    $isAuthenticated = true;
    $_SESSION['admin_authenticated'] = true;
} elseif (!empty($_SESSION['admin_authenticated'])) {
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Administration - Cosmos Group</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h1 class="text-2xl font-bold text-center mb-6">Administration</h1>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Mot de passe</label>
                    <input type="password" id="password" name="password" required 
                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" 
                        class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    Se connecter
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Traitement des actions (nécessite une BDD fonctionnelle)
if (isset($_POST['action'])) {
    try {
        $db = Database::getInstance();
    } catch (Exception $e) {
        $dbError = $e->getMessage();
        $db = null;
    }
    if ($db) {
        switch ($_POST['action']) {
        case 'update_status':
            if (isset($_POST['application_id']) && isset($_POST['status'])) {
                $sql = "UPDATE job_applications SET status = :status WHERE id = :id";
                $db->executeQuery($sql, [
                    'status' => $_POST['status'],
                    'id' => $_POST['application_id']
                ]);
            }
            break;
            
        case 'delete':
            if (isset($_POST['application_id'])) {
                $sql = "DELETE FROM job_applications WHERE id = :id";
                $db->executeQuery($sql, ['id' => $_POST['application_id']]);
            }
            break;
        }
    }
}

// Récupération des candidatures
$applications = [];
$dbError = null;

// Fonction pour charger les candidatures depuis le fichier JSON
function loadApplicationsFromJSON() {
    $jsonFile = __DIR__ . '/data/applications.json';
    
    if (!file_exists($jsonFile)) {
        error_log("admin-applications: Fichier JSON non trouvé, tentative de lecture depuis la BDD");
        return null; // Retourner null pour essayer la BDD
    }
    
    $jsonContent = file_get_contents($jsonFile);
    $data = json_decode($jsonContent, true);
    
    if ($data === null) {
        error_log("admin-applications: Erreur de décodage JSON");
        return [];
    }
    
    error_log("admin-applications: " . count($data) . " candidatures chargées depuis JSON");
    return $data;
}

// Essayer de charger depuis JSON d'abord
$applications = loadApplicationsFromJSON();

// Si JSON n'existe pas ou est vide, essayer la BDD (fallback)
if ($applications === null) {
    try {
        $db = Database::getInstance();
        $sql = "SELECT * FROM job_applications ORDER BY created_at DESC";
        $applications = $db->executeQuery($sql)->fetchAll();
        error_log("admin-applications: " . count($applications) . " candidatures chargées depuis BDD");
    } catch (Exception $e) {
        error_log("admin-applications: Erreur BDD - " . $e->getMessage());
        $dbError = $e->getMessage();
        $applications = []; // Tableau vide si les deux sources échouent
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration des candidatures - Hewa Bora Internationale</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Administration des candidatures COSMOS GROUP</h1>
                <div class="flex space-x-4">
                    <a href="offres.html" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-arrow-left mr-2"></i>Retour au site
                    </a>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" id="statisticsSection">
                <div class="col-span-full flex justify-end mb-2">
                    <button onclick="forceUpdateStatistics()" 
                            class="bg-blue-500 text-white px-3 py-1 rounded-md text-sm hover:bg-blue-600 transition-colors duration-200"
                            title="Forcer la mise à jour des statistiques">
                        <i class="fas fa-sync-alt mr-1"></i>Actualiser
                    </button>
                </div>
                <div class="bg-blue-100 p-4 rounded-lg hover:bg-blue-200 transition-colors duration-200 cursor-pointer" onclick="showStatisticsDetails('total')">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-blue-800">Total candidatures</h3>
                            <p class="text-2xl font-bold text-blue-600" id="totalApplications"><?= count($applications) ?></p>
                        </div>
                        <div class="text-blue-600">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-blue-600">
                        <i class="fas fa-info-circle mr-1"></i>Cliquez pour plus de détails
                    </div>
                </div>
                
                <div class="bg-yellow-100 p-4 rounded-lg hover:bg-yellow-200 transition-colors duration-200 cursor-pointer" onclick="showStatisticsDetails('nouveau')">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-yellow-800">Nouvelles</h3>
                            <p class="text-2xl font-bold text-yellow-600" id="newApplications">
                                <?= count(array_filter($applications, fn($app) => $app['status'] === 'nouveau')) ?>
                            </p>
                        </div>
                        <div class="text-yellow-600">
                            <i class="fas fa-circle text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-yellow-600">
                        <i class="fas fa-info-circle mr-1"></i>En attente de traitement
                    </div>
                </div>
                
                <div class="bg-green-100 p-4 rounded-lg hover:bg-green-200 transition-colors duration-200 cursor-pointer" onclick="showStatisticsDetails('accepte')">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-green-800">Acceptées</h3>
                            <p class="text-2xl font-bold text-green-600" id="acceptedApplications">
                                <?= count(array_filter($applications, fn($app) => $app['status'] === 'accepte')) ?>
                            </p>
                        </div>
                        <div class="text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-green-600">
                        <i class="fas fa-info-circle mr-1"></i>Candidatures validées
                    </div>
                </div>
                
                <div class="bg-red-100 p-4 rounded-lg hover:bg-red-200 transition-colors duration-200 cursor-pointer" onclick="showStatisticsDetails('refuse')">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-red-800">Refusées</h3>
                            <p class="text-2xl font-bold text-red-600" id="rejectedApplications">
                                <?= count(array_filter($applications, fn($app) => $app['status'] === 'refuse')) ?>
                            </p>
                        </div>
                        <div class="text-red-600">
                            <i class="fas fa-times-circle text-2xl"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-red-600">
                        <i class="fas fa-info-circle mr-1"></i>Candidatures rejetées
                    </div>
                </div>
            </div>

            <!-- Statistiques avancées -->
            <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6" id="advancedStats" style="display: none;">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Statistiques détaillées</h3>
                    <button onclick="toggleAdvancedStats()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="advancedStatsContent">
                    <!-- Le contenu sera rempli par JavaScript -->
                </div>
            </div>

            <!-- Barre de recherche -->
            <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-4 items-center">
                    <div class="flex-1">
                        <label for="searchInput" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-search mr-2"></i>Rechercher par poste
                        </label>
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Ex: Développeur, Manager, Analyste..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex gap-2">
                        <button id="clearSearch" 
                                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            <i class="fas fa-times mr-2"></i>Effacer
                        </button>
                        <button id="exportFiltered" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            <i class="fas fa-download mr-2"></i>Exporter
                        </button>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-600">
                    <span id="filteredCount"><?= count($applications) ?></span> candidature(s) affichée(s)
                </div>
            </div>

            <?php if (!empty($dbError)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                <strong>Erreur base de données :</strong> <?= htmlspecialchars($dbError) ?>
                <p class="mt-2 text-sm">Vérifiez que les tables existent (exécutez <code>database/schema.sql</code>) et que <code>config/database.php</code> ou <code>.env</code> est correct.</p>
            </div>
            <?php endif; ?>

            <!-- Liste des candidatures -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-300" id="applicationsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Candidature</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poste</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="applicationsTableBody">
                        <?php foreach ($applications as $app): ?>
                        <tr class="hover:bg-gray-50 application-row" data-poste="<?= htmlspecialchars(strtolower($app['job_title'])) ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <span class="text-sm font-medium text-blue-600">
                                                <i class="fas fa-file-alt"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            Candidature #<?= $app['id'] ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($app['cv_filename']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?= htmlspecialchars($app['job_title']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($app['job_company']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($app['job_location']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>Voir documents
                                </div>
                                <div class="text-sm text-gray-400 text-xs">
                                    Infos dans CV/Lettre
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($app['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $statusColors = [
                                    'nouveau' => 'bg-blue-100 text-blue-800 border-blue-200',
                                    'en_cours' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                    'accepte' => 'bg-green-100 text-green-800 border-green-200',
                                    'refuse' => 'bg-red-100 text-red-800 border-red-200'
                                ];
                                $statusLabels = [
                                    'nouveau' => 'Nouveau',
                                    'en_cours' => 'En cours',
                                    'accepte' => 'Accepté',
                                    'refuse' => 'Refusé'
                                ];
                                $statusColor = $statusColors[$app['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                $statusLabel = $statusLabels[$app['status']] ?? ucfirst($app['status']);
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?= $statusColor ?>">
                                    <?php if ($app['status'] === 'nouveau'): ?>
                                        <i class="fas fa-circle text-blue-500 mr-1"></i>
                                    <?php elseif ($app['status'] === 'en_cours'): ?>
                                        <i class="fas fa-clock text-yellow-500 mr-1"></i>
                                    <?php elseif ($app['status'] === 'accepte'): ?>
                                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                                    <?php elseif ($app['status'] === 'refuse'): ?>
                                        <i class="fas fa-times-circle text-red-500 mr-1"></i>
                                    <?php endif; ?>
                                    <?= $statusLabel ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <!-- Voir les détails -->
                                    <button onclick="showDetails(<?= htmlspecialchars(json_encode($app)) ?>)" 
                                            class="text-blue-600 hover:text-blue-900" title="Voir les détails">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <!-- Télécharger CV -->
                                    <?php 
                                    $cvPath = $app['cv_path'];
                                    $absolutePath = __DIR__ . '/' . $cvPath;
                                    $fileExists = !empty($cvPath) && file_exists($absolutePath);
                                    ?>
                                    <?php if ($fileExists): ?>
                                    <a href="<?= htmlspecialchars($cvPath) ?>" target="_blank" 
                                       class="text-green-600 hover:text-green-900" title="Télécharger le CV"
                                       download="<?= htmlspecialchars($app['cv_filename'] ?? 'CV.pdf') ?>">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-gray-400 cursor-not-allowed" title="CV non disponible">
                                        <i class="fas fa-download"></i>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Télécharger Lettre de motivation -->
                                    <?php 
                                    $lettrePath = $app['lettre_path'] ?? '';
                                    $lettreAbsolutePath = !empty($lettrePath) ? __DIR__ . '/' . $lettrePath : '';
                                    $lettreExists = !empty($lettrePath) && file_exists($lettreAbsolutePath);
                                    ?>
                                    <?php if ($lettreExists): ?>
                                    <a href="<?= htmlspecialchars($lettrePath) ?>" target="_blank" 
                                       class="text-purple-600 hover:text-purple-900" title="Télécharger la lettre de motivation"
                                       download="<?= htmlspecialchars($app['lettre_filename'] ?? 'Lettre_motivation.pdf') ?>">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-gray-400 cursor-not-allowed" title="Lettre non disponible">
                                        <i class="fas fa-file-alt"></i>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Changer statut -->
                                    <select onchange="updateStatus(<?= $app['id'] ?>, this.value)" 
                                            class="text-xs border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="nouveau" <?= $app['status'] === 'nouveau' ? 'selected' : '' ?>>🆕 Nouveau</option>
                                        <option value="en_cours" <?= $app['status'] === 'en_cours' ? 'selected' : '' ?>>⏳ En cours</option>
                                        <option value="accepte" <?= $app['status'] === 'accepte' ? 'selected' : '' ?>>✅ Accepté</option>
                                        <option value="refuse" <?= $app['status'] === 'refuse' ? 'selected' : '' ?>>❌ Refusé</option>
                                    </select>
                                    
                                    <!-- Supprimer -->
                                    <button onclick="deleteApplication(<?= $app['id'] ?>)" 
                                            class="text-red-600 hover:text-red-900" 
                                            title="Supprimer la candidature"
                                            data-application-id="<?= $app['id'] ?>"
                                            data-candidate-name="Candidature #<?= $app['id'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($applications)): ?>
            <div class="text-center py-8">
                <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
                <p class="text-gray-500">Aucune candidature reçue pour le moment.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal pour les détails -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center pb-4 border-b">
                    <h3 class="text-xl font-bold text-gray-900">Détails de la candidature</h3>
                    <button onclick="closeDetailsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="detailsContent" class="py-4">
                    <!-- Le contenu sera rempli par JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour afficher des notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 p-4 rounded-md shadow-lg ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                'bg-blue-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Variables globales pour la recherche
        let allApplications = [];
        let filteredApplications = [];

        // Initialiser les données au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch();
            loadApplicationsData();
            updateStatistics(); // Initialiser les statistiques au chargement
            autoRefreshStatistics(); // Démarrer le rafraîchissement automatique
        });

        // Initialiser la fonctionnalité de recherche
        function initializeSearch() {
            const searchInput = document.getElementById('searchInput');
            const clearButton = document.getElementById('clearSearch');
            const exportButton = document.getElementById('exportFiltered');

            // Recherche en temps réel
            searchInput.addEventListener('input', function() {
                filterApplications(this.value);
            });

            // Bouton effacer
            clearButton.addEventListener('click', function() {
                searchInput.value = '';
                filterApplications('');
                searchInput.focus();
            });

            // Bouton exporter
            exportButton.addEventListener('click', function() {
                exportFilteredResults();
            });
        }

        // Charger les données des candidatures
        function loadApplicationsData() {
            const rows = document.querySelectorAll('.application-row');
            allApplications = Array.from(rows).map(row => {
                const candidatureElement = row.querySelector('.text-gray-900');
                const statusSelect = row.querySelector('select');
                
                return {
                    element: row,
                    poste: row.getAttribute('data-poste') || '',
                    candidature: candidatureElement ? candidatureElement.textContent.trim() : '',
                    statut: statusSelect ? statusSelect.value : 'nouveau'
                };
            });
            filteredApplications = [...allApplications];
            
            // Mettre à jour automatiquement les statistiques après le chargement
            updateStatistics();
        }

        // Filtrer les candidatures
        function filterApplications(searchTerm) {
            const searchLower = searchTerm.toLowerCase().trim();
            
            if (searchLower === '') {
                allApplications.forEach(app => {
                    app.element.style.display = '';
                });
                filteredApplications = [...allApplications];
            } else {
                filteredApplications = allApplications.filter(app => {
                    return app.poste.includes(searchLower);
                });

                allApplications.forEach(app => {
                    if (filteredApplications.includes(app)) {
                        app.element.style.display = '';
                    } else {
                        app.element.style.display = 'none';
                    }
                });
            }

            updateFilteredCount();
            showNoResultsMessage(filteredApplications.length === 0);
        }

        // Mettre à jour le compteur de résultats
        function updateFilteredCount() {
            const countElement = document.getElementById('filteredCount');
            countElement.textContent = filteredApplications.length;
        }

        // Afficher un message si aucun résultat
        function showNoResultsMessage(noResults) {
            let noResultsRow = document.getElementById('noResultsRow');
            
            if (noResults) {
                if (!noResultsRow) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'noResultsRow';
                    noResultsRow.innerHTML = `
                        <td colspan="6" class="px-6 py-8 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg mb-2">Aucune candidature trouvée</p>
                                <p class="text-gray-400 text-sm">Essayez de modifier vos critères de recherche</p>
                            </div>
                        </td>
                    `;
                    document.getElementById('applicationsTableBody').appendChild(noResultsRow);
                }
                noResultsRow.style.display = '';
            } else if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }

        // Exporter les résultats filtrés
        function exportFilteredResults() {
            if (filteredApplications.length === 0) {
                showNotification('Aucune candidature à exporter', 'error');
                return;
            }

            let csvContent = 'ID,Poste,Entreprise,Lieu,Statut,Date,CV,Lettre\n';
            
            filteredApplications.forEach(app => {
                const row = app.element;
                const cells = row.querySelectorAll('td');
                
                const candidature = cells[0].querySelector('.text-gray-900').textContent.trim();
                const poste = cells[1].querySelector('.text-gray-900').textContent.trim();
                const entreprise = cells[1].querySelectorAll('.text-gray-500')[0].textContent.trim();
                const lieu = cells[1].querySelectorAll('.text-gray-500')[1].textContent.trim();
                const statut = cells[4].querySelector('span').textContent.trim();
                const date = cells[3].textContent.trim();
                const cv = cells[0].querySelectorAll('.text-gray-500')[0]?.textContent.trim() || 'N/A';
                
                csvContent += `"${candidature}","${poste}","${entreprise}","${lieu}","${statut}","${date}","${cv}","Voir documents"\n`;
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `candidatures_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            showNotification(`${filteredApplications.length} candidature(s) exportée(s)`, 'success');
        }

        function showDetails(application) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('detailsContent');
            
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                        <p class="text-sm text-blue-700">
                            <i class="fas fa-info-circle mr-2"></i>
                            Les informations du candidat (nom, email, téléphone, etc.) sont disponibles dans les documents CV et lettre de motivation.
                        </p>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-900">Informations du poste</h4>
                        <p><strong>Titre:</strong> ${application.job_title}</p>
                        <p><strong>Entreprise:</strong> ${application.job_company}</p>
                        <p><strong>Lieu:</strong> ${application.job_location}</p>
                        ${application.job_description ? '<p><strong>Description:</strong> ' + application.job_description.substring(0, 200) + '...</p>' : ''}
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-900">Documents</h4>
                        
                        <!-- CV -->
                        <div class="mb-3">
                            <p class="font-medium text-gray-700">CV</p>
                            ${application.cv_path ? `
                                <p><strong>Nom du fichier:</strong> ${application.cv_filename || 'Non spécifié'}</p>
                                <a href="${application.cv_path}" target="_blank" 
                                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 mt-2"
                                   download="${application.cv_filename || 'CV.pdf'}">
                                    <i class="fas fa-download mr-2"></i>Télécharger le CV
                                </a>
                            ` : '<p class="text-red-600">Aucun fichier CV disponible</p>'}
                        </div>
                        
                        <!-- Lettre de motivation -->
                        <div class="mt-3">
                            <p class="font-medium text-gray-700">Lettre de motivation</p>
                            ${application.lettre_path ? `
                                <p><strong>Nom du fichier:</strong> ${application.lettre_filename || 'Non spécifié'}</p>
                                <a href="${application.lettre_path}" target="_blank" 
                                   class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mt-2"
                                   download="${application.lettre_filename || 'Lettre_motivation.pdf'}">
                                    <i class="fas fa-download mr-2"></i>Télécharger la lettre
                                </a>
                            ` : '<p class="text-gray-500 text-sm">Aucune lettre de motivation fournie</p>'}
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-semibold text-gray-900">Informations techniques</h4>
                        <p><strong>ID:</strong> ${application.id}</p>
                        <p><strong>Date de candidature:</strong> ${new Date(application.created_at).toLocaleString('fr-FR')}</p>
                        ${application.ip_address ? '<p><strong>Adresse IP:</strong> ' + application.ip_address + '</p>' : ''}
                        ${application.user_agent ? '<p class="text-xs text-gray-500"><strong>User Agent:</strong> ' + application.user_agent.substring(0, 100) + '...</p>' : ''}
                    </div>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        function updateStatus(applicationId, status) {
            // Validation côté client
            const validStatuses = ['nouveau', 'en_cours', 'accepte', 'refuse'];
            if (!validStatuses.includes(status)) {
                showNotification('Statut invalide', 'error');
                return;
            }

            // Récupérer l'élément select qui a déclenché l'événement
            const select = event.target;
            const originalValue = select.value;
            
            // Éviter les mises à jour inutiles
            if (originalValue === status) {
                return;
            }
            
            // Afficher un indicateur de chargement
            select.disabled = true;
            const originalHTML = select.innerHTML;
            select.innerHTML = '<option>⏳ Mise à jour...</option>';
            
            // Afficher une notification de mise à jour
            showNotification('Mise à jour du statut en cours...', 'info');
            
            // Créer les données à envoyer
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('application_id', applicationId);
            formData.append('status', status);
            
            // Envoyer la requête AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(data => {
                // Succès
                showNotification('Statut mis à jour avec succès', 'success');
                
                // Mettre à jour l'apparence du select selon le nouveau statut
                updateSelectAppearance(select, status);
                
                // Réactiver le select
                select.disabled = false;
                select.innerHTML = originalHTML;
                select.value = status;
                
                // Mettre à jour les statistiques IMMÉDIATEMENT
                updateStatistics();
                
                // Mettre à jour les données de recherche
                loadApplicationsData();
            })
            .catch(error => {
                // Erreur
                console.error('Erreur:', error);
                showNotification('Erreur lors de la mise à jour du statut', 'error');
                
                // Restaurer l'état original
                select.disabled = false;
                select.innerHTML = originalHTML;
                select.value = originalValue;
            });
        }

        // Fonction pour mettre à jour l'apparence du select selon le statut
        function updateSelectAppearance(select, status) {
            const statusColors = {
                'nouveau': 'border-blue-300 bg-blue-50',
                'en_cours': 'border-yellow-300 bg-yellow-50',
                'accepte': 'border-green-300 bg-green-50',
                'refuse': 'border-red-300 bg-red-50'
            };
            
            // Supprimer les anciennes classes
            select.className = select.className.replace(/border-\w+-300|bg-\w+-50/g, '');
            
            // Ajouter les nouvelles classes
            if (statusColors[status]) {
                select.className += ' ' + statusColors[status];
            }
        }

        function deleteApplication(applicationId) {
            const button = event.target.closest('button');
            const candidateName = button.dataset.candidateName;
            const confirmed = confirm(`Êtes-vous sûr de vouloir supprimer la candidature de ${candidateName} ? Cette action est irréversible.`);

            if (confirmed) {
                const originalContent = button.innerHTML;
                const originalTitle = button.title;
                
                // Afficher un indicateur de chargement
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;
                button.title = 'Suppression en cours...';
                
                // Afficher une notification de suppression
                showNotification('Suppression de la candidature en cours...', 'info');
                
                // Créer les données à envoyer
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('application_id', applicationId);
                
                // Envoyer la requête AJAX
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(data => {
                    // Succès
                    showNotification('Candidature supprimée avec succès', 'success');
                    
                    // Masquer la ligne du tableau avec une animation
                    const row = button.closest('tr');
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(-100%)';
                    
                    // Supprimer la ligne après l'animation
                    setTimeout(() => {
                        row.remove();
                        
                        // Mettre à jour IMMÉDIATEMENT les statistiques et compteurs
                        updateStatistics();
                        updateApplicationsCount();
                        
                        // Mettre à jour les données de recherche
                        loadApplicationsData();
                    }, 500);
                })
                .catch(error => {
                    // Erreur
                    console.error('Erreur:', error);
                    showNotification('Erreur lors de la suppression de la candidature', 'error');
                    
                    // Restaurer l'état original du bouton
                    button.disabled = false;
                    button.innerHTML = originalContent;
                    button.title = originalTitle;
                });
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });

        // Fonction pour forcer la mise à jour des statistiques
        function forceUpdateStatistics() {
            updateStatistics();
            showNotification('Statistiques actualisées', 'success');
        }

        // Fonction pour mettre à jour les statistiques
        function updateStatistics() {
            const applications = document.querySelectorAll('.application-row');
            const total = applications.length;
            
            // Compter par statut
            const stats = {
                total: total,
                nouveau: 0,
                en_cours: 0,
                accepte: 0,
                refuse: 0
            };
            
            applications.forEach(row => {
                const statusSelect = row.querySelector('select');
                if (statusSelect) {
                    const status = statusSelect.value;
                    if (stats.hasOwnProperty(status)) {
                        stats[status]++;
                    }
                }
            });
            
            // Mettre à jour les éléments d'affichage avec animation
            updateStatElement('totalApplications', stats.total);
            updateStatElement('newApplications', stats.nouveau);
            updateStatElement('acceptedApplications', stats.accepte);
            updateStatElement('rejectedApplications', stats.refuse);
        }

        // Fonction pour mettre à jour un élément de statistique avec animation
        function updateStatElement(elementId, newValue) {
            const element = document.getElementById(elementId);
            if (element) {
                const oldValue = parseInt(element.textContent) || 0;
                if (oldValue !== newValue) {
                    // Animation de mise à jour
                    element.style.transform = 'scale(1.1)';
                    element.style.transition = 'transform 0.3s ease';
                    element.textContent = newValue;
                    
                    setTimeout(() => {
                        element.style.transform = 'scale(1)';
                    }, 300);
                } else {
                    element.textContent = newValue;
                }
            }
        }

        // Fonction pour rafraîchir automatiquement les statistiques
        function autoRefreshStatistics() {
            setInterval(() => {
                updateStatistics();
            }, 15000); // Rafraîchir toutes les 15 secondes
        }

        // Fonction pour mettre à jour le compteur des candidatures
        function updateApplicationsCount() {
            const totalApplications = document.querySelectorAll('.application-row').length;
            const totalElement = document.getElementById('totalApplications');
            if (totalElement) {
                totalElement.textContent = totalApplications;
            }
            
            // Mettre à jour le compteur de recherche si il existe
            const filteredCountElement = document.getElementById('filteredCount');
            if (filteredCountElement) {
                const visibleApplications = document.querySelectorAll('.application-row:not([style*="display: none"])').length;
                filteredCountElement.textContent = visibleApplications;
            }
        }

        // Fonction pour afficher les statistiques détaillées
        function showStatisticsDetails(type) {
            const advancedStats = document.getElementById('advancedStats');
            const content = document.getElementById('advancedStatsContent');
            
            const typeLabels = {
                'total': 'Toutes les candidatures',
                'nouveau': 'Candidatures nouvelles',
                'accepte': 'Candidatures acceptées',
                'refuse': 'Candidatures refusées'
            };
            
            // Calculer les statistiques détaillées
            const stats = calculateDetailedStats(type);
            
            content.innerHTML = `
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-chart-pie mr-2"></i>Répartition par secteur
                    </h4>
                    <div class="space-y-2">
                        ${Object.entries(stats.sectors).map(([sector, count]) => `
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-blue-700">${sector}</span>
                                <span class="font-semibold text-blue-800">${count}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-800 mb-2">
                        <i class="fas fa-clock mr-2"></i>Répartition par expérience
                    </h4>
                    <div class="space-y-2">
                        ${Object.entries(stats.experience).map(([exp, count]) => `
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-green-700">${exp}</span>
                                <span class="font-semibold text-green-800">${count}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-800 mb-2">
                        <i class="fas fa-calendar mr-2"></i>Activité récente
                    </h4>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-purple-700">Aujourd'hui</span>
                            <span class="font-semibold text-purple-800">${stats.today}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-purple-700">Cette semaine</span>
                            <span class="font-semibold text-purple-800">${stats.thisWeek}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-purple-700">Ce mois</span>
                            <span class="font-semibold text-purple-800">${stats.thisMonth}</span>
                        </div>
                    </div>
                </div>
            `;
            
            advancedStats.style.display = 'block';
            advancedStats.scrollIntoView({ behavior: 'smooth' });
        }

        // Fonction pour calculer les statistiques détaillées
        function calculateDetailedStats(type) {
            const applications = document.querySelectorAll('.application-row');
            let filteredApplications = [];
            
            switch(type) {
                case 'total':
                    filteredApplications = Array.from(applications);
                    break;
                case 'nouveau':
                    filteredApplications = Array.from(applications).filter(row => 
                        row.querySelector('select').value === 'nouveau'
                    );
                    break;
                case 'accepte':
                    filteredApplications = Array.from(applications).filter(row => 
                        row.querySelector('select').value === 'accepte'
                    );
                    break;
                case 'refuse':
                    filteredApplications = Array.from(applications).filter(row => 
                        row.querySelector('select').value === 'refuse'
                    );
                    break;
            }
            
            const stats = {
                sectors: {},
                experience: {},
                today: 0,
                thisWeek: 0,
                thisMonth: 0
            };
            
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            const monthAgo = new Date(today.getFullYear(), today.getMonth() - 1, today.getDate());
            
            filteredApplications.forEach(row => {
                // Secteur
                const sectorCell = row.querySelectorAll('td')[0];
                const sector = sectorCell.querySelector('.text-gray-500').textContent.split(' - ')[0];
                stats.sectors[sector] = (stats.sectors[sector] || 0) + 1;
                
                // Expérience
                const experience = sectorCell.querySelector('.text-gray-500').textContent.split(' - ')[1];
                stats.experience[experience] = (stats.experience[experience] || 0) + 1;
                
                // Date (simulation pour l'exemple)
                stats.today += Math.floor(Math.random() * 3);
                stats.thisWeek += Math.floor(Math.random() * 5);
                stats.thisMonth += Math.floor(Math.random() * 10);
            });
            
            return stats;
        }

        // Fonction pour basculer l'affichage des statistiques avancées
        function toggleAdvancedStats() {
            const advancedStats = document.getElementById('advancedStats');
            if (advancedStats.style.display === 'none') {
                advancedStats.style.display = 'block';
            } else {
                advancedStats.style.display = 'none';
            }
        }


    </script>
</body>
</html> 