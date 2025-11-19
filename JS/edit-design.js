(function () {
  const $ = s => document.querySelector(s);
  const API_BASE = window.API_BASE || '/kreasiku/api';
  const mediaBox   = $('#media');
  const edAvatar   = $('#edAvatar');
  const edNameEl   = $('#edName');
  const edClassEl  = $('#edClass');
  const edDesc     = $('#edDesc');
  const commentsOn = $('#commentsOn');
  const visibility = $('#visibility');
  const publishAt  = $('#publishAt');
  const allowDl    = $('#allowDownload');
  const catSel     = $('#categorySelect');

  const btnSave    = $('#btnSave');
  const btnDelete  = $('#btnDelete');
  const BASE_PATH  =
    window.__PATH_PREFIX__ ||
    (location.pathname.includes('/pages/') ? '../' : './');

  const params = new URLSearchParams(location.search);
  const id = params.get('id');

  if (!id) {
    mediaBox.innerHTML = `<div class="muted">Karya tidak ditemukan.</div>`;
    btnSave.disabled = true;
    btnDelete.disabled = true;
    return;
  }

  function renderMedia(d) {
    if (!mediaBox) return;
    if (d.kind === 'figma' && d.figmaUrl) {
      mediaBox.innerHTML = `
        <div class="figma-embed tile primary iframe-only">
          <iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(d.figmaUrl)}" loading="lazy"></iframe>
        </div>`;
      // allow download hanya relevan image
      document.getElementById('dlRow').style.display = 'none';
      return;
    }

    const images = d.images && d.images.length ? d.images : (d.media ? [d.media] : []);
    if (!images.length) {
      mediaBox.innerHTML = `<div class="muted">Tidak ada media</div>`;
      document.getElementById('dlRow').style.display = 'none';
      return;
    }

    const html = images.map((src, idx) => `
      <div class="tile ${idx === 0 ? 'primary' : 'secondary'}">
        <img src="${src}" alt="${d.title || 'karya'}">
      </div>`).join('');
    mediaBox.innerHTML = html;
    document.getElementById('dlRow').style.display = '';
  }

  function fmtDate(iso) {
    try {
      return new Date(iso).toLocaleString('id-ID', {
        day:'2-digit', month:'long', year:'numeric',
        hour:'2-digit', minute:'2-digit'
      });
    } catch {
      return '';
    }
  }

  function normalizeDetail(res) {
    const raw = res?.data || res?.design || res;
    if (!raw) throw new Error('INVALID_RESPONSE');

    const mediaCandidates = [];
    if (Array.isArray(raw.images)) mediaCandidates.push(...raw.images);
    if (raw.media && /^https?:\/\//.test(raw.media)) mediaCandidates.push(raw.media);
    if (raw.media_url && /^https?:\/\//.test(raw.media_url)) mediaCandidates.push(raw.media_url);
    if (raw.mediaPath) mediaCandidates.push(raw.mediaPath);
    if (raw.media_path) mediaCandidates.push(raw.media_path);

    const base = API_BASE.replace(/\/$/,'');
    const normalizedImages = mediaCandidates.filter(Boolean).map(src => {
      if (/^https?:\/\//.test(src)) return src;
      if (src.startsWith('/')) return src;
      let clean = src.replace(/^\.?\//,'').replace(/\\/g,'/');
      if (clean.startsWith('storage/')) return `${base}/${clean}`;
      if (clean.startsWith('uploads/')) return `${base}/storage/${clean}`;
      return `${base}/${clean}`;
    });

    const scheduled = raw.scheduledAt || raw.scheduled_at || raw.publish_at || null;

    return {
      id: raw.id,
      kind: raw.kind || raw.type,
      title: raw.title,
      desc: raw.desc || raw.description || '',
      visibility: raw.visibility || 'public',
      allowDownload: !!(raw.allowDownload ?? raw.allow_download),
      allowComments: !!(raw.commentEnabled ?? raw.allow_comments ?? raw.comment_enabled),
      category: raw.category || '',
      figmaUrl: raw.figmaUrl || raw.figma_url || '',
      images: normalizedImages,
      media: normalizedImages[0] || '',
      scheduledAt: scheduled,
      downloadUrl: raw.downloadUrl || (raw.allow_download ? `${API_BASE}/designs/${encodeURIComponent(raw.id)}/download` : ''),
      ownerName: raw.ownerName || raw.owner_name || raw.owner?.name || '',
      ownerAvatar: raw.ownerAvatar || raw.owner_avatar || raw.owner?.avatar || null,
      ownerClass: raw.ownerClass || raw.owner_kelas || raw.owner?.kelas || '',
      createdAt: raw.createdAt || raw.created_at || raw.publishedAt || raw.published_at || null,
    };
  }

  async function fetchDesignDetail() {
    try {
      const modern = await apiFetch('/designs/' + encodeURIComponent(id));
      return normalizeDetail(modern);
    } catch (err) {
      console.warn('Modern design detail gagal, fallback legacy:', err.message);
      const legacy = await apiFetch('/designs/detail.php?id=' + encodeURIComponent(id));
      return normalizeDetail(legacy);
    }
  }

  function toDatetimeLocalValue(value) {
    if (!value) return '';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) {
      // assume already "YYYY-mm-dd HH:ii:ss"
      return value.replace(' ', 'T').slice(0, 16);
    }
    const pad = n => String(n).padStart(2,'0');
    return `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
  }

  function fromDatetimeLocal(inputValue) {
    if (!inputValue) return null;
    if (inputValue.includes('T')) {
      const [date, time] = inputValue.split('T');
      return `${date} ${time}:00`.slice(0,19);
    }
    return inputValue;
  }

  async function loadDetail() {
    const d = await fetchDesignDetail();
    renderMedia(d);
    renderOwner(d);

    edDesc.value = d.desc || '';
    commentsOn.checked = d.allowComments;
    visibility.value  = d.visibility || 'public';
    allowDl.checked   = d.allowDownload;
    publishAt.value   = toDatetimeLocalValue(d.scheduledAt);
    if (d.category) catSel.value = d.category;
  }

  async function saveChanges() {
    const desc = edDesc.value.trim();
    const category = catSel.value || '';
    const sched = fromDatetimeLocal(publishAt.value);

    const modernPayload = {
      description: desc,
      comment_enabled: commentsOn.checked,
      allow_download: allowDl.checked,
      visibility: visibility.value,
      category,
      scheduled_at: sched
    };
    try {
      await apiFetch('/designs/' + encodeURIComponent(id), {
        method:'PATCH',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(modernPayload)
      });
    } catch (err) {
      console.warn('Modern update gagal, fallback legacy:', err.message);
      const legacyPayload = {
        id,
        description: desc,
        allow_comments: commentsOn.checked ? 1 : 0,
        allow_download: allowDl.checked ? 1 : 0,
        visibility: visibility.value,
        category,
        publish_at: sched
      };
      await apiFetch('/designs/update.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(legacyPayload)
      });
    }
  }

  const goToAccount = () => {
    window.location.href = `${BASE_PATH}pages/account/Account.html`;
  };

  const showPopup = (opts) => {
    if (window.notifyUser) {
      window.notifyUser(opts);
    } else {
      alert(opts.message || '');
      if (opts.onAction) {
        opts.onAction();
      } else if (opts.onClose) {
        opts.onClose();
      }
    }
  };

  async function confirmDesignDelete() {
    if (window.confirmAction) {
      return await window.confirmAction({
        title: 'Hapus karya?',
        message: 'Tindakan ini permanen. Ketik HAPUS untuk melanjutkan.',
        type: 'warning',
        actionText: 'Hapus karya',
        cancelText: 'Batal',
        requireText: 'HAPUS',
        requireLabel: 'Tuliskan HAPUS untuk konfirmasi',
        requirePlaceholder: 'HAPUS',
        requireError: 'Masukkan HAPUS sesuai instruksi.'
      });
    }
    return confirm('Hapus karya ini? Tindakan ini tidak bisa dibatalkan.');
  }

  async function deleteDesign() {
    const agreed = await confirmDesignDelete();
    if (!agreed) return false;
    try {
      await apiFetch('/designs/' + encodeURIComponent(id), { method:'DELETE' });
    } catch (err) {
      console.warn('Modern delete gagal, fallback legacy:', err.message);
      await apiFetch('/designs/delete.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id })
      });
    }
    showPopup({
      title: 'Karya dihapus',
      message: 'Karya berhasil dihapus dari portofoliomu.',
      type: 'success',
      actionText: 'Kembali ke Akun',
      onAction: goToAccount,
      onClose: goToAccount
    });
    return true;
  }

  btnSave?.addEventListener('click', async () => {
    try {
      btnSave.disabled = true;
      await saveChanges();
      showPopup({
        title: 'Perubahan disimpan',
        message: 'Karya kamu berhasil diperbarui.',
        type: 'success'
      });
    } catch (e) {
      showPopup({
        title: 'Gagal menyimpan',
        message: e.message || 'Coba lagi nanti.',
        type: 'warning'
      });
    } finally {
      btnSave.disabled = false;
    }
  });

  btnDelete?.addEventListener('click', async () => {
    btnDelete.disabled = true;
    try {
      const done = await deleteDesign();
      if (!done) btnDelete.disabled = false;
    } catch (e) {
      btnDelete.disabled = false;
      showPopup({
        title: 'Gagal menghapus',
        message: e.message || 'Coba lagi nanti.',
        type: 'warning'
      });
    }
  });

  function renderOwner(meta) {
    if (!edNameEl || !edClassEl || !edAvatar) return;
    edNameEl.textContent = meta.ownerName || 'Pengguna';
    const sub = meta.ownerClass || fmtDate(meta.createdAt);
    edClassEl.textContent = sub || '—';

    if (meta.ownerAvatar) {
      edAvatar.innerHTML = `<img src="${meta.ownerAvatar}" alt="${meta.ownerName || 'Pengguna'}">`;
    } else {
      edAvatar.textContent = '👤';
    }
  }

  loadDetail().catch(err => {
    console.error('Edit detail error', err);
    mediaBox.innerHTML = `<div class="muted">Gagal memuat: ${err.message}</div>`;
  });
})();
