(function () {
  const grid  = document.getElementById('savedGrid');
  const empty = document.getElementById('savedEmpty');
  const sortSel = document.getElementById('savedSort'); // optional: newest|oldest|likes|stars

  function fmtDate(iso) {
    try {
      return new Date(iso).toLocaleDateString('id-ID',{ day:'2-digit', month:'long', year:'numeric' });
    } catch { return ''; }
  }

  function card(it) {
    const media = (it.kind === 'figma' && it.figma_url)
      ? `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figma_url)}" loading="lazy"></iframe>`
      : `<img src="${it.media_url}" alt="${it.title || 'Karya'}">`;
    const url = "../pages/Detail.html?id=" + encodeURIComponent(it.id);
    return `
      <div class="card-item">
        <a class="card" href="${url}">
          <div class="card-media">
            ${media}
            <div class="card-overlay"><span>${it.title || it.category || 'Karya'}</span></div>
          </div>
        </a>
        <div class="meta-date">${fmtDate(it.created_at)}</div>
      </div>
    `;
  }

  async function loadSaved() {
    const qs = sortSel?.value ? ('?sort=' + encodeURIComponent(sortSel.value)) : '';
    const res = await apiFetch('/designs/saved.php' + qs);
    const items = Array.isArray(res.items) ? res.items : [];

    if (!items.length) {
      if (grid) grid.innerHTML = '';
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    if (grid) grid.innerHTML = items.map(card).join('');
  }

  sortSel?.addEventListener('change', loadSaved);
  loadSaved().catch(err => {
    if (grid) grid.innerHTML = `<div class="muted">Gagal memuat: ${err.message}</div>`;
  });
})();
