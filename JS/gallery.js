(function () {
  const DB_ALLOWED = ['portofolio','website','cv','desain','logo'];
  const fmtDate = iso => {
    try {
      return new Date(iso).toLocaleDateString('id-ID',{ day:'2-digit', month:'long', year:'numeric' });
    } catch { return ''; }
  };

  function cardHTML(it, base = '../') {
    const media = (it.kind === 'figma' && it.figma_url)
      ? `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figma_url)}" loading="lazy"></iframe>`
      : `<img src="${it.media_url}" alt="${it.title || 'Karya'}">`;
    const url = base + "pages/Detail.html?id=" + encodeURIComponent(it.id);
    return `
      <div class="card-item">
        <a class="card" href="${url}">
          <div class="card-media">
            ${media}
            <div class="card-overlay"><span>${it.title || it.category || 'Karya'}</span></div>
          </div>
        </a>
        <div class="meta-date">${fmtDate(it.created_at)}</div>
      </div>`;
  }

  // Single category page (punya #galleryList dan global __PAGE_CATEGORY__)
  const singleGrid = document.getElementById('galleryList');
  if (singleGrid) {
    const cat = (window.__PAGE_CATEGORY__ || '').toLowerCase();
    if (!DB_ALLOWED.includes(cat)) {
      singleGrid.innerHTML = `<div class="muted">Kategori tidak dikenal.</div>`;
      return;
    }
    apiFetch('/designs/list.php?category=' + encodeURIComponent(cat))
      .then(res => {
        const items = Array.isArray(res.items) ? res.items : [];
        if (!items.length) {
          singleGrid.innerHTML = `<div class="muted">Belum ada konten di kategori ini. <a href="../pages/Upload.html">Upload sekarang</a>.</div>`;
          return;
        }
        singleGrid.innerHTML = items.map(it => cardHTML(it, '../')).join('');
      })
      .catch(e => {
        singleGrid.innerHTML = `<div class="muted">Gagal memuat: ${e.message}</div>`;
      });
  }

  // Multi-section page (elemen .cards[data-cat])
  document.querySelectorAll('.cards[data-cat]').forEach(async grid => {
    const cat = (grid.dataset.cat || '').toLowerCase();
    if (!DB_ALLOWED.includes(cat)) return;
    try {
      const res = await apiFetch('/designs/list.php?category=' + encodeURIComponent(cat) + '&limit=3');
      const items = Array.isArray(res.items) ? res.items : [];
      if (!items.length) {
        grid.innerHTML = `<div class="muted">Belum ada konten. <a href="../pages/Upload.html">Upload sekarang</a>.</div>`;
        return;
      }
      grid.innerHTML = items.map(it => cardHTML(it, '../')).join('');
    } catch (e) {
      grid.innerHTML = `<div class="muted">Gagal memuat: ${e.message}</div>`;
    }
  });
})();
