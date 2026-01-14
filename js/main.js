// JavaScript principal pour COSMOS GROUP
document.addEventListener('DOMContentLoaded', function() {
    var navToggle = document.querySelector('.nav-toggle');
    var navMenu = document.querySelector('.nav-menu');
    
    if (navToggle && navMenu) {
        // Supprimer les anciens écouteurs d'événements s'ils existent
        var newNavToggle = navToggle.cloneNode(true);
        navToggle.parentNode.replaceChild(newNavToggle, navToggle);
        navToggle = newNavToggle;
        
        navToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
        
        // Fermer le menu en cliquant en dehors
        document.addEventListener('click', function(e) {
            if (!navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            }
        });
    }
    
    var dropdownItems = document.querySelectorAll('.nav-dropdown');
    
    for (var i = 0; i < dropdownItems.length; i++) {
        (function(dropdown) {
            var dropdownToggle = dropdown.querySelector('.dropdown-toggle');
            var dropdownMenu = dropdown.querySelector('.dropdown-menu');
            
            dropdown.addEventListener('mouseenter', function() {
                if (window.innerWidth > 768) {
                    dropdown.classList.add('active');
                }
            });
            
            dropdown.addEventListener('mouseleave', function() {
                if (window.innerWidth > 768) {
                    dropdown.classList.remove('active');
                }
            });
            
            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', function(e) {
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
    
    document.addEventListener('click', function(e) {
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
        (function(link) {
            link.addEventListener('click', function() {
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
    
    window.addEventListener('resize', function() {
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
        (function(button) {
            button.addEventListener('click', function() {
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
        searchForm.addEventListener('submit', function(e) {
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
        var observer = new IntersectionObserver(function(entries) {
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
        link.addEventListener('click', function(e) {
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
    
    window.addEventListener('scroll', function() {
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
        
        setTimeout(function() {
            if (notification && notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
        
        var closeBtn = notification.querySelector('.notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
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
    
    // Gestion des favoris (localStorage)
    var favoriteButtons = document.querySelectorAll('.btn-favorite');
    for (var fbIndex = 0; fbIndex < favoriteButtons.length; fbIndex++) {
        (function(button) {
            button.addEventListener('click', function(e) {
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
        (function(input) {
            var timeout;
            input.addEventListener('input', function() {
                clearTimeout(timeout);
                var self = this;
                timeout = setTimeout(function() {
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
    
    window.acceptCookies = function() {
        localStorage.setItem('cookieConsent', 'true');
        document.querySelector('.cookie-banner').remove();
    };
    
    initCookieConsent();
    
    // Lazy loading des images
    var images = document.querySelectorAll('img[data-src]');
    if (window.IntersectionObserver) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
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
});

window.utils = {
    formatDate: function(date) {
        return new Intl.DateTimeFormat('fr-FR').format(new Date(date));
    },
    
    formatSalary: function(salary) {
        return new Intl.NumberFormat('fr-FR').format(salary) + ' FC';
    },
    
    debounce: function(func, wait) {
        var timeout;
        return function() {
            var args = arguments;
            var later = function() {
                clearTimeout(timeout);
                func.apply(null, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};
