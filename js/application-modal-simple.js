/**
 * Modal de candidature simplifié - Cosmos Group
 * Version: 3.0 - 2026
 * Description de l'offre + Upload CV et Lettre de motivation uniquement
 */

class ApplicationModalSimple {
    constructor() {
        this.modal = null;
        this.form = null;
        this.isSubmitting = false;
        this.currentJobData = {};

        this.init();
    }

    init() {
        this.createModal();
        this.bindEvents();
        console.log('✅ ApplicationModalSimple initialisé');
    }

    createModal() {
        // Créer le HTML du modal simplifié
        const modalHTML = `
            <div id="applicationModal" class="application-modal">
                <div class="modal-overlay"></div>
                <div class="modal-container">
                    <div class="modal-header">
                        <h2>Postuler pour ce poste</h2>
                        <button class="modal-close" type="button">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="job-info">
                            <h3 id="modal-job-title">Titre du poste</h3>
                            <p id="modal-job-company"><i class="fas fa-building"></i> Entreprise</p>
                            <p id="modal-job-location"><i class="fas fa-map-marker-alt"></i> Localisation</p>
                            <p id="modal-job-salary" class="modal-salary-badge" style="display:none;">
                                <i class="fas fa-dollar-sign"></i> <span id="modal-job-salary-value"></span>
                            </p>
                            <p id="modal-job-sector" style="display:none;">
                                <i class="fas fa-industry"></i> <span id="modal-job-sector-value"></span>
                            </p>
                            <p id="modal-job-headcount" style="display:none;">
                                <i class="fas fa-users"></i> <span id="modal-job-headcount-value"></span>
                            </p>
                        </div>

                        <!-- Description complète de l'offre -->
                        <div class="job-description-section">
                            <h4><i class="fas fa-info-circle"></i> Description du poste</h4>
                            <div id="modal-job-description" class="job-description-content">
                                <!-- La description sera insérée ici -->
                            </div>
                        </div>

                        <form id="applicationForm" class="application-form" enctype="multipart/form-data">
                            <div class="form-section">
                                <h4><i class="fas fa-user"></i> Vos informations</h4>
                                <p class="form-section-subtitle">Pour vous envoyer une confirmation</p>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nom_complet">
                                            <i class="fas fa-user"></i> Nom complet *
                                        </label>
                                        <input type="text" id="nom_complet" name="nom_complet" required placeholder="Votre nom complet">
                                        <span class="error-message"></span>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">
                                            <i class="fas fa-envelope"></i> Email *
                                        </label>
                                        <input type="email" id="email" name="email" required placeholder="votre@email.com">
                                        <span class="error-message"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-briefcase"></i> Infos RH</h4>
                                <p class="form-section-subtitle">Informations complémentaires pour le recruteur</p>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="pretention_salariale">
                                            <i class="fas fa-dollar-sign"></i> Prétention salariale (optionnel)
                                        </label>
                                        <div class="salary-input-wrapper">
                                            <input type="number" id="pretention_salariale" name="pretention_salariale"
                                                placeholder="ex: 2000" min="0" step="50">
                                            <span class="salary-currency">USD</span>
                                        </div>
                                        <p class="field-hint"><i class="fas fa-info-circle"></i> Exemple : 2 000 $</p>
                                        <span class="error-message"></span>
                                    </div>

                                    <div class="form-group">
                                        <label for="disponibilite">
                                            <i class="fas fa-calendar-alt"></i> Disponibilité / Préavis *
                                        </label>
                                        <select id="disponibilite" name="disponibilite" required>
                                            <option value="">-- Sélectionnez --</option>
                                            <option value="Immédiatement">Immédiatement</option>
                                            <option value="1 semaine">1 semaine</option>
                                            <option value="2 semaines">2 semaines</option>
                                            <option value="1 mois">1 mois</option>
                                            <option value="2 mois">2 mois</option>
                                            <option value="3 mois">3 mois</option>
                                            <option value="Plus de 3 mois">Plus de 3 mois</option>
                                        </select>
                                        <span class="error-message"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-paperclip"></i> Pièces jointes</h4>
                                <p class="form-section-subtitle">Veuillez joindre votre CV et votre lettre de motivation</p>
                                
                                <div class="form-group">
                                    <label for="cv">
                                        <i class="fas fa-file-pdf"></i> CV (PDF, DOC, DOCX - Max 5MB) *
                                    </label>
                                    <div class="file-input-wrapper">
                                        <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                                        <div class="file-input-display">
                                            <i class="fas fa-upload"></i>
                                            <span>Choisir votre CV</span>
                                        </div>
                                    </div>
                                    <div class="file-info"></div>
                                    <span class="error-message"></span>
                                </div>

                                <div class="form-group">
                                    <label for="lettre">
                                        <i class="fas fa-file-alt"></i> Lettre de motivation (PDF, DOC, DOCX - Max 5MB) *
                                    </label>
                                    <div class="file-input-wrapper">
                                        <input type="file" id="lettre" name="lettre" accept=".pdf,.doc,.docx" required>
                                        <div class="file-input-display">
                                            <i class="fas fa-upload"></i>
                                            <span>Choisir votre lettre de motivation</span>
                                        </div>
                                    </div>
                                    <div class="file-info-lettre"></div>
                                    <span class="error-message"></span>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4><i class="fas fa-shield-alt"></i> Consentement</h4>
                                <p class="form-section-subtitle">Veuillez lire et accepter les conditions ci-dessous</p>

                                <div class="form-group consent-group">
                                    <label class="consent-label">
                                        <input type="checkbox" id="consent_data" name="consent_data" value="1" required>
                                        <span class="consent-text">
                                            J'accepte que mes données personnelles soient traitées par Cosmos Group dans le cadre de ma candidature, conformément à notre
                                            <a href="politique-confidentialite.html" target="_blank">politique de confidentialité</a>. *
                                        </span>
                                    </label>
                                    <span class="error-message"></span>
                                </div>

                                <div class="form-group consent-group">
                                    <label class="consent-label">
                                        <input type="checkbox" id="consent_contact" name="consent_contact" value="1">
                                        <span class="consent-text">
                                            J'accepte d'être contacté(e) par Cosmos Group pour d'autres opportunités correspondant à mon profil. (optionnel)
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Champs cachés pour les informations du poste -->
                            <input type="hidden" id="job_title" name="job_title">
                            <input type="hidden" id="job_company" name="job_company">
                            <input type="hidden" id="job_location" name="job_location">
                            <input type="hidden" id="job_description" name="job_description">

                            <div class="form-actions">
                                <button type="button" class="btn-cancel">Annuler</button>
                                <button type="submit" class="btn-submit">
                                    <span class="btn-text">
                                        <i class="fas fa-paper-plane"></i>
                                        Envoyer ma candidature
                                    </span>
                                    <span class="btn-loading">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Envoi en cours...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;

        // Ajouter le modal au DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        this.modal = document.getElementById('applicationModal');
        this.form = document.getElementById('applicationForm');
    }

    bindEvents() {
        // Attendre que le modal soit bien dans le DOM
        setTimeout(() => {
            this.setupEventListeners();
        }, 0);
    }

    setupEventListeners() {
        // Boutons "Postuler"
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-apply') || e.target.closest('.btn-apply')) {
                e.preventDefault();
                const button = e.target.matches('.btn-apply') ? e.target : e.target.closest('.btn-apply');
                this.openModal(button);
            }
        });

        // Fermeture du modal
        this.modal.addEventListener('click', (e) => {
            if (e.target.matches('.modal-close') || e.target.matches('.modal-overlay') || e.target.matches('.btn-cancel')) {
                this.closeModal();
            }
        });

        // Échap pour fermer
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.closeModal();
            }
        });

        // Soumission du formulaire
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            console.log('🔥 Formulaire soumis - Début de la validation');
            this.submitApplication();
        });

        // Gestion du fichier CV
        const cvInput = document.getElementById('cv');
        if (cvInput) {
            const cvWrapper = cvInput.closest('.form-group');
            const fileDisplay = cvWrapper.querySelector('.file-input-display span');
            const fileInfo = cvWrapper.querySelector('.file-info');

            cvInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                console.log('📎 Fichier CV sélectionné:', file ? file.name : 'aucun');
                if (file) {
                    fileDisplay.textContent = file.name;
                    fileInfo.innerHTML = `
                        <i class="fas fa-check-circle"></i>
                        ${file.name} (${this.formatFileSize(file.size)})
                    `;
                    fileInfo.classList.add('success');
                    this.validateFile(file, cvInput);
                } else {
                    fileDisplay.textContent = 'Choisir votre CV';
                    fileInfo.innerHTML = '';
                    fileInfo.classList.remove('success');
                }
            });
        } else {
            console.error('❌ Élément CV non trouvé');
        }

        // Gestion du fichier Lettre de motivation
        const lettreInput = document.getElementById('lettre');
        if (lettreInput) {
            const lettreWrapper = lettreInput.closest('.form-group');
            const lettreDisplay = lettreWrapper.querySelector('.file-input-display span');
            const lettreInfo = lettreWrapper.querySelector('.file-info-lettre');

            lettreInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                console.log('📎 Fichier Lettre sélectionné:', file ? file.name : 'aucun');
                if (file) {
                    lettreDisplay.textContent = file.name;
                    lettreInfo.innerHTML = `
                        <i class="fas fa-check-circle"></i>
                        ${file.name} (${this.formatFileSize(file.size)})
                    `;
                    lettreInfo.classList.add('success');
                    this.validateFile(file, lettreInput);
                } else {
                    lettreDisplay.textContent = 'Choisir votre lettre de motivation';
                    lettreInfo.innerHTML = '';
                    lettreInfo.classList.remove('success');
                }
            });
        } else {
            console.error('❌ Élément Lettre non trouvé');
        }
    }

    openModal(button) {
        // Récupérer les données du poste
        this.currentJobData = {
            title: button.dataset.jobTitle || button.getAttribute('data-job-title'),
            company: button.dataset.jobCompany || button.getAttribute('data-job-company'),
            location: button.dataset.jobLocation || button.getAttribute('data-job-location'),
            salary: button.dataset.jobSalary || button.getAttribute('data-job-salary') || '',
            sector: button.dataset.jobSector || button.getAttribute('data-job-sector') || '',
            headcount: button.dataset.jobHeadcount || button.getAttribute('data-job-headcount') || '',
            description: button.dataset.jobDescription || button.getAttribute('data-job-description') || 'Description non disponible'
        };

        console.log('📋 Ouverture du modal pour:', this.currentJobData);

        // Remplir les informations du poste
        document.getElementById('modal-job-title').textContent = this.currentJobData.title;
        document.getElementById('modal-job-company').innerHTML = `<i class="fas fa-building"></i> ${this.currentJobData.company}`;
        document.getElementById('modal-job-location').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${this.currentJobData.location}`;
        document.getElementById('modal-job-description').textContent = this.currentJobData.description;

        // Afficher le salaire si disponible
        const salaryEl = document.getElementById('modal-job-salary');
        const salaryValEl = document.getElementById('modal-job-salary-value');
        if (this.currentJobData.salary) {
            salaryValEl.textContent = this.currentJobData.salary;
            salaryEl.style.display = 'flex';
        } else {
            salaryEl.style.display = 'none';
        }

        // Afficher le secteur si disponible
        const sectorEl = document.getElementById('modal-job-sector');
        const sectorValEl = document.getElementById('modal-job-sector-value');
        if (this.currentJobData.sector) {
            sectorValEl.textContent = this.currentJobData.sector;
            sectorEl.style.display = 'flex';
        } else {
            sectorEl.style.display = 'none';
        }

        // Afficher l'effectif recherché si disponible
        const headcountEl = document.getElementById('modal-job-headcount');
        const headcountValEl = document.getElementById('modal-job-headcount-value');
        if (this.currentJobData.headcount) {
            headcountValEl.textContent = this.currentJobData.headcount;
            headcountEl.style.display = 'flex';
        } else {
            headcountEl.style.display = 'none';
        }

        // Remplir les champs cachés
        document.getElementById('job_title').value = this.currentJobData.title;
        document.getElementById('job_company').value = this.currentJobData.company;
        document.getElementById('job_location').value = this.currentJobData.location;
        document.getElementById('job_description').value = this.currentJobData.description;

        // Afficher le modal
        this.modal.classList.add('active');
        document.body.classList.add('modal-open');
    }

    closeModal() {
        this.modal.classList.remove('active');
        document.body.classList.remove('modal-open');

        // Réinitialiser le formulaire après l'animation
        setTimeout(() => {
            this.resetForm();
        }, 300);
    }

    resetForm() {
        this.form.reset();
        this.clearErrors();

        // Réinitialiser l'affichage du fichier CV
        const cvWrapper = document.getElementById('cv').closest('.form-group');
        if (cvWrapper) {
            const fileDisplay = cvWrapper.querySelector('.file-input-display span');
            const fileInfo = cvWrapper.querySelector('.file-info');
            if (fileDisplay) fileDisplay.textContent = 'Choisir votre CV';
            if (fileInfo) {
                fileInfo.innerHTML = '';
                fileInfo.classList.remove('success');
            }
        }

        // Réinitialiser l'affichage du fichier Lettre de motivation
        const lettreWrapper = document.getElementById('lettre').closest('.form-group');
        if (lettreWrapper) {
            const lettreDisplay = lettreWrapper.querySelector('.file-input-display span');
            const lettreInfo = lettreWrapper.querySelector('.file-info-lettre');
            if (lettreDisplay) lettreDisplay.textContent = 'Choisir votre lettre de motivation';
            if (lettreInfo) {
                lettreInfo.innerHTML = '';
                lettreInfo.classList.remove('success');
            }
        }

        // Réinitialiser les champs Infos RH
        const disponibiliteField = document.getElementById('disponibilite');
        if (disponibiliteField) disponibiliteField.value = '';
        const pretentionField = document.getElementById('pretention_salariale');
        if (pretentionField) pretentionField.value = '';

        // Réinitialiser les cases de consentement
        const consentData = document.getElementById('consent_data');
        if (consentData) consentData.checked = false;
        const consentContact = document.getElementById('consent_contact');
        if (consentContact) consentContact.checked = false;
    }

    async submitApplication() {
        if (this.isSubmitting) return;

        console.log('📤 Soumission de la candidature...');

        // Validation côté client
        if (!this.validateForm()) {
            console.log('❌ Validation échouée');
            return;
        }

        this.isSubmitting = true;
        this.setSubmitState(true);

        try {
            // Préparer les données
            const formData = new FormData(this.form);

            // Log des données (sans les fichiers)
            const dataForLog = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'cv' && key !== 'lettre') {
                    dataForLog[key] = value;
                }
            }
            console.log('📋 Données à envoyer:', dataForLog);
            console.log('📎 Fichiers:', {
                cv: formData.get('cv') ? formData.get('cv').name : 'aucun',
                lettre: formData.get('lettre') ? formData.get('lettre').name : 'aucun'
            });
            console.log('💼 Infos RH:', {
                pretention_salariale: formData.get('pretention_salariale') || 'non renseigné',
                disponibilite: formData.get('disponibilite'),
                consent_data: formData.get('consent_data'),
                consent_contact: formData.get('consent_contact') || '0'
            });

            // URL du script
            const submitUrl = (typeof window.baseUrl === 'string' && window.baseUrl)
                ? window.baseUrl + 'application-submit.php'
                : 'application-submit.php';

            console.log('🌐 URL de soumission:', submitUrl);

            const response = await fetch(submitUrl, {
                method: 'POST',
                body: formData
            });

            console.log('📡 Réponse reçue, status:', response.status);

            const text = await response.text();
            console.log('📄 Réponse brute (premiers 500 caractères):', text.substring(0, 500));

            let result = null;
            try {
                result = JSON.parse(text);
            } catch (parseError) {
                console.error('❌ Erreur de parsing JSON:', parseError);
                this.showError('Erreur serveur. Veuillez contacter l\'administrateur.');
                return;
            }

            console.log('📋 Résultat parsé:', result);

            if (result && result.success) {
                this.showSuccess(result.message || 'Votre candidature a été envoyée avec succès !');
                setTimeout(() => {
                    this.closeModal();
                }, 3000);
            } else {
                let errorMsg = 'Une erreur est survenue.';
                if (result && result.message) {
                    errorMsg = result.message;
                } else if (result && result.errors) {
                    const errors = Object.values(result.errors);
                    errorMsg = errors.join('<br>');
                }
                this.showError(errorMsg);
            }

        } catch (error) {
            console.error('❌ Erreur lors de la soumission:', error);
            this.showError('Erreur de connexion. Veuillez vérifier votre connexion internet et réessayer.');
        } finally {
            this.isSubmitting = false;
            this.setSubmitState(false);
        }
    }

    validateForm() {
        let isValid = true;
        const errors = [];
        this.clearErrors();

        console.log('🔍 Début de la validation du formulaire');

        // Validation du nom complet
        const nomCompletField = document.getElementById('nom_complet');
        console.log('👤 Nom complet:', nomCompletField ? nomCompletField.value : 'CHAMP NON TROUVÉ');
        if (!nomCompletField || !nomCompletField.value.trim()) {
            this.showFieldError(nomCompletField, 'Le nom complet est obligatoire');
            errors.push('Nom complet manquant');
            isValid = false;
        }

        // Validation de l'email
        const emailField = document.getElementById('email');
        console.log('📧 Email:', emailField ? emailField.value : 'CHAMP NON TROUVÉ');
        if (!emailField || !emailField.value.trim()) {
            this.showFieldError(emailField, 'L\'email est obligatoire');
            errors.push('Email manquant');
            isValid = false;
        } else if (!this.validateEmail(emailField.value)) {
            this.showFieldError(emailField, 'Email invalide');
            errors.push('Email invalide');
            isValid = false;
        }

        // Validation du fichier CV (OBLIGATOIRE)
        const cvField = document.getElementById('cv');
        console.log('📄 CV:', cvField ? (cvField.files.length > 0 ? cvField.files[0].name : 'AUCUN FICHIER') : 'CHAMP NON TROUVÉ');
        if (!cvField || cvField.files.length === 0) {
            this.showFieldError(cvField, 'Veuillez télécharger votre CV');
            errors.push('CV manquant');
            isValid = false;
        } else {
            const file = cvField.files[0];
            if (!this.validateFile(file, cvField)) {
                errors.push('CV invalide');
                isValid = false;
            }
        }

        // Validation du fichier Lettre de motivation (OBLIGATOIRE)
        const lettreField = document.getElementById('lettre');
        console.log('📝 Lettre:', lettreField ? (lettreField.files.length > 0 ? lettreField.files[0].name : 'AUCUN FICHIER') : 'CHAMP NON TROUVÉ');
        if (!lettreField || lettreField.files.length === 0) {
            this.showFieldError(lettreField, 'Veuillez télécharger votre lettre de motivation');
            errors.push('Lettre de motivation manquante');
            isValid = false;
        } else {
            const file = lettreField.files[0];
            if (!this.validateFile(file, lettreField)) {
                errors.push('Lettre de motivation invalide');
                isValid = false;
            }
        }

        // Validation de la disponibilité (OBLIGATOIRE)
        const disponibiliteField = document.getElementById('disponibilite');
        if (!disponibiliteField || !disponibiliteField.value) {
            this.showFieldError(disponibiliteField, 'Veuillez indiquer votre disponibilité');
            errors.push('Disponibilité manquante');
            isValid = false;
        }

        // Validation du consentement (OBLIGATOIRE)
        const consentDataField = document.getElementById('consent_data');
        if (!consentDataField || !consentDataField.checked) {
            this.showFieldError(consentDataField, 'Vous devez accepter le traitement de vos données');
            errors.push('Consentement manquant');
            isValid = false;
        }

        if (!isValid) {
            console.log('❌ Erreurs de validation:', errors);
            this.showError('Veuillez corriger les erreurs dans le formulaire.');
            
            // Faire défiler vers le premier champ en erreur
            setTimeout(() => {
                const firstError = this.form.querySelector('.has-error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    console.log('📍 Défilement vers le premier champ en erreur');
                }
            }, 100);
        } else {
            console.log('✅ Validation réussie');
        }

        return isValid;
    }

    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    validateFile(file, field) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedExtensions = ['pdf', 'doc', 'docx'];
        const extension = file.name.split('.').pop().toLowerCase();

        if (file.size > maxSize) {
            this.showFieldError(field, 'Le fichier est trop volumineux (max 5MB)');
            return false;
        }

        if (!allowedExtensions.includes(extension)) {
            this.showFieldError(field, 'Format non autorisé (PDF, DOC, DOCX uniquement)');
            return false;
        }

        this.clearFieldError(field);
        return true;
    }

    showFieldError(field, message) {
        if (!field) return;
        
        field.classList.add('error');
        const formGroup = field.closest('.form-group');
        if (formGroup) {
            formGroup.classList.add('has-error');
            
            // Trouver le span d'erreur dans le form-group
            const errorSpan = formGroup.querySelector('.error-message');
            if (errorSpan) {
                errorSpan.textContent = message;
                errorSpan.style.display = 'block';
            }
        }
        
        console.log('⚠️ Erreur de champ:', field.id || field.name, '-', message);
    }

    clearFieldError(field) {
        if (!field) return;
        
        field.classList.remove('error');
        const formGroup = field.closest('.form-group');
        if (formGroup) {
            formGroup.classList.remove('has-error');
            
            // Trouver le span d'erreur dans le form-group
            const errorSpan = formGroup.querySelector('.error-message');
            if (errorSpan) {
                errorSpan.textContent = '';
                errorSpan.style.display = 'none';
            }
        }
    }

    clearErrors() {
        const errorFields = this.form.querySelectorAll('.error');
        const errorMessages = this.form.querySelectorAll('.error-message');
        const errorGroups = this.form.querySelectorAll('.has-error');

        errorFields.forEach(field => field.classList.remove('error'));
        errorGroups.forEach(group => group.classList.remove('has-error'));
        errorMessages.forEach(msg => {
            msg.textContent = '';
            msg.style.display = 'none';
        });
    }

    setSubmitState(isSubmitting) {
        const submitBtn = this.form.querySelector('.btn-submit');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');

        if (isSubmitting) {
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline-flex';
        } else {
            submitBtn.disabled = false;
            btnText.style.display = 'inline-flex';
            btnLoading.style.display = 'none';
        }
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        // Supprimer les notifications existantes
        const existingNotifications = document.querySelectorAll('.application-notification');
        existingNotifications.forEach(notif => notif.remove());

        // Créer la nouvelle notification
        const notification = document.createElement('div');
        notification.className = `application-notification ${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        // Ajouter au modal
        this.modal.querySelector('.modal-body').prepend(notification);

        // Supprimer automatiquement après 5 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
}

// Initialiser le modal quand le DOM est prêt
document.addEventListener('DOMContentLoaded', function () {
    console.log('🚀 Initialisation du modal de candidature simplifié...');
    window.applicationModalSimple = new ApplicationModalSimple();
});

// Export pour utilisation globale
window.ApplicationModalSimple = ApplicationModalSimple;
