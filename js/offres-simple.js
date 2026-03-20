// Logique Offres d'emploi simplifiée (toutes les offres visibles)
// - Charge toutes les offres depuis api-offres.php
// - Ouvre un modal de détail (api-offre.php)
// - Déclenche le modal de candidature (main.js) avec offer_id

(function () {
  const PER_PAGE = 26;

  function $(id) {
    return document.getElementById(id);
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function nl2brSafe(str) {
    return escapeHtml(str).replace(/\n/g, '<br>');
  }

  function toArray(x) {
    return Array.isArray(x) ? x : [];
  }

  function readCheckedValues(name) {
    return Array.from(document.querySelectorAll(`input[name="${name}"]:checked`)).map((cb) => cb.value);
  }

  function setQueryParams(paramsObj) {
    const url = new URL(window.location.href);
    Object.entries(paramsObj).forEach(([k, v]) => {
      if (v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)) {
        url.searchParams.delete(k);
      } else if (Array.isArray(v)) {
        url.searchParams.delete(k);
        v.forEach((item) => url.searchParams.append(k, item));
      } else {
        url.searchParams.set(k, String(v));
      }
    });
    window.history.replaceState({}, '', url.toString());
  }

  function pushPageParams(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    window.history.pushState({}, '', url.toString());
  }

  function buildApiUrl(state) {
    const url = new URL('api-offres.php', window.location.href);
    url.searchParams.set('activePage', String(state.page));
    url.searchParams.set('expiredPage', '1'); // On ne s'intéresse pas aux expirées
    url.searchParams.set('perPage', String(PER_PAGE));
    url.searchParams.set('sort', state.sort);

    if (state.q) url.searchParams.set('q', state.q);
    if (state.loc) url.searchParams.set('loc', state.loc);

    state.contract.forEach((v) => url.searchParams.append('contract[]', v));
    state.experience.forEach((v) => url.searchParams.append('experience[]', v));
    state.sector.forEach((v) => url.searchParams.append('sector[]', v));

    return url.toString();
  }

  function formatDateFr(dateStr) {
    if (!dateStr) return '';
    const d = new Date(String(dateStr).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(dateStr);
    return d.toLocaleDateString('fr-FR');
  }

  function renderOfferCard(offer) {
    const title = offer.title || 'Offre';
    const company = offer.company || 'COSMOS Group';
    const location = offer.location || '';
    const contract = (offer.contract_type || '').toUpperCase();
    const created = offer.created_at ? formatDateFr(offer.created_at) : '';
    const logo = offer.logo_url || 'logo/cropped-cropped-cropped-Logo-cosmosdrc-1-125x42.png';
    const tags = toArray(offer.tags);

    const badge = contract || 'OFFRE';
    const description = offer.excerpt || '';

    const tagsHtml = tags
      .slice(0, 6)
      .map((t) => `<span class="tag">${escapeHtml(t)}</span>`)
      .join('');

    const applyHtml = `<a href="#" class="btn-apply"
          data-offer-id="${escapeHtml(offer.id)}"
          data-job-title="${escapeHtml(title)}"
          data-job-company="${escapeHtml(company)}"
          data-job-location="${escapeHtml(location)}">Envoyer ma candidature</a>`;

    return `
      <div class="job-card"
           data-offer-id="${escapeHtml(offer.id)}"
           data-contract="${escapeHtml(offer.contract_type || '')}"
           data-experience="${escapeHtml(offer.experience_level || '')}"
           data-sector="${escapeHtml(offer.sector || '')}"
           data-status="${escapeHtml(offer.status || '')}">
        <div class="job-header">
          <div class="company-logo">
            <img src="${escapeHtml(logo)}" alt="${escapeHtml(company)}">
          </div>
          <div class="job-actions">
            <button class="btn-favorite" title="Ajouter aux favoris" data-job-id="${escapeHtml(offer.id)}">
              <i class="far fa-heart"></i>
            </button>
            <div class="job-badge">${escapeHtml(badge)}</div>
          </div>
        </div>
        <h3 class="job-title">${escapeHtml(title)}</h3>
        <p class="company-name">${escapeHtml(company)}</p>
        <p class="job-location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(location)}</p>
        <p class="job-description">${escapeHtml(description)}</p>
        <div class="job-tags">${tagsHtml}</div>
        <div class="job-footer">
          <span class="job-date"><i class="fas fa-clock"></i> ${created ? ('Publié le ' + escapeHtml(created)) : ''}</span>
          ${applyHtml}
        </div>
      </div>
    `;
  }

  function renderEmpty(container, message) {
    container.innerHTML = `
      <div style="padding: 24px; text-align: center; color: var(--text-muted);">
        ${escapeHtml(message)}
      </div>
    `;
  }

  function renderPagination({ numbersEl, prevEl, nextEl, infoEl, page, totalPages, totalItems, perPage, onGo }) {
    const safeTotalPages = Math.max(1, totalPages || 1);
    const safePage = Math.min(Math.max(1, page || 1), safeTotalPages);

    prevEl.disabled = safePage <= 1 || safeTotalPages <= 1;
    nextEl.disabled = safePage >= safeTotalPages || safeTotalPages <= 1;

    const start = totalItems === 0 ? 0 : (safePage - 1) * perPage + 1;
    const end = totalItems === 0 ? 0 : Math.min(safePage * perPage, totalItems);
    infoEl.textContent = `Affichage de ${start} à ${end} sur ${totalItems} offres`;

    const maxButtons = 7;
    const pages = [];
    const push = (p) => pages.push(p);

    if (safeTotalPages <= maxButtons) {
      for (let p = 1; p <= safeTotalPages; p++) push(p);
    } else {
      push(1);
      const left = Math.max(2, safePage - 1);
      const right = Math.min(safeTotalPages - 1, safePage + 1);

      if (left > 2) pages.push('…');
      for (let p = left; p <= right; p++) push(p);
      if (right < safeTotalPages - 1) pages.push('…');
      push(safeTotalPages);
    }

    numbersEl.innerHTML = pages
      .map((p) => {
        if (p === '…') return `<span class="pagination-dots">…</span>`;
        const active = p === safePage ? ' active' : '';
        return `<button class="pagination-number${active}" data-page="${p}">${p}</button>`;
      })
      .join('');

    numbersEl.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const target = parseInt(btn.getAttribute('data-page') || '1', 10);
        if (!Number.isFinite(target) || target === safePage) return;
        onGo(target);
      });
    });

    prevEl.onclick = () => safePage > 1 && onGo(safePage - 1);
    nextEl.onclick = () => safePage < safeTotalPages && onGo(safePage + 1);
  }

  async function fetchJson(url) {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return await res.json();
  }

  function initOfferDetailModal() {
    const modal = $('offerDetailModal');
    const closeX = $('offerDetailClose');
    const closeBtn = $('offerDetailCloseBtn');

    function close() {
      modal.classList.remove('show');
      document.body.style.overflow = '';
      modal.setAttribute('aria-hidden', 'true');
    }

    closeX?.addEventListener('click', close);
    closeBtn?.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modal.classList.contains('show')) close();
    });

    return { open: () => {
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
      modal.setAttribute('aria-hidden', 'false');
    }, close };
  }

  document.addEventListener('DOMContentLoaded', function () {
    const jobsContainer = $('jobs-container');

    if (!jobsContainer) return;

    const modalApi = initOfferDetailModal();
    const modalTitle = $('offerDetailTitle');
    const modalMeta = $('offerDetailMeta');
    const modalDesc = $('offerDetailDescription');
    const modalApply = $('offerDetailApplyBtn');

    const searchInput = $('job-search');
    const locationInput = $('location-search');
    const searchForm = document.querySelector('.search-form-main');
    const sortSelect = $('sort-select');
    const clearFiltersBtn = document.querySelector('.btn-clear-filters');
    const viewBtns = document.querySelectorAll('.view-btn');

    const resultsCountEl = document.querySelector('.results-count');

    const paginationNumbers = $('pagination-numbers');
    const prevBtn = $('prev-btn');
    const nextBtn = $('next-btn');
    const paginationInfo = $('pagination-info');

    const urlParams = new URLSearchParams(window.location.search);
    const state = {
      page: parseInt(urlParams.get('page') || '1', 10) || 1,
      q: urlParams.get('q') || '',
      loc: urlParams.get('loc') || '',
      sort: (urlParams.get('sort') || 'date'),
      contract: readCheckedValues('contract'),
      experience: readCheckedValues('experience'),
      sector: readCheckedValues('sector'),
    };

    if (searchInput && state.q) searchInput.value = state.q;
    if (locationInput && state.loc) locationInput.value = state.loc;
    if (sortSelect) sortSelect.value = state.sort;

    function getFavorites() {
      try {
        return JSON.parse(localStorage.getItem('favorites') || '[]');
      } catch {
        return [];
      }
    }

    function setFavorites(list) {
      localStorage.setItem('favorites', JSON.stringify(list));
    }

    function applyFavoritesUI(container) {
      const favorites = new Set(getFavorites());
      container.querySelectorAll('.btn-favorite').forEach((btn) => {
        const id = btn.getAttribute('data-job-id') || btn.closest('.job-card')?.getAttribute('data-offer-id') || '';
        const icon = btn.querySelector('i');
        const isFav = favorites.has(id);
        btn.classList.toggle('active', isFav);
        if (icon) {
          icon.classList.toggle('fas', isFav);
          icon.classList.toggle('far', !isFav);
        }
      });
    }

    function toggleFavorite(jobId, button) {
      if (!jobId) return;
      const favorites = getFavorites();
      const idx = favorites.indexOf(jobId);
      const isFav = idx !== -1;
      const next = isFav ? favorites.filter((x) => x !== jobId) : favorites.concat([jobId]);
      setFavorites(next);

      const icon = button.querySelector('i');
      button.classList.toggle('active', !isFav);
      if (icon) {
        icon.classList.toggle('fas', !isFav);
        icon.classList.toggle('far', isFav);
      }

      if (window.showNotification) {
        window.showNotification(isFav ? 'Offre retirée des favoris' : 'Offre ajoutée aux favoris', isFav ? 'info' : 'success');
      }
    }

    // Vue grid/list
    viewBtns.forEach((btn) => {
      btn.addEventListener('click', function () {
        viewBtns.forEach((b) => b.classList.remove('active'));
        this.classList.add('active');
        const view = this.dataset.view;
        const cls = view === 'list' ? 'jobs-list' : 'jobs-grid';
        jobsContainer.className = cls;
      });
    });

    async function load() {
      // synchroniser les filtres actuels
      state.contract = readCheckedValues('contract');
      state.experience = readCheckedValues('experience');
      state.sector = readCheckedValues('sector');
      state.q = searchInput ? searchInput.value.trim() : state.q;
      state.loc = locationInput ? locationInput.value.trim() : state.loc;
      state.sort = sortSelect ? sortSelect.value : state.sort;

      const url = buildApiUrl(state);
      try {
        jobsContainer.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Chargement des offres...</p></div>';

        const data = await fetchJson(url);
        if (!data || !data.success) throw new Error('Réponse invalide');

        // On affiche toutes les offres (actives + expirées)
        const allOffers = [...toArray(data.activeOffers), ...toArray(data.expiredOffers)];
        const totalOffers = (data.totalActive || 0) + (data.totalExpired || 0);

        resultsCountEl.textContent = String(totalOffers);

        if (allOffers.length === 0) {
          renderEmpty(jobsContainer, 'Aucune offre ne correspond à vos critères.');
        } else {
          jobsContainer.innerHTML = allOffers.map((o) => renderOfferCard(o)).join('');
        }

        applyFavoritesUI(jobsContainer);

        // Pagination basée sur les offres actives (la logique principale)
        renderPagination({
          numbersEl: paginationNumbers,
          prevEl: prevBtn,
          nextEl: nextBtn,
          infoEl: paginationInfo,
          page: data.activePage,
          totalPages: data.totalActivePages,
          totalItems: totalOffers,
          perPage: PER_PAGE,
          onGo: (p) => {
            state.page = p;
            pushPageParams(state.page);
            load();
            window.scrollTo({ top: jobsContainer.offsetTop - 120, behavior: 'smooth' });
          },
        });

        // Re-brancher les boutons candidature (main.js)
        if (window.initApplicationButtons) {
          window.initApplicationButtons();
        }
      } catch (e) {
        console.error(e);
        renderEmpty(jobsContainer, "Impossible de charger les offres (API indisponible).");
        resultsCountEl.textContent = '0';
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        paginationNumbers.innerHTML = '';
        paginationInfo.textContent = 'Affichage de 0 à 0 sur 0 offres';
      }
    }

    // Détail: délégation de clic
    async function openOfferDetails(offerId) {
      if (!offerId) return;
      try {
        const data = await fetchJson(`api-offre.php?id=${encodeURIComponent(offerId)}`);
        if (!data || !data.success || !data.offer) throw new Error('Réponse invalide');

        const o = data.offer;
        const isExpired = String(o.status || '').toLowerCase() === 'expired' ||
          (o.expires_at && String(o.expires_at) < new Date().toISOString().slice(0, 10));

        modalTitle.textContent = o.title || 'Détail de l\'offre';

        const tags = toArray(o.tags).slice(0, 10).map((t) => `<span class="tag">${escapeHtml(t)}</span>`).join(' ');
        const metaLines = [
          `<p><strong>Entreprise:</strong> ${escapeHtml(o.company || 'COSMOS Group')}</p>`,
          `<p><strong>Lieu:</strong> ${escapeHtml(o.location || '')}</p>`,
          o.contract_type ? `<p><strong>Contrat:</strong> ${escapeHtml(String(o.contract_type).toUpperCase())}</p>` : '',
          o.experience_level ? `<p><strong>Expérience:</strong> ${escapeHtml(o.experience_level)}</p>` : '',
          o.sector ? `<p><strong>Secteur:</strong> ${escapeHtml(o.sector)}</p>` : '',
          o.created_at ? `<p><strong>Publié le:</strong> ${escapeHtml(formatDateFr(o.created_at))}</p>` : '',
          o.expires_at ? `<p><strong>Expire le:</strong> ${escapeHtml(formatDateFr(o.expires_at))}</p>` : '',
          tags ? `<div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px;">${tags}</div>` : '',
          isExpired ? `<p style="margin-top: 10px; color: #b91c1c;"><strong>Offre expirée</strong></p>` : '',
        ].filter(Boolean);

        modalMeta.innerHTML = metaLines.join('');
        modalDesc.innerHTML = `<div style="line-height: 1.65;">${nl2brSafe(o.description || '')}</div>`;

        if (isExpired) {
          modalApply.disabled = true;
          modalApply.style.opacity = '0.6';
          modalApply.style.cursor = 'not-allowed';
        } else {
          modalApply.disabled = false;
          modalApply.style.opacity = '';
          modalApply.style.cursor = '';
        }

        modalApply.onclick = function () {
          if (modalApply.disabled) return;
          modalApi.close();
          if (typeof window.openApplicationModal === 'function') {
            window.openApplicationModal(o.title || '', o.company || 'COSMOS Group', o.location || '', o.description || '', toArray(o.tags));
          } else {
            alert("Le formulaire de candidature n'est pas disponible.");
          }
        };

        modalApi.open();
      } catch (e) {
        console.error(e);
        if (window.showNotification) {
          window.showNotification("Impossible d'ouvrir le détail de l'offre.", 'error');
        } else {
          alert("Impossible d'ouvrir le détail de l'offre.");
        }
      }
    }

    function cardClickHandler(e) {
      const favBtn = e.target.closest('.btn-favorite');
      if (favBtn) {
        e.preventDefault();
        const id = favBtn.getAttribute('data-job-id') || favBtn.closest('.job-card')?.getAttribute('data-offer-id') || '';
        toggleFavorite(String(id), favBtn);
        return;
      }
      if (e.target.closest('.btn-apply')) return;
      const card = e.target.closest('.job-card');
      if (!card) return;
      const offerId = card.getAttribute('data-offer-id') || card.dataset.offerId;
      openOfferDetails(offerId);
    }

    jobsContainer.addEventListener('click', cardClickHandler);

    // Search / filtres => relance depuis page 1
    const debounce = (fn, wait) => {
      let t;
      return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
      };
    };

    const triggerReload = () => {
      state.page = 1;
      setQueryParams({
        page: state.page,
        q: searchInput ? searchInput.value.trim() : '',
        loc: locationInput ? locationInput.value.trim() : '',
        sort: sortSelect ? sortSelect.value : 'date',
      });
      load();
    };

    if (searchForm) {
      searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerReload();
      });
    }
    if (searchInput) searchInput.addEventListener('input', debounce(triggerReload, 250));
    if (locationInput) locationInput.addEventListener('input', debounce(triggerReload, 250));
    if (sortSelect) sortSelect.addEventListener('change', triggerReload);

    document.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
      cb.addEventListener('change', triggerReload);
    });

    if (clearFiltersBtn) {
      clearFiltersBtn.addEventListener('click', () => {
        document.querySelectorAll('input[type="checkbox"]').forEach((cb) => { cb.checked = false; });
        if (searchInput) searchInput.value = '';
        if (locationInput) locationInput.value = '';
        triggerReload();
      });
    }

    // Première charge
    setQueryParams({ page: state.page, q: state.q, loc: state.loc, sort: state.sort });
    load();

    // Gestion retour navigateur
    window.addEventListener('popstate', () => {
      const p = new URLSearchParams(window.location.search);
      state.page = parseInt(p.get('page') || '1', 10) || 1;
      state.q = p.get('q') || '';
      state.loc = p.get('loc') || '';
      state.sort = p.get('sort') || 'date';
      if (searchInput) searchInput.value = state.q;
      if (locationInput) locationInput.value = state.loc;
      if (sortSelect) sortSelect.value = state.sort;
      load();
    });
  });
})();