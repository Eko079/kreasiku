(function () {
  const base = window.__PATH_PREFIX__ || '';
  const grid  = document.getElementById('savedGrid');
  const empty = document.getElementById('savedEmpty') || document.getElementById('emptyState');
  const sortSel = document.getElementById('savedSort') || document.getElementById('sortSelect');
  const API_BASE = (window.API_BASE || '/kreasiku/api').replace(/\/$/, '');
  const ROOT_BASE = API_BASE.replace(/\/api$/, '');
  const authData = getStoredAuth();
  const uidKey = authData ? (authData.uid || authData.email || authData.name || 'anonymous') : 'anonymous';
  if (!authData) {
    window.location.href = base + 'login.html';
    return;
  }

  function fmtDate(iso) {
    try {
      return new Date(iso).toLocaleDateString('id-ID',{ day:'2-digit', month:'long', year:'numeric' });
    } catch { return ''; }
  }

  function getStoredAuth() {
    try { return JSON.parse(localStorage.getItem('authUser') || 'null'); }
    catch { return null; }
  }

  function normalizeMedia(src) {
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith('/')) return src;
    const clean = src.replace(/^\.?\//,'').replace(/\\/g,'/');
    if (clean.startsWith('storage/')) return ROOT_BASE + '/' + clean;
    if (clean.startsWith('uploads/')) return ROOT_BASE + '/storage/' + clean;
    return ROOT_BASE + '/storage/' + clean;
  }

  function updateSidebarProfile(profile, userData) {
    const sbAvatar = document.getElementById('sbAvatar');
    const sbName = document.getElementById('sbName');
    const sbClass = document.getElementById('sbClass');
    const storedAuth = userData || getStoredAuth();
    const firstName = profile?.firstName || storedAuth?.name || storedAuth?.email?.split('@')[0] || 'Pengguna';
    const lastName  = profile?.lastName || '';
    if (sbName) sbName.textContent = `${firstName}${lastName ? ' ' + lastName : ''}`;
    if (sbClass) sbClass.textContent = profile?.kelas || '—';
    if (sbAvatar) {
      const rawPhoto = profile?.photo || profile?.avatar_url || profile?.avatar || storedAuth?.avatar_url || storedAuth?.avatar;
      const photo = normalizeMedia(rawPhoto);
      if (photo) {
        sbAvatar.innerHTML = `<img src="${photo}" alt="Avatar">`;
      } else {
        sbAvatar.textContent = '👤';
      }
    }
  }

  const initialAuth = authData;
  if (initialAuth) {
    const fallback = JSON.parse(localStorage.getItem('kreasiku_profile_' + uidKey) || 'null');
    if (fallback) updateSidebarProfile(fallback, initialAuth);
  }

  async function refreshProfile(){
    const auth = getStoredAuth();
    if (!auth) return;
    try {
      const res = await apiFetch('/users/me');
      if (res?.profile) {
        const key = 'kreasiku_profile_' + uidKey;
        localStorage.setItem(key, JSON.stringify(res.profile));
        window.dispatchEvent(new CustomEvent('profileUpdated', { detail:{ uid: uidKey } }));
        updateSidebarProfile(res.profile, res.user);
      }
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + 'login.html';
        return;
      }
      console.warn('Gagal memuat profil sidebar', err);
    }
  }
  refreshProfile();

  async function ensureSession() {
    try {
      const me = await apiFetch('/auth/me.php');
      if (me?.user) {
        localStorage.setItem('authUser', JSON.stringify(me.user));
        const cachedProfile = JSON.parse(localStorage.getItem('kreasiku_profile_' + uidKey) || 'null');
        updateSidebarProfile(cachedProfile, me.user);
        return me.user;
      }
    } catch (err) {
      console.warn('cek sesi saved gagal', err);
    }

    const stored = getStoredAuth();
    if (!stored?.email) {
      window.location.href = base + 'login.html';
      return null;
    }

    try {
      const resp = await apiFetch('/auth/login.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          email: stored.email,
          name: stored.name || stored.email.split('@')[0] || 'Pengguna'
        })
      });
      localStorage.setItem('authUser', JSON.stringify(resp.user));
      updateSidebarProfile(null, resp.user);
      refreshProfile();
      return resp.user;
    } catch (err) {
      alert('Sesi kamu habis. Silakan login ulang.');
      window.location.href = base + 'login.html';
      return null;
    }
  }

  function card(it) {
    const isFigma = it.kind === 'figma' && (it.figmaUrl || it.figma_url);
    const mediaSrc = normalizeMedia(it.media || it.mediaUrl || it.media_url);
    const media = isFigma
      ? `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figmaUrl || it.figma_url)}" loading="lazy"></iframe>`
      : `<img src="${mediaSrc}" alt="${it.title || 'Karya'}">`;
    const url = `${base}pages/Detail.html?id=${encodeURIComponent(it.id)}`;
    const cat = (it.category || '').toUpperCase();
    const date = fmtDate(it.publishedAt || it.createdAt);
    return `
      <article class="saved-card">
        <a class="preview" href="${url}">
          ${media}
          <span class="preview-badge">${isFigma ? 'Figma' : cat || 'IMG'}</span>
        </a>
        <div class="saved-info">
          <a class="saved-title" href="${url}">${it.title || 'Tanpa Judul'}</a>
          <div class="saved-meta">
            <span>${cat || 'Umum'}</span>
            <span>${date}</span>
          </div>
        </div>
      </article>
    `;
  }

  async function loadSaved() {
    if (!grid) return;
    grid.innerHTML = '<div class="muted">Memuat...</div>';
    const user = await ensureSession();
    if (!user) return;

    const qs = sortSel?.value ? ('?sort=' + encodeURIComponent(sortSel.value)) : '';
    const res = await apiFetch('/me/saved' + qs);
    const items = Array.isArray(res.data) ? res.data : [];

    if (!items.length) {
      grid.innerHTML = '';
      if (empty) {
        empty.hidden = false;
      } else {
        grid.innerHTML = `<div class="muted">Belum ada karya tersimpan.</div>`;
      }
      return;
    }
    if (empty) empty.hidden = true;
    grid.innerHTML = items.map(card).join('');
  }

  sortSel?.addEventListener('change', () => {
    loadSaved().catch(err => {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + 'login.html';
        return;
      }
      if (grid) grid.innerHTML = `<div class="muted">Gagal memuat: ${err.message || err}</div>`;
    });
  });

  loadSaved().catch(err => {
    if (err?.message === 'UNAUTHENTICATED') {
      window.location.href = base + 'login.html';
      return;
    }
    if (grid) grid.innerHTML = `<div class="muted">Gagal memuat: ${err.message || err}</div>`;
  });
})();
