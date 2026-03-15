// JavaScript principal pour Cosmos Group
document.addEventListener('DOMContentLoaded', function () {
    var navToggle = document.querySelector('.nav-toggle');
    var navMenu = document.querySelector('.nav-menu');

    if (navToggle && navMenu) {
        // Supprimer les anciens écouteurs d'événements s'ils existent
        var newNavToggle = navToggle.cloneNode(true);
        navToggle.parentNode.replaceChild(newNavToggle, navToggle);
        navToggle = newNavToggle;

        navToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });

        // Fermer le menu en cliquant en dehors
        document.addEventListener('click', function (e) {
            if (!navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            }
        });
    }

    var dropdownItems = document.querySelectorAll('.nav-dropdown');

    for (var i = 0; i < dropdownItems.length; i++) {
        (function (dropdown) {
            var dropdownToggle = dropdown.querySelector('.dropdown-toggle');
            var dropdownMenu = dropdown.querySelector('.dropdown-menu');

            dropdown.addEventListener('mouseenter', function () {
                if (window.innerWidth > 768) {
                    dropdown.classList.add('active');
                }
            });

            dropdown.addEventListener('mouseleave', function () {
                if (window.innerWidth > 768) {
                    dropdown.classList.remove('active');
                }
            });

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function (e) {
                    if (window.innerWidth <= 768 || !dropdown.classList.contains('active')) {
                        e.preventDefault();
                    }

                    dropdown.classList.toggle('active');

                    for (var j = 0; j < dropdownItems.length; j++) {
                        var otherDropdown = dropdownItems[j];
                        if (otherDropdown !== dropdown) {
                            otherDropdown.classList.remove('active');
                        }
                    }
                });
            }
        })(dropdownItems[i]);
    }

    document.addEventListener('click', function (e) {
        var target = e.target;
        var insideDropdown = false;
        while (target) {
            if (target.classList && target.classList.contains('nav-dropdown')) {
                insideDropdown = true;
                break;
            }
            target = target.parentNode;
        }
        if (!insideDropdown) {
            for (var i = 0; i < dropdownItems.length; i++) {
                dropdownItems[i].classList.remove('active');
            }
        }
    });

    var navLinks = document.querySelectorAll('.nav-links a');
    for (var k = 0; k < navLinks.length; k++) {
        (function (link) {
            link.addEventListener('click', function () {
                if (!link.classList.contains('dropdown-toggle')) {
                    if (window.innerWidth <= 768 && navMenu && navToggle) {
                        navMenu.classList.remove('active');
                        navToggle.classList.remove('active');
                    }
                    for (var i = 0; i < dropdownItems.length; i++) {
                        dropdownItems[i].classList.remove('active');
                    }
                }
            });
        })(navLinks[k]);
    }

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && navMenu && navToggle) {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
            for (var i = 0; i < dropdownItems.length; i++) {
                dropdownItems[i].classList.remove('active');
            }
        }
    });

    var filterButtons = document.querySelectorAll('.filter-btn');
    var jobCards = document.querySelectorAll('.job-card');

    for (var fb = 0; fb < filterButtons.length; fb++) {
        (function (button) {
            button.addEventListener('click', function () {
                for (var x = 0; x < filterButtons.length; x++) {
                    filterButtons[x].classList.remove('active');
                }
                button.classList.add('active');

                var filter = button.getAttribute('data-filter');

                for (var c = 0; c < jobCards.length; c++) {
                    var card = jobCards[c];
                    if (filter === 'all') {
                        card.style.display = 'block';
                        card.classList.add('fade-in-up');
                    } else {
                        var badge = card.querySelector('.job-badge');
                        var jobType = badge ? badge.textContent.toLowerCase() : '';
                        if (jobType.indexOf(filter) !== -1) {
                            card.style.display = 'block';
                            card.classList.add('fade-in-up');
                        } else {
                            card.style.display = 'none';
                        }
                    }
                }
            });
        })(filterButtons[fb]);
    }

    var searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var searchInputEl = document.querySelector('.search-input');
            var locationInputEl = document.querySelector('.location-input');
            var searchValue = searchInputEl ? searchInputEl.value : '';
            var locationValue = locationInputEl ? locationInputEl.value : '';

            var params = new URLSearchParams();
            if (searchValue) params.append('q', searchValue);
            if (locationValue) params.append('location', locationValue);

            var url = 'offres.html';
            var query = params.toString();
            if (query) {
                url += '?' + query;
            }
            window.location.href = url;
        });
    }

    // Animation au scroll
    var observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    if (window.IntersectionObserver) {
        var observer = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                var entry = entries[i];
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                }
            }
        }, observerOptions);

        var animatedElements = document.querySelectorAll('.job-card, .service-card, .partner-logo');
        for (var ae = 0; ae < animatedElements.length; ae++) {
            observer.observe(animatedElements[ae]);
        }
    }

    // Smooth scroll pour les liens d'ancrage
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Navbar scroll effect
    var header = document.querySelector('.header');
    var lastScrollTop = 0;

    window.addEventListener('scroll', function () {
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        if (header) {
            if (scrollTop > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            if (scrollTop > lastScrollTop && scrollTop > 200) {
                header.style.transform = 'translateY(-100%)';
            } else {
                header.style.transform = 'translateY(0)';
            }

            lastScrollTop = scrollTop;
        }
    });

    // Gestion des alertes et notifications
    function showNotification(message, type) {
        if (!type) type = 'info';
        var notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.innerHTML = '<span>' + message + '</span>' +
            '<button class="notification-close">&times;</button>';

        document.body.appendChild(notification);

        setTimeout(function () {
            if (notification && notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);

        var closeBtn = notification.querySelector('.notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (notification && notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            });
        }
    }

    // Validation des formulaires
    function validateEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function validateForm(form) {
        var inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        var isValid = true;

        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            var value = input.value.trim();
            var errorElement = input.parentNode.querySelector('.error-message');

            if (errorElement && errorElement.parentNode) {
                errorElement.parentNode.removeChild(errorElement);
            }

            input.classList.remove('error');

            if (!value) {
                showFieldError(input, 'Ce champ est obligatoire');
                isValid = false;
            } else if (input.type === 'email' && !validateEmail(value)) {
                showFieldError(input, 'Veuillez saisir une adresse email valide');
                isValid = false;
            }
        }

        return isValid;
    }

    function showFieldError(input, message) {
        input.classList.add('error');
        var errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.textContent = message;
        input.parentNode.appendChild(errorElement);
    }

    // Exposer les helpers pour les autres scripts/fonctions globales du fichier
    // (newsletter/contact/candidature s'appuient dessus).
    window.showNotification = showNotification;
    window.showFieldError = showFieldError;
    window.validateEmail = validateEmail;

    // Gestion des favoris (localStorage)
    var favoriteButtons = document.querySelectorAll('.btn-favorite');
    for (var fbIndex = 0; fbIndex < favoriteButtons.length; fbIndex++) {
        (function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                var jobId = this.getAttribute('data-job-id');
                toggleFavorite(jobId, this);
            });
        })(favoriteButtons[fbIndex]);
    }

    function toggleFavorite(jobId, button) {
        var favorites = JSON.parse(localStorage.getItem('favorites') || '[]');

        if (favorites.includes(jobId)) {
            favorites = favorites.filter(id => id !== jobId);
            button.classList.remove('active');
            showNotification('Offre retirée des favoris', 'info');
        } else {
            favorites.push(jobId);
            button.classList.add('active');
            showNotification('Offre ajoutée aux favoris', 'success');
        }

        localStorage.setItem('favorites', JSON.stringify(favorites));
    }

    // Initialiser les favoris au chargement
    function initializeFavorites() {
        var favorites = JSON.parse(localStorage.getItem('favorites') || '[]');
        for (var i = 0; i < favoriteButtons.length; i++) {
            var button = favoriteButtons[i];
            var jobId = button.getAttribute('data-job-id');
            if (favorites.indexOf(jobId) !== -1) {
                button.classList.add('active');
            }
        }
    }

    initializeFavorites();

    // Recherche en temps réel
    var searchInputs = document.querySelectorAll('.search-input');
    for (var si = 0; si < searchInputs.length; si++) {
        (function (input) {
            var timeout;
            input.addEventListener('input', function () {
                clearTimeout(timeout);
                var self = this;
                timeout = setTimeout(function () {
                    performSearch(self.value);
                }, 300);
            });
        })(searchInputs[si]);
    }

    function performSearch(query) {
        if (query.length < 2) return;

        // Simulation d'une recherche AJAX
        console.log('Recherche pour:', query);
        // Ici, vous ajouteriez l'appel API réel
    }

    // Gestion des cookies RGPD
    function initCookieConsent() {
        if (!localStorage.getItem('cookieConsent')) {
            showCookieConsent();
        }
    }

    function showCookieConsent() {
        var cookieBanner = document.createElement('div');
        cookieBanner.className = 'cookie-banner';
        cookieBanner.innerHTML = `
            <div class="cookie-content">
                <p>Nous utilisons des cookies pour améliorer votre expérience sur notre site. En continuant à naviguer, vous acceptez notre utilisation des cookies.</p>
                <div class="cookie-buttons">
                    <button class="btn-secondary" onclick="acceptCookies()">Accepter</button>
                    <a href="cookies.html" class="btn-link">En savoir plus</a>
                </div>
            </div>
        `;

        document.body.appendChild(cookieBanner);
    }

    window.acceptCookies = function () {
        localStorage.setItem('cookieConsent', 'true');
        document.querySelector('.cookie-banner').remove();
    };

    initCookieConsent();

    // Lazy loading des images
    var images = document.querySelectorAll('img[data-src]');
    if (window.IntersectionObserver) {
        var imageObserver = new IntersectionObserver(function (entries, observer) {
            for (var i = 0; i < entries.length; i++) {
                var entry = entries[i];
                if (entry.isIntersecting) {
                    var img = entry.target;
                    img.src = img.getAttribute('data-src');
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            }
        });

        for (var im = 0; im < images.length; im++) {
            imageObserver.observe(images[im]);
        }
    }

    // ===== SOLUTIONS MODAL LOGIC (DISABLED - NOW USING FAQ) =====
    /*
    const solutionModal = document.getElementById('solutionModal');
    const solutionCards = document.querySelectorAll('.solution-card');
    const closeSolutionModal = document.querySelector('.close-modal');

    if (solutionModal && solutionCards.length > 0) {
        solutionCards.forEach(card => {
            const btn = card.querySelector('.btn-read-more-modal');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const title = card.getAttribute('data-title');
                    const description = card.getAttribute('data-description');
                    
                    // Gérer à la fois les images et les SVG
                    const solutionImage = card.querySelector('.solution-image');
                    const modalImgContainer = document.getElementById('modalImageContainer');
                    
                    // Cloner l'élément image (img ou svg)
                    const imageElement = solutionImage.querySelector('img, svg');
                    if (imageElement) {
                        const clonedElement = imageElement.cloneNode(true);
                        modalImgContainer.innerHTML = '';
                        modalImgContainer.appendChild(clonedElement);
                    }

                    document.getElementById('modalTitle').textContent = title;
                    document.getElementById('modalDescription').textContent = description;

                    solutionModal.classList.add('show');
                    document.body.style.overflow = 'hidden'; // Prevents scrolling
                });
            }
        });

        if (closeSolutionModal) {
            closeSolutionModal.addEventListener('click', function () {
                solutionModal.classList.remove('show');
                document.body.style.overflow = ''; // Restores scrolling
            });
        }

        // Close on outside click
        window.addEventListener('click', function (e) {
            if (e.target === solutionModal) {
                solutionModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });

        // Close on Esc key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && solutionModal.classList.contains('show')) {
                solutionModal.classList.remove('show');
                document.body.style.overflow = '';
            }
        });
    }
    */
});

window.utils = {
    formatDate: function (date) {
        return new Intl.DateTimeFormat('fr-FR').format(new Date(date));
    },

    formatSalary: function (salary) {
        return new Intl.NumberFormat('fr-FR').format(salary) + ' FC';
    },

    debounce: function (func, wait) {
        var timeout;
        return function () {
            var args = arguments;
            var later = function () {
                clearTimeout(timeout);
                func.apply(null, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

// ===== ANIMATION DES STATISTIQUES HERO =====
function animateStats() {
    const stats = document.querySelectorAll('.stat-modern');

    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };

    const statsObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const statNumber = entry.target.querySelector('.stat-modern-number');
                if (statNumber && !statNumber.classList.contains('animated')) {
                    animateCounter(statNumber);
                    statNumber.classList.add('animated');
                }
            }
        });
    }, observerOptions);

    stats.forEach(function (stat) {
        statsObserver.observe(stat);
    });
}

function animateCounter(element) {
    const text = element.textContent;
    const hasPlus = text.includes('+');
    const number = parseInt(text.replace(/[^0-9]/g, ''));
    const suffix = text.replace(/[0-9+]/g, '');
    const duration = 2000;
    const steps = 60;
    const increment = number / steps;
    let current = 0;

    const timer = setInterval(function () {
        current += increment;
        if (current >= number) {
            current = number;
            clearInterval(timer);
        }

        let displayValue = Math.floor(current);
        if (displayValue >= 1000) {
            displayValue = (displayValue / 1000).toFixed(0) + 'K';
        }

        element.textContent = (hasPlus ? '+' : '') + displayValue + suffix;
    }, duration / steps);
}

// Initialiser l'animation des stats au chargement
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateStats);
} else {
    animateStats();
}

// ===== GESTION DES FORMULAIRES DE CANDIDATURE =====
document.addEventListener('DOMContentLoaded', function () {
    // Gestion du formulaire de candidature
    const applicationForms = document.querySelectorAll('form[action*="submit.php"], .application-form');
    applicationForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleApplicationSubmit(this);
        });
    });

    // Gestion des formulaires de newsletter
    const newsletterForms = document.querySelectorAll('form[action*="newsletter-subscribe.php"], .newsletter-form');
    newsletterForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleNewsletterSubmit(this);
        });
    });
});

// Fonction pour gérer la soumission de candidature
function handleApplicationSubmit(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;

    console.log('📤 Soumission du formulaire de candidature');

    // Validation du formulaire
    if (!validateApplicationForm(form)) {
        console.log('❌ Validation échouée');
        return;
    }

    console.log('✅ Validation réussie');

    // Désactiver le bouton et afficher le loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

    // Créer FormData
    const formData = new FormData(form);

    console.log('📋 Données du formulaire:', Object.fromEntries(formData.entries()));

    // Envoyer la requête
    fetch('submit.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('📡 Réponse reçue, status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('📄 Réponse brute:', text);
            try {
                const data = JSON.parse(text);
                console.log('📊 Données parsées:', data);

                if (data.success) {
                    showNotification(data.message, 'success');
                    form.reset();
                    closeApplicationModal();

                    // Rediriger seulement si explicitement demandé par le serveur
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 2000);
                    }
                } else {
                    console.log('❌ Erreur dans la réponse:', data.message);
                    showNotification(data.message || 'Une erreur est survenue', 'error');

                    // Afficher les erreurs spécifiques
                    if (data.errors) {
                        Object.keys(data.errors).forEach(field => {
                            const input = form.querySelector(`[name="${field}"]`);
                            if (input) {
                                showFieldError(input, data.errors[field]);
                            }
                        });
                    }
                }

                // Afficher les avertissements s'il y en a
                if (data.warning) {
                    setTimeout(() => {
                        showNotification(data.warning, 'info');
                    }, 2000);
                }
            } catch (parseError) {
                console.error('❌ Erreur de parsing JSON:', parseError);
                console.log('📄 Contenu reçu:', text);
                showNotification('Erreur de traitement de la réponse du serveur', 'error');
            }
        })
        .catch(error => {
            console.error('❌ Erreur réseau:', error);
            showNotification('Erreur de connexion. Veuillez réessayer.', 'error');
        })
        .finally(() => {
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
            console.log('🔄 Bouton réactivé');
        });
}

// Fonction pour gérer la soumission de newsletter
function handleNewsletterSubmit(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    const emailInput = form.querySelector('input[type="email"]');
    const originalButtonHTML = submitButton.innerHTML;
    const emailValue = (emailInput && emailInput.value) ? emailInput.value.trim() : '';

    // Validation de l'email
    if (!emailValue || !validateEmail(emailValue)) {
        showFieldError(emailInput, 'Veuillez saisir une adresse email valide');
        return;
    }

    // Désactiver le bouton et afficher le loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inscription...';

    // Créer FormData
    const formData = new FormData(form);

    const newsletterUrl = (typeof window.baseUrl === 'string' && window.baseUrl)
        ? window.baseUrl + 'newsletter-subscribe.php'
        : 'newsletter-subscribe.php';

    fetch(newsletterUrl, {
        method: 'POST',
        body: formData
    })
        .then(response => response.text())
        .then(text => {
            let data = null;
            try {
                data = JSON.parse(text);
            } catch (_) {
                showNotification('Réponse serveur invalide. Veuillez réessayer.', 'error');
                return;
            }
            if (data.success) {
                showNotification(data.message, 'success');
                showNewsletterSuccessMessage(emailValue);
                form.reset();
            } else {
                showNotification(data.message || 'Une erreur est survenue', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur newsletter:', error);
            showNotification('Erreur de connexion. Veuillez réessayer.', 'error');
        })
        .finally(() => {
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonHTML;
        });
}

// Modal pro pour succès newsletter
function showNewsletterSuccessMessage(email) {
    const existing = document.getElementById('newsletterSuccessModal');
    if (existing) existing.remove();

    const successModal = document.createElement('div');
    successModal.id = 'newsletterSuccessModal';
    successModal.className = 'modal show';
    successModal.innerHTML = `
        <div class="modal-content" style="max-width: 600px; text-align: center;">
            <div class="modal-body" style="padding: 40px 24px;">
                <div style="color: #10b981; font-size: 4rem; margin-bottom: 18px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="color: #1f2937; margin-bottom: 12px;">Abonnement confirmé !</h2>
                <p style="color: #4b5563; margin-bottom: 20px; font-size: 15px; line-height: 1.6;">
                    Merci pour votre abonnement ! Votre demande a été envoyée avec succès.
                </p>
                <div style="background: #f3f4f6; padding: 16px; border-radius: 10px; margin-bottom: 20px;">
                    <p style="color: #374151; font-size: 14px; margin: 0 0 8px 0;">
                        <strong>📧 Votre email</strong><br>
                        <span style="color: #667eea; font-weight: 500;">${email ? email : 'Votre adresse a bien été enregistrée'}</span>
                    </p>
                </div>
                <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 14px; margin: 20px 0; border-radius: 4px; text-align: left;">
                    <p style="color: #1e40af; font-size: 13px; margin: 0; line-height: 1.5;">
                        <strong>✅ Ce qui se passe maintenant :</strong><br>
                        • Un email de confirmation vous a été envoyé<br>
                        • Vous recevrez nos offres d'emploi exclusives<br>
                        • Pensez à vérifier vos spams/promotions
                    </p>
                </div>
                <button type="button" class="btn-primary" onclick="closeNewsletterSuccessModal()" style="margin-top: 10px;">
                    <i class="fas fa-check"></i> Parfait !
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(successModal);
    document.body.style.overflow = 'hidden';

    successModal.addEventListener('click', function (e) {
        if (e.target === successModal) closeNewsletterSuccessModal();
    });

    setTimeout(() => closeNewsletterSuccessModal(), 10000);
}

function closeNewsletterSuccessModal() {
    const modal = document.getElementById('newsletterSuccessModal');
    if (modal) modal.remove();
    document.body.style.overflow = '';
}

// Validation spécifique pour les formulaires de candidature
function validateApplicationForm(form) {
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    // Nettoyer les erreurs précédentes
    form.querySelectorAll('.error-message').forEach(error => error.remove());
    form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));

    requiredFields.forEach(field => {
        const value = field.value.trim();

        if (!value) {
            showFieldError(field, 'Ce champ est obligatoire');
            isValid = false;
        } else if (field.type === 'email' && !validateEmail(value)) {
            showFieldError(field, 'Veuillez saisir une adresse email valide');
            isValid = false;
        }
    });

    // Validation du fichier CV
    const cvInput = form.querySelector('input[type="file"][name="cv"]');
    if (cvInput && cvInput.files.length > 0) {
        const file = cvInput.files[0];
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            showFieldError(cvInput, 'Seuls les fichiers PDF, DOC et DOCX sont autorisés');
            isValid = false;
        } else if (file.size > maxSize) {
            showFieldError(cvInput, 'Le fichier ne doit pas dépasser 5MB');
            isValid = false;
        }
    } else if (cvInput) {
        showFieldError(cvInput, 'Veuillez télécharger votre CV');
        isValid = false;
    }

    // Validation du fichier Lettre de motivation (OBLIGATOIRE)
    const lettreInput = form.querySelector('input[type="file"][name="lettre_motivation"]');
    if (lettreInput && lettreInput.files.length > 0) {
        const file = lettreInput.files[0];
        const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            showFieldError(lettreInput, 'Seuls les fichiers PDF, DOC et DOCX sont autorisés');
            isValid = false;
        } else if (file.size > maxSize) {
            showFieldError(lettreInput, 'Le fichier ne doit pas dépasser 5MB');
            isValid = false;
        }
    } else if (lettreInput) {
        showFieldError(lettreInput, 'Veuillez télécharger votre lettre de motivation');
        isValid = false;
    }

    return isValid;
}

// Fonction pour créer un bouton "Envoyer ma candidature"
function createApplicationButton(jobTitle, jobCompany, jobLocation) {
    const button = document.createElement('button');
    button.className = 'btn-primary application-btn';
    button.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer ma candidature';

    button.addEventListener('click', function () {
        openApplicationModal(jobTitle, jobCompany, jobLocation);
    });

    return button;
}

// Fonction pour ouvrir le modal de candidature
function openApplicationModal(jobTitle, jobCompany, jobLocation, jobDescription, jobTags) {
    console.log('🚀 Ouverture du modal pour:', jobTitle, jobCompany, jobLocation);

    // Créer le modal s'il n'existe pas
    let modal = document.getElementById('applicationModal');
    if (!modal) {
        console.log('📝 Création du modal...');
        modal = createApplicationModal();
        document.body.appendChild(modal);
    }

    // Générer un token CSRF
    generateCSRFToken().then(token => {
        const csrfInput = modal.querySelector('input[name="csrf_token"]');
        if (csrfInput) {
            csrfInput.value = token;
            console.log('🔐 Token CSRF généré et ajouté');
        }
    }).catch(error => {
        console.error('❌ Erreur génération token CSRF:', error);
    });

    // Remplir les informations du poste
    const jobTitleInput = modal.querySelector('input[name="job_title"]');
    const jobCompanyInput = modal.querySelector('input[name="job_company"]');
    const jobLocationInput = modal.querySelector('input[name="job_location"]');

    if (jobTitleInput) jobTitleInput.value = jobTitle || '';
    if (jobCompanyInput) jobCompanyInput.value = jobCompany || '';
    if (jobLocationInput) jobLocationInput.value = jobLocation || '';

    // Remplir l'aperçu de l'offre (affichage dans le modal, avant le formulaire)
    const previewTitleEl = modal.querySelector('.application-job-preview-title');
    const previewCompanyEl = modal.querySelector('.application-job-preview-company');
    const previewLocationEl = modal.querySelector('.application-job-preview-location');
    const previewDescriptionEl = modal.querySelector('.application-job-preview-description');
    const previewTagsEl = modal.querySelector('.application-job-preview-tags');
    const previewBlockEl = modal.querySelector('.application-job-preview');

    if (previewTitleEl) previewTitleEl.textContent = jobTitle || '';
    if (previewCompanyEl) previewCompanyEl.textContent = jobCompany || '';
    if (previewLocationEl) previewLocationEl.textContent = jobLocation || '';
    if (previewDescriptionEl) previewDescriptionEl.textContent = jobDescription || '';

    if (previewTagsEl) {
        previewTagsEl.innerHTML = '';
        const tags = Array.isArray(jobTags) ? jobTags : [];
        tags
            .map(t => String(t || '').trim())
            .filter(Boolean)
            .forEach(tagText => {
                const tag = document.createElement('span');
                tag.className = 'tag';
                tag.textContent = tagText;
                previewTagsEl.appendChild(tag);
            });
    }

    // Masquer le bloc aperçu si aucune donnée n'est disponible
    if (previewBlockEl) {
        const hasAny =
            Boolean((jobTitle || '').trim()) ||
            Boolean((jobCompany || '').trim()) ||
            Boolean((jobLocation || '').trim()) ||
            Boolean((jobDescription || '').trim()) ||
            (Array.isArray(jobTags) && jobTags.length > 0);
        previewBlockEl.style.display = hasAny ? '' : 'none';
    }

    console.log('✅ Informations du poste remplies');

    // Afficher le modal
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';

    console.log('✅ Modal affiché');
}

// Fonction pour générer un token CSRF
async function generateCSRFToken() {
    try {
        const response = await fetch('csrf-token.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        if (!response.ok) {
            throw new Error('Erreur réseau');
        }

        const data = await response.json();
        if (data.success && data.token) {
            return data.token;
        } else {
            throw new Error('Token non généré');
        }
    } catch (error) {
        console.error('Erreur génération token CSRF:', error);
        // Retourner un token temporaire en cas d'erreur
        return 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
}

// Fonction pour créer le modal de candidature
function createApplicationModal() {
    const modal = document.createElement('div');
    modal.id = 'applicationModal';
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Postuler pour ce poste</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="application-job-preview" style="margin-bottom: 16px;">
                    <h3 class="application-job-preview-title" style="margin: 0 0 6px; font-size: 18px;"></h3>
                    <p class="application-job-preview-meta" style="margin: 0 0 10px; opacity: 0.9;">
                        <span class="application-job-preview-company"></span>
                        <span class="application-job-preview-separator" style="margin: 0 8px;">•</span>
                        <span class="application-job-preview-location"></span>
                    </p>
                    <p class="application-job-preview-description" style="margin: 0 0 10px; line-height: 1.5;"></p>
                    <div class="application-job-preview-tags" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
                    <hr class="application-job-preview-divider" style="margin: 16px 0; opacity: 0.25;" />
                </div>
                <form class="application-form" action="submit.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" id="csrf_token" value="">
                    <input type="hidden" name="job_title" value="">
                    <input type="hidden" name="job_company" value="">
                    <input type="hidden" name="job_location" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom_complet">Nom complet</label>
                            <input type="text" id="nom_complet" name="nom_complet" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cv">CV (PDF, DOC, DOCX - Max 5MB)</label>
                        <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                        <small style="color: #6b7280; font-size: 13px; display: block; margin-top: 4px;">
                            Votre CV doit contenir vos coordonnées complètes (nom, téléphone, etc.)
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="lettre_motivation">Lettre de motivation (PDF, DOC, DOCX - Max 5MB)</label>
                        <input type="file" id="lettre_motivation" name="lettre_motivation" accept=".pdf,.doc,.docx" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn-secondary modal-cancel">Annuler</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Envoyer ma candidature
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Ajouter les événements
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = modal.querySelector('.modal-cancel');

    if (closeBtn) {
        closeBtn.addEventListener('click', closeApplicationModal);
        console.log('✅ Événement de fermeture (X) ajouté');
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeApplicationModal);
        console.log('✅ Événement de fermeture (Annuler) ajouté');
    }

    // Fermer en cliquant sur l'arrière-plan
    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeApplicationModal();
        }
    });

    console.log('✅ Modal créé avec succès');
    return modal;
}

// Fonction pour fermer le modal de candidature
function closeApplicationModal() {
    console.log('🚪 Fermeture du modal de candidature');
    const modal = document.getElementById('applicationModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
        console.log('✅ Modal fermé');
    } else {
        console.error('❌ Modal non trouvé pour fermeture');
    }
}

// Initialiser les boutons de candidature existants
document.addEventListener('DOMContentLoaded', function () {
    initApplicationButtons();
});

// Fonction pour initialiser les boutons de candidature (exposée globalement)
function initApplicationButtons() {
    // Chercher tous les boutons "Postuler" existants et les connecter
    const applyButtons = document.querySelectorAll('.btn-apply, .apply-btn, [data-action="apply"]');
    console.log('🔧 Initialisation des boutons de candidature:', applyButtons.length);

    applyButtons.forEach((button, index) => {
        console.log(`🔗 Connexion du bouton ${index + 1}:`, button);

        // Supprimer les anciens événements pour éviter les doublons
        button.removeEventListener('click', handleApplyClick);
        button.addEventListener('click', handleApplyClick);

        // Ajouter un attribut pour identifier les boutons connectés
        button.setAttribute('data-connected', 'true');
    });

    console.log('✅ Tous les boutons de candidature sont connectés');
}

// Fonction pour gérer le clic sur un bouton de candidature
function handleApplyClick(e) {
    e.preventDefault();
    console.log('🔥 Clic sur bouton de candidature détecté');

    // Récupérer les informations du poste depuis les attributs data ou le contexte
    const jobTitle = this.getAttribute('data-job-title') ||
        this.closest('.job-card')?.querySelector('.job-title')?.textContent ||
        'Poste non spécifié';
    const jobCompany = this.getAttribute('data-job-company') ||
        this.closest('.job-card')?.querySelector('.company-name')?.textContent ||
        'Cosmos Group';
    const jobLocation = this.getAttribute('data-job-location') ||
        this.closest('.job-card')?.querySelector('.job-location')?.textContent ||
        'Kinshasa';

    const closestCard = this.closest('.job-card');
    const jobDescription = this.getAttribute('data-job-description') ||
        closestCard?.querySelector('.job-description')?.textContent ||
        '';

    // Récupérer les tags depuis l'attribut data-job-tags ou depuis le DOM
    let jobTags = [];
    const tagsAttr = this.getAttribute('data-job-tags');
    if (tagsAttr) {
        try {
            jobTags = JSON.parse(tagsAttr);
        } catch (e) {
            console.warn('Erreur parsing tags JSON:', e);
        }
    }

    // Fallback: récupérer depuis le DOM si pas dans les attributs
    if (jobTags.length === 0 && closestCard) {
        jobTags = Array.from(closestCard.querySelectorAll('.job-tags .tag')).map(el => el.textContent);
    }

    console.log('📋 Données récupérées:', { jobTitle, jobCompany, jobLocation, jobDescription, jobTags });
    openApplicationModal(jobTitle, jobCompany, jobLocation, jobDescription, jobTags);
}

// Exposer la fonction globalement
window.initApplicationButtons = initApplicationButtons;
// ===== GESTION DU FORMULAIRE DE CONTACT =====
document.addEventListener('DOMContentLoaded', function () {
    // Gestion du formulaire de contact
    // DÉSACTIVÉ : Le formulaire de contact est géré directement dans contact.html
    // pour éviter les doubles soumissions
    /*
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleContactSubmit(this);
        });
    }
    */
});

// Fonction pour gérer la soumission du formulaire de contact
function handleContactSubmit(form) {
    const submitButton = form.querySelector('.btn-submit');
    const originalButtonText = submitButton.innerHTML;

    // Validation du formulaire
    if (!validateContactForm(form)) {
        return;
    }

    // Désactiver le bouton et afficher le loading
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

    // Créer FormData
    const formData = new FormData(form);

    // Envoyer la requête
    fetch('contact-submit.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                form.reset();

                // Message de succès personnalisé
                showContactSuccessMessage();
            } else {
                showNotification(data.message || 'Une erreur est survenue', 'error');

                // Afficher les erreurs spécifiques
                if (data.errors) {
                    Object.keys(data.errors).forEach(field => {
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) {
                            showFieldError(input, data.errors[field]);
                        }
                    });
                }
            }

            // Afficher les avertissements s'il y en a
            if (data.warning) {
                setTimeout(() => {
                    showNotification(data.warning, 'info');
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur de connexion. Veuillez réessayer ou nous contacter directement au +243 98 21 61 066.', 'error');
        })
        .finally(() => {
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalButtonText;
        });
}

// Validation spécifique pour le formulaire de contact
function validateContactForm(form) {
    const requiredFields = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    // Nettoyer les erreurs précédentes
    form.querySelectorAll('.error-message').forEach(error => error.remove());
    form.querySelectorAll('.error').forEach(field => field.classList.remove('error'));

    requiredFields.forEach(field => {
        const value = field.value.trim();

        if (!value) {
            showFieldError(field, 'Ce champ est obligatoire');
            isValid = false;
        } else if (field.type === 'email' && !validateEmail(value)) {
            showFieldError(field, 'Veuillez saisir une adresse email valide');
            isValid = false;
        }
    });

    // Validation du téléphone (optionnel mais si rempli, doit être valide)
    const phoneField = form.querySelector('input[name="phone"]');
    if (phoneField && phoneField.value.trim() && !validatePhone(phoneField.value.trim())) {
        showFieldError(phoneField, 'Veuillez saisir un numéro de téléphone valide');
        isValid = false;
    }

    return isValid;
}

// Fonction pour afficher un message de succès personnalisé
function showContactSuccessMessage() {
    // Créer un modal de succès
    const existing = document.getElementById('contactSuccessModal');
    if (existing) existing.remove();

    const successModal = document.createElement('div');
    successModal.id = 'contactSuccessModal';
    successModal.className = 'modal show';
    successModal.innerHTML = `
        <div class="modal-content" style="max-width: 500px; text-align: center;">
            <div class="modal-body" style="padding: 40px 24px;">
                <div style="color: #10b981; font-size: 4rem; margin-bottom: 20px;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="color: #1f2937; margin-bottom: 16px;">Message envoyé !</h2>
                <p style="color: #6b7280; margin-bottom: 24px;">
                    Merci pour votre message. Notre équipe vous répondra dans les plus brefs délais.
                </p>
                <div style="background: #f3f4f6; padding: 16px; border-radius: 8px; margin-bottom: 24px;">
                    <p style="color: #374151; font-size: 14px; margin: 0;">
                        <strong>Un email de confirmation vient de vous être envoyé</strong><br>
                        Vérifiez votre boîte de réception (et vos spams).
                    </p>
                </div>
                <button type="button" onclick="closeContactSuccessModal()" class="btn-primary">
                    <i class="fas fa-check"></i> Parfait !
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(successModal);
    document.body.style.overflow = 'hidden';

    successModal.addEventListener('click', function (e) {
        if (e.target === successModal) closeContactSuccessModal();
    });

    // Fermer automatiquement après 8 secondes
    setTimeout(() => {
        closeContactSuccessModal();
    }, 8000);
}

// Fonction pour fermer le modal de succès (contact)
function closeContactSuccessModal() {
    const modal = document.getElementById('contactSuccessModal');
    if (modal) modal.remove();
    document.body.style.overflow = '';
}

// Amélioration de la validation des checkboxes
document.addEventListener('DOMContentLoaded', function () {
    // Gestion des checkboxes personnalisées
    const checkboxes = document.querySelectorAll('.checkbox-label input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
            // Supprimer l'erreur si la checkbox est cochée
            if (this.checked) {
                const errorMessage = this.closest('.form-group').querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
                this.classList.remove('error');
            }
        });
    });

    // Validation en temps réel pour les champs du formulaire de contact
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        const inputs = contactForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function () {
                validateSingleField(this);
            });

            input.addEventListener('input', function () {
                // Supprimer l'erreur pendant la saisie
                if (this.classList.contains('error')) {
                    this.classList.remove('error');
                    const errorMessage = this.closest('.form-group').querySelector('.error-message');
                    if (errorMessage) {
                        errorMessage.remove();
                    }
                }
            });
        });
    }
});

// Fonction pour valider un champ individuel
function validateSingleField(field) {
    const value = field.value.trim();
    let isValid = true;

    // Supprimer les erreurs existantes
    field.classList.remove('error');
    const existingError = field.closest('.form-group').querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    // Validation selon le type de champ
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Ce champ est obligatoire');
        isValid = false;
    } else if (field.type === 'email' && value && !validateEmail(value)) {
        showFieldError(field, 'Veuillez saisir une adresse email valide');
        isValid = false;
    } else if (field.type === 'tel' && value && !validatePhone(value)) {
        showFieldError(field, 'Veuillez saisir un numéro de téléphone valide');
        isValid = false;
    }

    return isValid;
}

// Fonction pour afficher les informations de contact de manière interactive
function initContactInfo() {
    // Ajouter des effets hover sur les éléments de contact
    const contactItems = document.querySelectorAll('.contact-item');
    contactItems.forEach(item => {
        item.addEventListener('mouseenter', function () {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.3s ease';
        });

        item.addEventListener('mouseleave', function () {
            this.style.transform = 'translateY(0)';
        });
    });

    // Rendre les numéros de téléphone cliquables
    const phoneNumbers = document.querySelectorAll('.contact-details p');
    phoneNumbers.forEach(p => {
        const text = p.textContent;
        if (text.includes('+243')) {
            const phoneRegex = /(\+243\s?\d{3}\s?\d{3}\s?\d{3})/g;
            const newHTML = text.replace(phoneRegex, '<a href="tel:$1" style="color: #3b82f6; text-decoration: none;">$1</a>');
            if (newHTML !== text) {
                p.innerHTML = newHTML;
            }
        }
    });

    // IMPORTANT: ne pas transformer l'email en mailto (demande client)
}

// Initialiser les améliorations de contact au chargement
document.addEventListener('DOMContentLoaded', function () {
    initContactInfo();
});

// ===== ANIMATION DES STATISTIQUES CIRCULAIRES =====
function animateCircularStats() {
    const circularStats = document.querySelectorAll('.circular-stat-number');

    if (circularStats.length === 0) return;

    const observerOptions = {
        threshold: 0.3,
        rootMargin: '0px'
    };

    const statsObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                animateCircularCounter(entry.target);
                entry.target.classList.add('animated');
            }
        });
    }, observerOptions);

    circularStats.forEach(function (stat) {
        statsObserver.observe(stat);
    });
}

function animateCircularCounter(element) {
    const target = parseInt(element.getAttribute('data-target'));
    const duration = 2000; // 2 seconds
    const steps = 60;
    const increment = target / steps;
    let current = 0;

    const timer = setInterval(function () {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, duration / steps);
}

// Initialiser l'animation des statistiques circulaires au chargement
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', animateCircularStats);
} else {
    animateCircularStats();
}


// ===== FAQ ACCORDION =====
document.addEventListener('DOMContentLoaded', function() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        
        question.addEventListener('click', () => {
            // Fermer tous les autres items
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                }
            });
            
            // Toggle l'item cliqué
            item.classList.toggle('active');
        });
    });
});
