(function () {
  const $ = s => document.querySelector(s);

  const ownerBar   = $('#ownerBar');
  const mediaWrap  = $('#mediaWrap');
  const descText   = $('#descText');

  const btnLike    = $('#btnLike');
  const btnSave    = $('#btnSave');
  const likeCount  = $('#likeCount');
  const saveCount  = $('#saveCount');

  const btnOpenLink= $('#btnOpenLink');
  const btnDownload= $('#btnDownload');

  const form       = $('#commentForm');
  const input      = $('#commentInput');
  const list       = $('#commentList');

  const params = new URLSearchParams(location.search);
  const id = params.get('id');

  if (!id) {
    mediaWrap.innerHTML = `<div class="muted">Karya tidak ditemukan.</div>`;
    return;
  }

  function fmtDate(iso) {
    try {
      return new Date(iso).toLocaleString('id-ID', {
        day:'2-digit', month:'long', year:'numeric',
        hour:'2-digit', minute:'2-digit'
      });
    } catch { return ''; }
  }

  function ownerHTML(owner, created_at) {
    const av = owner?.avatar
      ? `<img class="avatar" src="${owner.avatar}" alt="">`
      : `<div class="avatar">👤</div>`;
    return `
      <div class="ob-left">
        ${av}
        <div>
          <div class="ob-name">${owner?.name || 'Pengguna'}</div>
          <div class="ob-date">${fmtDate(created_at)}</div>
        </div>
      </div>
    `;
  }

  function renderMedia(d) {
    if (d.kind === 'figma' && d.figma_url) {
      mediaWrap.innerHTML = `
        <div class="figma-embed">
          <iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(d.figma_url)}" loading="lazy"></iframe>
        </div>`;
      btnOpenLink.hidden = false;
      btnOpenLink.href   = d.figma_url;
      btnDownload.hidden = true;
    } else {
      mediaWrap.innerHTML = `
        <div class="image-box">
          <img src="${d.media_url}" alt="${d.title || 'karya'}">
        </div>`;
      btnOpenLink.hidden = true;
      if (d.allow_download && d.media_url) {
        btnDownload.hidden = false;
        btnDownload.href   = d.media_url;
      } else {
        btnDownload.hidden = true;
      }
    }
  }

  async function loadDetail() {
    const res = await apiFetch('/designs/detail.php?id=' + encodeURIComponent(id));
    const d = res.design;

    ownerBar.innerHTML = ownerHTML(d.owner, d.created_at);
    descText.textContent = d.description || '';

    likeCount.textContent = d.likes_count ?? 0;
    saveCount.textContent = d.saves_count ?? 0;

    btnLike.classList.toggle('active', !!d.liked_by_me);
    btnSave.classList.toggle('active', !!d.saved_by_me);

    renderMedia(d);

    // comments
    if (d.allow_comments) {
      form.style.display = '';
      await loadComments();
    } else {
      form.style.display = 'none';
      list.innerHTML = `<li class="muted">Komentar dimatikan oleh pemilik.</li>`;
    }
  }

  async function loadComments() {
    const res = await apiFetch('/comments/list.php?design_id=' + encodeURIComponent(id));
    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      list.innerHTML = `<li class="muted">Belum ada komentar</li>`;
      return;
    }
    list.innerHTML = items.map(c => `
      <li>
        <div class="c-head">
          <span class="c-name">${c.user_name || 'Anonim'}</span>
          <span class="c-date">${fmtDate(c.created_at)}</span>
        </div>
        <div class="c-text">${c.text}</div>
      </li>
    `).join('');
  }

  // events
  btnLike?.addEventListener('click', async () => {
    try {
      const r = await apiFetch('/designs/like_toggle.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id })
      });
      likeCount.textContent = r.likes_count ?? 0;
      btnLike.classList.toggle('active', !!r.liked);
    } catch (e) {
      alert('Gagal menyukai: ' + e.message);
    }
  });

  btnSave?.addEventListener('click', async () => {
    try {
      const r = await apiFetch('/designs/save_toggle.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id })
      });
      saveCount.textContent = r.saves_count ?? 0;
      btnSave.classList.toggle('active', !!r.saved);
    } catch (e) {
      alert('Gagal menyimpan: ' + e.message);
    }
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = (input.value || '').trim();
    if (!text) return;
    try {
      await apiFetch('/comments/create.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ design_id: id, text })
      });
      input.value = '';
      await loadComments();
    } catch (e) {
      alert('Gagal mengirim komentar: ' + e.message);
    }
  });

  // init
  loadDetail().catch(err => {
    mediaWrap.innerHTML = `<div class="muted">Gagal memuat detail: ${err.message}</div>`;
  });
})();
