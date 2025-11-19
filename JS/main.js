// js/main.js
(function ensureApiBase() {
  if (window.API_BASE) return;
  const detect = () => {
    const script =
      document.currentScript ||
      document.querySelector('script[src*="main.js"]');
    if (!script) return '/api';
    try {
      const url = new URL(script.getAttribute('src'), location.href);
      const base = url.pathname
        .replace(/\/js\/main\.js.*$/i, '')
        .replace(/\/+$/, '');
      return (base || '') + '/api';
    } catch {
      return '/api';
    }
  };
  window.API_BASE = detect();
})();

(() => {
  const PREFIX =
    window.__PATH_PREFIX__ ||
    (location.pathname.includes('/pages/') ? '../' : '');
  const LOGIN_URL = PREFIX + 'login.html';

  document.addEventListener('DOMContentLoaded', () => {
    initNotifier();
    setupAuthGuard();
    handleGreetings();
    setupFancySelects();
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
    const auth  = getAuthUser();

    if (auth && masuk) {
      const avatar = document.createElement('a');
      avatar.href = PREFIX + 'pages/account/Account.html';
      avatar.className = 'nav-avatar';
      avatar.setAttribute('aria-label', 'Akun');
      masuk.replaceWith(avatar);
      renderAvatar(avatar);
      refreshProfileCache().then(() => renderAvatar(avatar));
      window.addEventListener('storage', (e) => {
        if (e.key && e.key.startsWith('kreasiku_profile_')) renderAvatar(avatar);
      });
      window.addEventListener('profileUpdated', () => renderAvatar(avatar));
    }

    setupAuthLinks();

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
    const photo = prof.photo || auth.avatar || auth.avatar_url;
    anchor.innerHTML = photo ? `<img src="${photo}" alt="Foto profil">` : '👤';
  }

  async function refreshProfileCache() {
    const auth = safeJSON(localStorage.getItem('authUser'));
    if (!auth) return;
    try {
      const res = await fetch((window.API_BASE || '/kreasiku/api') + '/users/me', {
        credentials: 'include'
      });
      if (!res.ok) return;
      const json = await res.json().catch(()=>null);
      if (json?.ok && json.profile) {
        const uid = auth.uid || auth.email || auth.name || 'anonymous';
        localStorage.setItem('kreasiku_profile_' + uid, JSON.stringify(json.profile));
      }
    } catch {}
  }

  function setupAuthLinks() {
    const auth = safeJSON(localStorage.getItem('authUser'));
    document.querySelectorAll('.auth-link[data-auth-target]').forEach(link => {
      const loginHref = link.getAttribute('data-login-href') || link.dataset.loginHref || 'login.html';
      const authTarget = link.getAttribute('data-auth-target') || link.dataset.authTarget;
      const hideWhenAuth = link.dataset.hideWhenAuth === 'true';

      if (auth && authTarget) {
        link.href = PREFIX + authTarget;
        link.classList.add('btn-link-authed');
        if (hideWhenAuth) {
          link.style.display = 'none';
        } else {
          link.style.display = '';
          const handler = (e) => {
            e.preventDefault();
            location.href = PREFIX + authTarget;
          };
          link.addEventListener('click', handler, { once: true });
        }
      } else {
        link.href = PREFIX + loginHref;
        link.classList.remove('btn-link-authed');
        link.style.display = '';
      }
    });
  }

  function safeJSON(s){ try { return JSON.parse(s || 'null'); } catch { return null; } }

  function getAuthUser() {
    return safeJSON(localStorage.getItem('authUser'));
  }

  window.getAuthUser = getAuthUser;
  window.isUserLoggedIn = () => !!getAuthUser();

  function initNotifier() {
    if (window.__notifyReady) return;
    const overlay = document.createElement('div');
    overlay.className = 'notify-overlay';
    overlay.innerHTML = `
      <div class="notify-card info" data-label="INFO">
        <button type="button" class="notify-close" aria-label="Tutup">&times;</button>
        <h3>Info</h3>
        <p>Pesan notifikasi.</p>
        <div class="notify-input" hidden>
          <label for="notifyInput">Konfirmasi</label>
          <input id="notifyInput" type="text" autocomplete="off">
          <small class="notify-error"></small>
        </div>
        <div class="notify-actions">
          <button type="button" class="notify-btn ghost notify-cancel">Batal</button>
          <button type="button" class="notify-btn primary">Mengerti</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    const card = overlay.querySelector('.notify-card');
    const titleEl = card.querySelector('h3');
    const msgEl = card.querySelector('p');
    const primaryBtn = card.querySelector('.notify-btn.primary');
    const closeBtn = card.querySelector('.notify-close');
    const cancelBtn = card.querySelector('.notify-cancel');
    const inputWrap = card.querySelector('.notify-input');
    const inputLabel = inputWrap.querySelector('label');
    const inputField = inputWrap.querySelector('input');
    const inputError = inputWrap.querySelector('.notify-error');
    const labels = { info: 'INFO', success: 'BERHASIL', warning: 'PERHATIAN' };
    let actionHandler = null;
    let cancelHandler = null;
    let pendingResolver = null;
    let currentOptions = null;
    let delayTimer = null;
    let originalActionText = '';

    const resetInput = () => {
      inputWrap.hidden = true;
      inputWrap.style.display = 'none';
      inputWrap.classList.remove('error');
      inputLabel.textContent = '';
      inputField.value = '';
      inputField.placeholder = '';
      inputError.textContent = '';
    };

    const clearDelay = () => {
      if (delayTimer) {
        clearInterval(delayTimer);
        delayTimer = null;
      }
      primaryBtn.disabled = false;
      if (originalActionText) primaryBtn.textContent = originalActionText;
    };

    const hide = (result = false) => {
      if (!overlay.classList.contains('active')) return;
      overlay.classList.remove('active');
      const resolver = pendingResolver;
      const opts = currentOptions;
      actionHandler = null;
      const cancelCb = cancelHandler;
      cancelHandler = null;
      currentOptions = null;
      pendingResolver = null;
      resetInput();
      clearDelay();
      if (!result && cancelCb) cancelCb();
      if (!result && typeof opts?.onClose === 'function') opts.onClose();
      if (resolver) resolver(result);
    };

    const validateInput = () => {
      if (!currentOptions?.requireText) return true;
      const val = inputField.value.trim();
      let ok = true;
      if (typeof currentOptions.requireText === 'function') {
        ok = currentOptions.requireText(val);
      } else {
        ok = val.toLowerCase() === String(currentOptions.requireText).toLowerCase();
      }
      if (!ok) {
        inputWrap.classList.add('error');
        inputError.textContent = currentOptions.requireError || 'Masukkan teks yang diminta untuk melanjutkan.';
        return false;
      }
      inputWrap.classList.remove('error');
      inputError.textContent = '';
      return true;
    };

    const showDialog = (opts = {}) => {
      const {
        title = 'Info',
        message = '',
        type = 'info',
        actionText,
        cancelText,
        showCancel = false,
        onAction,
        onCancel,
        requireText,
        requireLabel,
        requirePlaceholder,
        requireError,
        delayMs = 0
      } = opts;
      card.classList.remove('info', 'success', 'warning');
      card.classList.add(['info','success','warning'].includes(type) ? type : 'info');
      card.setAttribute('data-label', labels[type] || labels.info);
      titleEl.textContent = title;
      msgEl.textContent = message;

      originalActionText = actionText || 'Mengerti';
      primaryBtn.textContent = originalActionText;
      primaryBtn.disabled = false;
      actionHandler = typeof onAction === 'function' ? onAction : null;

      cancelHandler = typeof onCancel === 'function' ? onCancel : null;
      const shouldShowCancel = showCancel || !!cancelText || !!requireText;
      if (shouldShowCancel) {
        cancelBtn.style.display = 'inline-flex';
        cancelBtn.textContent = cancelText || 'Batal';
      } else {
        cancelBtn.style.display = 'none';
      }

      if (requireText) {
        inputWrap.hidden = false;
        inputWrap.style.display = 'flex';
        inputWrap.classList.remove('error');
        inputLabel.textContent = requireLabel || 'Tulis konfirmasi';
        inputField.placeholder = requirePlaceholder || '';
        inputField.value = '';
        inputError.textContent = '';
      } else {
        resetInput();
      }

      currentOptions = {
        requireText,
        requireError,
        onClose: opts.onClose || null
      };
      clearDelay();
      if (delayMs > 0) {
        primaryBtn.disabled = true;
        const end = Date.now() + delayMs;
        const update = () => {
          const remaining = end - Date.now();
          if (remaining <= 0) {
            clearDelay();
            return;
          }
          const secs = Math.ceil(remaining / 1000);
          primaryBtn.textContent = `${originalActionText} (${secs}s)`;
        };
        update();
        delayTimer = setInterval(update, 250);
      }
      overlay.classList.add('active');
      setTimeout(() => {
        if (!inputWrap.hidden) {
          inputField.focus();
        }
      }, 30);
      return new Promise(resolve => {
        pendingResolver = resolve;
      });
    };

    primaryBtn.addEventListener('click', () => {
      if (!validateInput()) return;
      if (actionHandler) actionHandler();
      hide(true);
    });
    cancelBtn.addEventListener('click', () => {
      if (cancelHandler) cancelHandler();
      hide(false);
    });
    closeBtn.addEventListener('click', () => hide(false));
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) hide(false);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('active')) hide(false);
    });

    function promptLogin(message) {
      showDialog({
        title: 'Butuh login',
        message: message || 'Silakan login untuk melanjutkan.',
        type: 'warning',
        actionText: 'Login',
        cancelText: 'Nanti saja',
        showCancel: true,
        onAction: () => { location.href = LOGIN_URL; }
      });
    }

    window.notifyUser = (opts = {}) => { showDialog(opts); };
    window.closeNotify = hide;
    window.promptLogin = promptLogin;
    window.confirmAction = (opts = {}) => showDialog({
      showCancel: true,
      cancelText: opts.cancelText || 'Batal',
      actionText: opts.actionText || 'Lanjutkan',
      ...opts
    });
    window.__notifyReady = true;
  }

  function setupAuthGuard() {
    if (window.__authGuardReady) return;
    document.addEventListener('click', (event) => {
      const target = event.target.closest('[data-requires-auth]');
      if (!target) return;
      if (window.isUserLoggedIn && window.isUserLoggedIn()) return;
      event.preventDefault();
      event.stopPropagation();
      const msg = target.dataset.authMessage || 'Silakan login untuk melanjutkan aksi ini.';
      if (window.promptLogin) {
        window.promptLogin(msg);
      } else {
        alert(msg);
        location.href = LOGIN_URL;
      }
    }, true);
    window.__authGuardReady = true;
  }

  function handleGreetings() {
    const show = (payload, delay = 200) => {
      if (typeof window.notifyUser !== 'function') return;
      setTimeout(() => window.notifyUser(payload), delay);
    };
    const auth = getAuthUser();
    if (auth) {
      const uid = auth.uid || auth.email || auth.name || 'anonymous';
      const sessionKey = 'kreasiku_welcome_session_' + uid;
      if (sessionStorage.getItem(sessionKey)) return;
      const localKey = 'kreasiku_welcome_seen_' + uid;
      const seenBefore = localStorage.getItem(localKey) === '1';
      const name = auth.name || auth.fullName || (auth.email ? auth.email.split('@')[0] : 'Kreator');
      show({
        title: seenBefore ? 'Selamat datang kembali' : 'Selamat datang',
        message: seenBefore
          ? `Senang kamu kembali berkarya, ${name}!`
          : `Halo ${name}, siap memamerkan karya terbaikmu di Kreasiku?`,
        type: 'success',
        actionText: 'Buka galeri',
        cancelText: 'Nanti saja',
        showCancel: true,
        onAction: () => { location.href = PREFIX + 'pages/gallery/Desain.html'; }
      }, 600);
      sessionStorage.setItem(sessionKey, '1');
      if (!seenBefore) localStorage.setItem(localKey, '1');
      return;
    }
    const cookieFlag = document.cookie.includes('kreasiku_generic_welcome=1');
    if (cookieFlag) return;
    const expires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString();
    document.cookie = `kreasiku_generic_welcome=1; path=/; expires=${expires}`;
    show({
      title: 'Selamat datang di Kreasiku',
      message: 'Jelajahi karya siswa DKV, temukan inspirasi, dan unggah portofoliomu sendiri.',
      type: 'info',
      actionText: 'Lihat galeri',
      cancelText: 'Nanti saja',
      showCancel: true,
      onAction: () => { location.href = PREFIX + 'pages/gallery/Desain.html'; }
    }, 700);
  }

  function setupFancySelects() {
    const instances = new Set();

    const closeAll = (except) => {
      instances.forEach(inst => {
        if (inst !== except) inst.close();
      });
    };

    const initSelect = (select) => {
      const wrap = select.closest('.select-wrap');
      if (!wrap || wrap.dataset.fancyReady === '1') return;

      const display = document.createElement('button');
      display.type = 'button';
      display.className = 'custom-select-display';

      const panel = document.createElement('div');
      panel.className = 'custom-select-panel';

      wrap.appendChild(display);
      wrap.appendChild(panel);
      wrap.dataset.fancyReady = '1';

      const inst = {
        wrap,
        display,
        panel,
        select,
        close: () => {
          panel.classList.remove('open');
          display.classList.remove('open');
        }
      };
      instances.add(inst);

      const buildOptions = () => {
        panel.innerHTML = '';
        const opts = Array.from(select.options);
        opts.forEach(opt => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'custom-select-option';
          btn.textContent = opt.textContent;
          if (opt.disabled) {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
          }
          if (opt.selected) {
            btn.setAttribute('aria-selected', 'true');
            display.textContent = opt.textContent;
          }
          btn.addEventListener('click', () => {
            if (opt.disabled) return;
            select.value = opt.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            closeAll();
            buildOptions();
          });
          panel.appendChild(btn);
        });
        if (!select.selectedOptions.length && opts.length) {
          display.textContent = opts[0].textContent;
        }
      };

      buildOptions();

      display.addEventListener('click', () => {
        const open = panel.classList.contains('open');
        closeAll();
        if (!open) {
          panel.classList.add('open');
          display.classList.add('open');
        }
      });

      document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) inst.close();
      });

      select.addEventListener('change', buildOptions);
    };

    const refresh = () => {
      document.querySelectorAll('.select-wrap > select').forEach(initSelect);
    };
    refresh();
    window.refreshFancySelects = refresh;
  }
})();
