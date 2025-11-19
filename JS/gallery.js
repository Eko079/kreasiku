(function () {
  const DB_ALLOWED = ['portofolio','website','cv','desain','logo'];
  const PATH_PREFIX =
    typeof window.__PATH_PREFIX__ === 'string'
      ? window.__PATH_PREFIX__
      : (location.pathname.includes('/pages/') ? '../' : './');
  const API_BASE = (window.API_BASE || '/kreasiku/api').replace(/\/$/, '');
  const ROOT_BASE = API_BASE.replace(/\/api$/, '');
  const normalizeMedia = (src) => {
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith('/')) return src;
    const clean = src.replace(/^\.?\//,'').replace(/\\/g,'/');
    if (clean.startsWith('storage/')) return ROOT_BASE + '/' + clean;
    if (clean.startsWith('uploads/')) return ROOT_BASE + '/storage/' + clean;
    return ROOT_BASE + '/storage/' + clean;
  };
  const fmtDate = iso => {
    try {
      return new Date(iso).toLocaleDateString('id-ID',{ day:'2-digit', month:'long', year:'numeric' });
    } catch { return ''; }
  };

  function cardHTML(it, base = PATH_PREFIX, options = {}) {
    const variant = options.variant || 'default';
    const isFeatured = variant === 'featured';
    // Legacy & Modern backend support:
    // - Modern V2 sends `images` (array)
    // - Legacy sends `media_path` (single string)
    const rawImage = Array.isArray(it.images) && it.images.length > 0
      ? it.images[0]
      : (it.media_path || it.media_url || it.media || '');
    const imageUrl = normalizeMedia(rawImage);

    const isFigma = (it.kind === 'figma' && (it.figmaUrl || it.figma_url));
    const media = isFigma
      ? `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figmaUrl || it.figma_url)}" loading="lazy"></iframe>`
      : `<img src="${imageUrl}" alt="${it.title || 'Karya'}">`;
    const url = base + "pages/Detail.html?id=" + encodeURIComponent(it.id);
    const badgeLabel = isFigma ? 'Figma' : ((it.category || 'IMG') + '').toUpperCase();
    const badge = isFeatured ? `<span class="card-badge">${badgeLabel}</span>` : '';
    const dateLabel = fmtDate(it.created_at || it.createdAt);
    const catLabel = ((it.category || 'Umum') + '').toUpperCase();
    const meta = isFeatured
      ? `<div class="meta-date"><span>${catLabel}</span><span>${dateLabel || ''}</span></div>`
      : `<div class="meta-date">${dateLabel || ''}</div>`;
    const cardClasses = ['card-item'];
    if (isFeatured) cardClasses.push('card-item--featured');
    return `
      <div class="${cardClasses.join(' ')}">
        <a class="card" href="${url}">
          <div class="card-media">
            ${media}
            ${badge}
            <div class="card-overlay"><span>${it.title || it.category || 'Karya'}</span></div>
          </div>
        </a>
        ${meta}
      </div>`;
  }

  // Lightweight apiFetch wrapper: use global apiFetch if present, otherwise fallback to fetch
  async function doApiFetch(endpoint, options = {}) {
    if (typeof window.apiFetch === 'function') return window.apiFetch(endpoint, options);
    const res = await fetch((window.API_BASE || '/kreasiku/api') + endpoint, {
      credentials: 'include',
      ...options
    });
    const ct = res.headers.get('content-type') || '';
    const json = ct.includes('application/json') ? await res.json() : null;
    if (!res.ok || !json?.ok) {
      const msg = json?.error || ('HTTP_' + res.status);
      throw new Error(msg);
    }
    return json;
  }

  // Single category page (punya #galleryList dan global __PAGE_CATEGORY__)
  const singleGrid = document.getElementById('galleryList');
  if (singleGrid) {
    const cat = (window.__PAGE_CATEGORY__ || '').toLowerCase();
    if (!DB_ALLOWED.includes(cat)) {
      singleGrid.innerHTML = `<div class="muted">Kategori tidak dikenal.</div>`;
      return;
    }
    // Try modern endpoint first, fallback to legacy
    const fetchWithFallback = async (cat, limit) => {
      try {
        // Try modern API endpoint
        const res = await doApiFetch('/designs?category=' + encodeURIComponent(cat) + (limit ? '&limit=' + limit : ''));
        return Array.isArray(res.data) ? res.data : Array.isArray(res.items) ? res.items : [];
      } catch (e) {
        console.warn('Modern API failed, trying legacy:', e.message);
        try {
          // Fallback to legacy endpoint
          const res = await doApiFetch('/designs/list.php?category=' + encodeURIComponent(cat) + (limit ? '&limit=' + limit : ''));
          return Array.isArray(res.data) ? res.data : Array.isArray(res.items) ? res.items : [];
        } catch (e2) {
          throw new Error('Kedua API gagal: ' + e2.message);
        }
      }
    };

    fetchWithFallback(cat).then(items => {
      if (!items.length) {
        singleGrid.innerHTML = `<div class="muted">Belum ada konten di kategori ini. <a href="${PATH_PREFIX}pages/Upload.html" data-requires-auth="true" data-auth-message="Masuk terlebih dahulu untuk mengunggah karya.">Upload sekarang</a>.</div>`;
        return;
      }
      singleGrid.innerHTML = items.map(it => cardHTML(it, PATH_PREFIX, { variant: 'featured' })).join('');
    }).catch(e => {
      singleGrid.innerHTML = `<div class="muted">Gagal memuat: ${e.message}</div>`;
    });
  }

  // Multi-section page (elemen .cards[data-cat])
  document.querySelectorAll('.cards[data-cat]').forEach(async grid => {
    const cat = (grid.dataset.cat || '').toLowerCase();
    if (!DB_ALLOWED.includes(cat)) return;
    
    // Try modern endpoint first, fallback to legacy
    const fetchWithFallback = async (cat, limit) => {
      try {
        // Try modern API endpoint
        const res = await doApiFetch('/designs?category=' + encodeURIComponent(cat) + (limit ? '&limit=' + limit : ''));
        return Array.isArray(res.data) ? res.data : Array.isArray(res.items) ? res.items : [];
      } catch (e) {
        console.warn('Modern API failed for', cat, ', trying legacy:', e.message);
        try {
          // Fallback to legacy endpoint
          const res = await doApiFetch('/designs/list.php?category=' + encodeURIComponent(cat) + (limit ? '&limit=' + limit : ''));
          return Array.isArray(res.data) ? res.data : Array.isArray(res.items) ? res.items : [];
        } catch (e2) {
          throw new Error('Kedua API gagal: ' + e2.message);
        }
      }
    };

    try {
    const items = await fetchWithFallback(cat, 3);
      if (!items.length) {
        grid.innerHTML = `<div class="muted">Belum ada konten. <a href="${PATH_PREFIX}pages/Upload.html" data-requires-auth="true" data-auth-message="Masuk terlebih dahulu untuk mengunggah karya.">Upload sekarang</a>.</div>`;
        return;
      }
      grid.innerHTML = items.map(it => cardHTML(it)).join('');
    } catch (e) {
      grid.innerHTML = `<div class="muted">Gagal memuat: ${e.message}</div>`;
    }
  });
})();
