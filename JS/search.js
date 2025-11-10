(function () {
  const $ = s => document.querySelector(s);
  const grid  = $('#resultGrid');
  const empty = $('#emptyState');
  const meta  = $('#resultMeta');

  const qInput = $('#q');
  const sortSel= $('#sortSel'); // relevant|newest|oldest|likes|stars
  const catSel = $('#catSel');  // ''|portofolio|...
  const dlSel  = $('#dlSel');   // ''|allowed|blocked
  const btnSearch = $('#btnSearch');

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
        <div class="card-date">${fmtDate(it.created_at)}</div>
      </div>`;
  }

  async function runSearch() {
    const params = new URLSearchParams();
    if (qInput?.value) params.set('q', qInput.value.trim());
    if (catSel?.value) params.set('category', catSel.value);
    if (sortSel?.value && sortSel.value !== 'relevant') params.set('sort', sortSel.value);
    if (dlSel?.value) params.set('download', dlSel.value); // allowed|blocked

    const res = await apiFetch('/designs/list.php' + (params.toString() ? ('?' + params.toString()) : ''));
    const items = Array.isArray(res.items) ? res.items : [];
    meta.textContent = `${items.length} hasil`;

    if (!items.length) {
      grid.innerHTML = '';
      empty.hidden = false;
      return;
    }
    empty.hidden = true;
    grid.innerHTML = items.map(card).join('');
  }

  btnSearch?.addEventListener('click', runSearch);
  qInput?.addEventListener('keydown', e => { if (e.key === 'Enter') runSearch(); });
  [sortSel, catSel, dlSel].forEach(el => el?.addEventListener('change', runSearch));

  // hydrate ?q=
  const urlQ = new URLSearchParams(location.search).get('q') || '';
  if (qInput) qInput.value = urlQ;
  runSearch().catch(err => {
    grid.innerHTML = `<div class="muted">Gagal memuat: ${err.message}</div>`;
  });
})();
