(function(){
  const base = window.__PATH_PREFIX__ || "";
  const galleryEl = document.getElementById("myGallery");

  function getStoredAuth() {
    try { return JSON.parse(localStorage.getItem('authUser') || "null"); }
    catch { return null; }
  }

  let auth = getStoredAuth();
  if (!auth){
    window.location.href = base + "login.html";
    return;
  }

  const uid = auth.uid || auth.email || auth.name || "anonymous";

  function renderSidebar(profile){
    const sbAvatar = document.getElementById("sbAvatar");
    const sbName   = document.getElementById("sbName");
    const sbClass  = document.getElementById("sbClass");
    if (sbName) {
      const firstName = profile?.firstName || auth.name || auth.email?.split("@")[0] || "Pengguna";
      const lastName  = profile?.lastName || "";
      sbName.textContent = `${firstName.toLowerCase()}${lastName ? " " + lastName.toLowerCase() : ""}`;
    }
    if (sbClass) sbClass.textContent = profile?.kelas || "—";
    if (sbAvatar) {
      if (profile?.photo) {
        sbAvatar.innerHTML = `<img src="${profile.photo}" alt="Foto profil">`;
      } else {
        sbAvatar.textContent = "👤";
      }
    }
  }

  async function loadSidebarProfile(){
    try {
      const res = await apiFetch('/users/me');
      const profile = res.profile || {};
      localStorage.setItem(`kreasiku_profile_${uid}`, JSON.stringify(profile));
      window.dispatchEvent(new CustomEvent('profileUpdated', { detail:{ uid } }));
      renderSidebar(profile);
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + "login.html";
        return;
      }
      const profile = JSON.parse(localStorage.getItem(`kreasiku_profile_${uid}`) || "null");
      renderSidebar(profile || {});
    }
  }

  loadSidebarProfile();
  window.addEventListener("storage", (e) => {
    if (e.key && (e.key === `kreasiku_profile_${uid}` || e.key === "authUser")) {
      const profile = JSON.parse(localStorage.getItem(`kreasiku_profile_${uid}`) || "null");
      renderSidebar(profile || {});
    }
  });

  async function ensureSession(){
    try {
      const me = await apiFetch('/auth/me.php');
      if (me?.user) {
        auth = me.user;
        localStorage.setItem('authUser', JSON.stringify(auth));
        loadSidebarProfile();
        return auth;
      }
    } catch (err) {
      console.warn('Cek sesi akun gagal', err);
    }

    const stored = getStoredAuth();
    if (!stored?.email) {
      window.location.href = base + "login.html";
      return null;
    }

    try {
      const resp = await apiFetch('/auth/login.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          email: stored.email,
          name: stored.name || stored.email.split("@")[0] || "Pengguna"
        })
      });
      auth = resp.user;
      localStorage.setItem('authUser', JSON.stringify(auth));
      loadSidebarProfile();
      return auth;
    } catch (err) {
      alert('Sesi login kedaluwarsa. Silakan login ulang.');
      window.location.href = base + "login.html";
      return null;
    }
  }

  function normalizeMedia(src) {
    const apiBase = (window.API_BASE || '/kreasiku/api').replace(/\/$/, '');
    const rootBase = apiBase.replace(/\/api$/, '');
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith('/')) return src;
    let clean = src.replace(/^\.?\//,'').replace(/\\/g,'/');
    if (clean.startsWith('storage/')) return rootBase + '/' + clean;
    if (clean.startsWith('uploads/')) return rootBase + '/storage/' + clean;
    return rootBase + '/storage/' + clean;
  }

  function card(it){
    const imgSrc = normalizeMedia(it.images?.[0] || it.media || it.mediaUrl || it.media_path);
    const media = it.kind === 'figma' && it.figmaUrl
      ? `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figmaUrl)}" loading="lazy"></iframe>`
      : `<img src="${imgSrc}" alt="${it.title||'Karya'}">`;
    const url = `${base}pages/EditDesign.html?id=${encodeURIComponent(it.id)}`;
    return `
      <a class="card" href="${url}" title="Edit karya">
        ${media}
        <div class="title-overlay"><span>${it.title || it.category || 'Tanpa Judul'}</span></div>
      </a>`;
  }

  async function loadGallery(){
    if (!galleryEl) return;
    galleryEl.innerHTML = '<div class="muted">Memuat...</div>';
    const user = await ensureSession();
    if (!user) return;
    loadSidebarProfile();
    try {
      const res = await apiFetch('/me/designs');
      const items = Array.isArray(res.data) ? res.data : [];
      if (!items.length){
        galleryEl.innerHTML = `<div class="muted">Belum ada karya. <a href="${base}pages/Upload.html" data-requires-auth="true" data-auth-message="Masuk terlebih dahulu untuk mengunggah karya.">Upload sekarang</a>.</div>`;
        return;
      }
      galleryEl.innerHTML = items.map(card).join("");
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + "login.html";
        return;
      }
      console.error('Gagal memuat galeri saya', err);
      galleryEl.innerHTML = `<div class="muted">Tidak bisa memuat karya: ${err.message || err}</div>`;
    }
  }

  loadGallery();
})();
