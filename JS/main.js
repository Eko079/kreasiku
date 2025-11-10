// js/main.js
window.API_BASE = window.API_BASE || '/kreasiku/api';

(() => {
  const PREFIX =
    window.__PATH_PREFIX__ ||
    (location.pathname.includes('/pages/') ? '../' : '');

  document.addEventListener('DOMContentLoaded', () => {
    // Inject dengan CSS masing-masing
    inject('header', 'header.html', PREFIX + 'css/header.css', onHeaderReady);
    inject('footer', 'footer.html', PREFIX + 'css/footer.css');
  });

  async function inject(targetId, partial, cssFile, after) {
    const host = document.getElementById(targetId);
    if (!host) return;
    try {
      const r = await fetch(PREFIX + partial, { credentials: 'same-origin' });
      if (!r.ok) return;
      host.innerHTML = await r.text();           // penting: innerHTML
      fixRelUrls(host);                          // betulkan link/src relatif

      // Load CSS if provided and not already loaded
      if (cssFile && !document.querySelector(`link[href="${cssFile}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = cssFile;
        document.head.appendChild(link);
      }

      // Handle callback (if it's a function)
      if (typeof after === 'function') after(host);
    } catch (_) {}
  }

// --- ganti function fixRelUrls jadi:
function fixRelUrls(root) {
  const isRel = (u) => u && !/^(?:[a-z]+:|\/|#)/i.test(u); // bukan http:, https:, mailto:, /, #
  root.querySelectorAll('a[href]').forEach(a => {
    const href = a.getAttribute('href');
    if (isRel(href)) a.setAttribute('href', PREFIX + href);
  });
  root.querySelectorAll('img[src]').forEach(img => {
    const src = img.getAttribute('src');
    if (isRel(src)) img.setAttribute('src', PREFIX + src);
  });
  // jaga-jaga kalau suatu saat partial berisi resource lain
  root.querySelectorAll('link[rel="stylesheet"][href]').forEach(l => {
    const href = l.getAttribute('href');
    if (isRel(href)) l.setAttribute('href', PREFIX + href);
  });
  root.querySelectorAll('script[src]').forEach(s => {
    const src = s.getAttribute('src');
    if (isRel(src)) s.setAttribute('src', PREFIX + src);
  });
}


  function onHeaderReady(headerRoot) {
    // swap tombol "Masuk" -> avatar kalau sudah login (tanpa ngubah markup lain)
    const masuk = headerRoot.querySelector('.header-buttons .btn-link');
    const mulai = headerRoot.querySelector('.header-buttons .btn-start');
    const auth  = safeJSON(localStorage.getItem('authUser'));

    if (auth && masuk) {
      const avatar = document.createElement('a');
      avatar.href = PREFIX + 'pages/account/Account.html';
      avatar.className = 'nav-avatar';
      avatar.setAttribute('aria-label', 'Akun');
      masuk.replaceWith(avatar);
      if (mulai) mulai.style.display = 'none';
      renderAvatar(avatar);
      window.addEventListener('storage', (e) => {
        if (e.key && e.key.startsWith('kreasiku_profile_')) renderAvatar(avatar);
      });
    }

    // fallback mini supaya nav tidak ber-bullet kalau CSS global telat
    if (!document.getElementById('hdr-fallback')) {
      const style = document.createElement('style');
      style.id = 'hdr-fallback';
      style.textContent = `
        header .primary-nav ul{list-style:none;margin:0;padding:0;display:flex;gap:.9rem}
        header .primary-nav a{text-decoration:none}
        .nav-avatar{display:inline-grid;place-items:center;width:36px;height:36px;border-radius:999px;background:#EDEBFF;overflow:hidden}
        .nav-avatar img{width:100%;height:100%;object-fit:cover;display:block}
      `;
      document.head.appendChild(style);
    }

    // --- di akhir onHeaderReady(), tambahkan re-measure setelah CSS kemungkinan selesai diload:
    const h = headerRoot.offsetHeight || 72;
    document.documentElement.style.setProperty('--header-h', h + 'px');
    addEventListener('resize', () => {
      const hh = headerRoot.offsetHeight || 72;
      document.documentElement.style.setProperty('--header-h', hh + 'px');
    });
    // re-measure lagi setelah frame berikut & sedikit delay (CSS load)
    requestAnimationFrame(() => {
      setTimeout(() => {
        const hh2 = headerRoot.offsetHeight || 72;
        document.documentElement.style.setProperty('--header-h', hh2 + 'px');
      }, 250);
    });
  }

  function renderAvatar(anchor) {
    const auth = safeJSON(localStorage.getItem('authUser'));
    if (!auth) { anchor.textContent = '👤'; return; }
    const uid  = auth.uid || auth.email || auth.name || 'anonymous';
    const prof = safeJSON(localStorage.getItem('kreasiku_profile_' + uid)) || {};
    anchor.innerHTML = prof.photo ? `<img src="${prof.photo}" alt="Foto profil">` : '👤';
  }

  function safeJSON(s){ try { return JSON.parse(s || 'null'); } catch { return null; } }
})();
