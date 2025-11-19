(function () {
  const $ = s => document.querySelector(s);
  const API_BASE = window.API_BASE || '/kreasiku/api';
  const PREFIX =
    window.__PATH_PREFIX__ ||
    (location.pathname.includes('/pages/') ? '../' : './');

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

  function ownerHTML(data) {
    const avatar = data.ownerAvatar;
    const name   = data.ownerName || 'Pengguna';
    const createdAt = data.createdAt;
    const av = avatar
      ? `<div class="owner-avatar"><img src="${avatar}" alt="${name}"></div>`
      : `<div class="owner-avatar">👤</div>`;
    return `
      <div class="ob-left">
        ${av}
        <div class="owner-meta">
          <div class="name">${name}</div>
          <div class="sub">${fmtDate(createdAt)}</div>
        </div>
      </div>
    `;
  }

  function renderMedia(d) {
    const figmaUrl = d.figmaUrl;
    const images   = Array.isArray(d.images) ? d.images.filter(Boolean) : [];
    const mediaUrl = images[0] || d.media || '';
    if (d.kind === 'figma' && figmaUrl) {
      mediaWrap.innerHTML = `
        <div class="figma-embed">
          <iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(figmaUrl)}" loading="lazy"></iframe>
        </div>`;
      btnOpenLink.hidden = false;
      btnOpenLink.href   = figmaUrl;
      btnDownload.hidden = true;
      btnDownload.removeAttribute('href');
      btnDownload.removeAttribute('data-url');
    } else if (images.length > 1) {
      const main = images[0];
      const extras = images.slice(1, 3);
      const stackHtml = extras.length
        ? `<div class="stack">
            ${extras.map((img, idx) => `<div class="small"><img src="${img}" alt="${d.title || 'karya'}-${idx + 2}"></div>`).join('')}
          </div>`
        : '';
      mediaWrap.innerHTML = `
        <div class="img-grid">
          <div class="big"><img src="${main}" alt="${d.title || 'karya'}"></div>
          ${stackHtml}
        </div>`;
      btnOpenLink.hidden = true;
      btnOpenLink.removeAttribute('href');
      if (d.allowDownload && d.downloadUrl) {
        btnDownload.hidden = false;
        btnDownload.dataset.url = d.downloadUrl;
        btnDownload.removeAttribute('href');
      } else {
        btnDownload.hidden = true;
        btnDownload.removeAttribute('data-url');
      }
    } else if (mediaUrl) {
      mediaWrap.innerHTML = `
        <div class="image-box">
          <img src="${mediaUrl}" alt="${d.title || 'karya'}">
        </div>`;
      btnOpenLink.hidden = true;
      btnOpenLink.removeAttribute('href');
      if (d.allowDownload && d.downloadUrl) {
        btnDownload.hidden = false;
        btnDownload.dataset.url = d.downloadUrl;
        btnDownload.removeAttribute('href');
      } else {
        btnDownload.hidden = true;
        btnDownload.removeAttribute('data-url');
      }
    } else {
      mediaWrap.innerHTML = `<div class="muted">Media tidak tersedia</div>`;
      btnOpenLink.hidden = true;
      btnOpenLink.removeAttribute('href');
      btnDownload.hidden = true;
      btnDownload.removeAttribute('data-url');
    }
  }

  const asBool = (val) => {
    if (typeof val === 'boolean') return val;
    if (typeof val === 'number') return val === 1;
    if (typeof val === 'string') return val === '1' || val.toLowerCase() === 'true';
    return !!val;
  };

  function convertMediaList(raw) {
    const list = [];
    if (Array.isArray(raw.images)) list.push(...raw.images);
    if (raw.media) list.push(raw.media);
    if (raw.media_url) list.push(raw.media_url);
    if (raw.mediaPath) list.push(raw.mediaPath);
    if (raw.media_path) list.push(raw.media_path);

    const base = API_BASE.replace(/\/$/, '');
    const toAbs = (src) => {
      if (!src) return null;
      if (/^https?:\/\//.test(src)) return src;
      if (src.startsWith('/')) return src;
      let clean = src.replace(/^\.?\//, '');
      clean = clean.replace(/\\/g, '/');
      if (clean.startsWith('storage/')) {
        return `${base}/${clean}`;
      }
      if (clean.startsWith('uploads/')) {
        return `${base}/storage/${clean}`;
      }
      return `${base}/${clean}`;
    };
    return list.map(toAbs).filter(Boolean);
  }

  function normalizeDetail(res) {
    const raw = res?.data || res?.design || res;
    if (!raw) throw new Error('INVALID_RESPONSE');

    const figmaUrl = raw.figmaUrl || raw.figma_url || '';
    const images = convertMediaList(raw);
    const mediaAbsolute = images[0] || null;
    const allowDownload = asBool(raw.allowDownload ?? raw.allow_download);
    const commentEnabled = asBool(raw.commentEnabled ?? raw.allow_comments ?? raw.comment_enabled);

    return {
      id: raw.id,
      kind: raw.kind || raw.type,
      title: raw.title,
      desc: raw.desc || raw.description || '',
      category: raw.category,
      ownerName: raw.ownerName || raw.owner_name || raw.owner?.name || 'Pengguna',
      ownerAvatar: raw.ownerAvatar || raw.owner_avatar || raw.owner?.avatar || null,
      createdAt: raw.createdAt || raw.created_at,
      allowDownload,
      downloadUrl: allowDownload
        ? (raw.downloadUrl || `${API_BASE}/designs/${encodeURIComponent(raw.id)}/download`)
        : null,
      commentEnabled,
      figmaUrl,
      media: mediaAbsolute,
      images,
      likesCount: raw.likesCount ?? raw.likes_count ?? 0,
      savesCount: raw.savesCount ?? raw.saves_count ?? 0,
      liked: raw.liked ?? raw.liked_by_me ?? false,
      saved: raw.saved ?? raw.saved_by_me ?? false,
    };
  }

  async function fetchDetailData() {
    try {
      const modern = await apiFetch('/designs/' + encodeURIComponent(id));
      return normalizeDetail(modern);
    } catch (err) {
      console.warn('Modern detail API gagal, fallback legacy:', err.message);
      const legacy = await apiFetch('/designs/detail.php?id=' + encodeURIComponent(id));
      return normalizeDetail(legacy);
    }
  }

  async function loadDetail() {
    const d = await fetchDetailData();
    ownerBar.innerHTML = ownerHTML(d);
    descText.textContent = d.desc || '';

    likeCount.textContent = d.likesCount ?? 0;
    saveCount.textContent = d.savesCount ?? 0;

    btnLike.classList.toggle('active', !!d.liked);
    btnSave.classList.toggle('active', !!d.saved);

    renderMedia(d);

    // comments
    if (d.commentEnabled) {
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
        <div class="c-text">${c.body || c.text || ''}</div>
      </li>
    `).join('');
  }

  // events
  const formHeader = {'Content-Type':'application/x-www-form-urlencoded'};

  const ensureAuth = (message) => {
    const logged = typeof window.isUserLoggedIn === 'function'
      ? window.isUserLoggedIn()
      : !!localStorage.getItem('authUser');
    if (logged) return true;
    if (typeof window.promptLogin === 'function') {
      window.promptLogin(message || 'Silakan login untuk melanjutkan.');
    } else if (typeof window.notifyUser === 'function') {
      window.notifyUser({
        title: 'Butuh login',
        message: message || 'Silakan login untuk melanjutkan.',
        type: 'warning',
        actionText: 'Login',
        onAction: () => { location.href = PREFIX + 'login.html'; }
      });
    } else {
      alert(message || 'Silakan login untuk melanjutkan.');
      location.href = PREFIX + 'login.html';
    }
    return false;
  };

  const showActionError = (message) => {
    if (typeof window.notifyUser === 'function') {
      window.notifyUser({
        title: 'Ups',
        message,
        type: 'warning'
      });
    } else {
      alert(message);
    }
  };

  btnLike?.addEventListener('click', async () => {
    if (!ensureAuth('Masuk terlebih dahulu untuk menyukai karya.')) return;
    try {
      const payload = new URLSearchParams({ design_id: id });
      const r = await apiFetch('/designs/like_toggle.php', {
        method:'POST',
        headers: formHeader,
        body: payload.toString()
      });
      likeCount.textContent = r.likes_count ?? 0;
      btnLike.classList.toggle('active', !!r.liked);
    } catch (e) {
      showActionError('Gagal menyukai: ' + (e.message || 'Coba lagi nanti.'));
    }
  });

  btnSave?.addEventListener('click', async () => {
    if (!ensureAuth('Masuk terlebih dahulu untuk menyimpan karya.')) return;
    try {
      const payload = new URLSearchParams({ design_id: id });
      const r = await apiFetch('/designs/save_toggle.php', {
        method:'POST',
        headers: formHeader,
        body: payload.toString()
      });
      saveCount.textContent = r.saves_count ?? 0;
      btnSave.classList.toggle('active', !!r.saved);
    } catch (e) {
      showActionError('Gagal menyimpan: ' + (e.message || 'Coba lagi nanti.'));
    }
  });

  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = (input.value || '').trim();
    if (!text) return;
    if (!ensureAuth('Masuk terlebih dahulu untuk mengirim komentar.')) return;
    try {
      const payload = new URLSearchParams({ design_id: id, text });
      await apiFetch('/comments/create.php', {
        method:'POST',
        headers: formHeader,
        body: payload.toString()
      });
      input.value = '';
      await loadComments();
    } catch (e) {
      showActionError('Gagal mengirim komentar: ' + (e.message || 'Coba lagi nanti.'));
    }
  });

  // init
  loadDetail().catch(err => {
    mediaWrap.innerHTML = `<div class="muted">Gagal memuat detail: ${err.message}</div>`;
  });

  btnDownload?.addEventListener('click', async (e) => {
    const url = btnDownload.dataset.url;
    if (!url) { e.preventDefault(); return; }
    e.preventDefault();
    try {
      const resp = await fetch(url, { credentials: 'include' });
      if (!resp.ok) throw new Error('Gagal mengunduh');
      const blob = await resp.blob();
      const disp = resp.headers.get('content-disposition') || '';
      const match = disp.match(/filename=\"?([^\";]+)\"?/i);
      const fallback = `kreasiku-${id}${blob.type.includes('png')?'.png':blob.type.includes('jpeg')?'.jpg':''}`;
      const filename = match ? decodeURIComponent(match[1]) : fallback;
      const objectUrl = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = objectUrl;
      anchor.download = filename || `kreasiku-${id}`;
      document.body.appendChild(anchor);
      anchor.click();
      document.body.removeChild(anchor);
      setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
    } catch (err) {
      alert(err.message || 'Download gagal');
    }
  });
})();
