// Améliorations SEO pour COSMOS Group
document.addEventListener('DOMContentLoaded', function() {
    
    // ===== TRACKING DES INTERACTIONS UTILISATEUR =====
    
    // Tracking des clics sur les offres d'emploi
    function trackJobClick(jobTitle, jobCompany, jobLocation) {
        // Google Analytics 4 (remplacez par votre ID de mesure)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'job_click', {
                'job_title': jobTitle,
                'job_company': jobCompany,
                'job_location': jobLocation,
                'page_location': window.location.href
            });
        }
    }
    
    // Tracking des recherches
    function trackJobSearch(searchTerm, location, filters) {
        if (typeof gtag !== 'undefined') {
            gtag('event', 'search', {
                'search_term': searchTerm,
                'search_location': location,
                'search_filters': JSON.stringify(filters)
            });
        }
    }
    
    // ===== AMÉLIORATION DE L'EXPÉRIENCE UTILISATEUR =====
    
    // Lazy loading des images
    function initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
    
    // ===== OPTIMISATION DES URLS =====
    
    // Mise à jour de l'URL sans rechargement de page
    function updateURL(params) {
        const url = new URL(window.location);
        Object.keys(params).forEach(key => {
            if (params[key]) {
                url.searchParams.set(key, params[key]);
            } else {
                url.searchParams.delete(key);
            }
        });
        
        window.history.pushState({}, '', url);
        updateMetaTags(params);
    }
    
    // Mise à jour des meta tags pour le SEO dynamique
    function updateMetaTags(params) {
        const title = document.querySelector('title');
        const description = document.querySelector('meta[name="description"]');
        
        if (params.q && params.q.trim()) {
            const searchTerm = params.q.trim();
            title.textContent = `Emploi ${searchTerm} en RDC | COSMOS Group`;
            description.content = `Trouvez des offres d'emploi ${searchTerm} en RDC. Opportunités à Kinshasa.`;
        }
    }
    
    // Initialisation
    initLazyLoading();
    
    // Exposer les fonctions globalement
    window.trackJobClick = trackJobClick;
    window.trackJobSearch = trackJobSearch;
    window.updateURL = updateURL;
});