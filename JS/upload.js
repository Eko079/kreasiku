(function () {
  // Ensure apiFetch from js/api.js is available; provide a safe fallback if not.
  if (typeof window.apiFetch !== 'function') {
    console.warn('api.js not loaded; using fallback apiFetch');
    window.API_BASE = window.API_BASE || '/kreasiku/api';
    window.apiFetch = async (endpoint, options = {}) => {
      const resp = await fetch(window.API_BASE + endpoint, {
        credentials: 'include',
        ...options
      });
      const ct = resp.headers.get('content-type') || '';
      const data = ct.includes('application/json') ? await resp.json() : null;
      if (!resp.ok || !data?.ok) throw new Error((data && data.error) || ('HTTP_' + resp.status));
      return data;
    };
  }

  const $ = (id) => document.getElementById(id);

  // Core elements
  const dz         = $('dropzone');
  const fileInput  = $('fileInput');
  const linkRow    = $('linkRow');
  const linkInput  = $('linkInput');
  const clearLink  = $('clearLink');
  const catSel     = $('categorySelect');
  const descInput  = $('descInput');
  const thumbs     = $('thumbs');
  const btnUpload  = $('btnUpload');
  const btnCancel  = $('btnCancel');
  const form       = $('uploadForm');

  // Settings (optional controls)
  const commentsOn    = $('commentsOn');
  const visibilitySel = $('visibility');
  const publishAtIn   = $('publishAt');      // optional (ignored by backend if not supported)
  const dlRow         = $('dlRow');          // row wrapper for "allow download"
  const allowDl       = $('allowDownload');  // checkbox

  const MAX_IMG = 3;
  const MAX_MB  = 50;

  let images    = []; // data URLs for previews (0..3)
  let figmaEmbed = '';
  let figmaRaw   = '';

  const nonEmpty = (s) => s && String(s).trim().length > 0;

  function toFigmaEmbed(url) {
    try {
      const u = new URL(url);
      if (!u.hostname.includes('figma.com')) return '';
      return `https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(url)}`;
    } catch {
      return '';
    }
  }

  function readAsDataURL(file) {
    return new Promise((res, rej) => {
      const fr = new FileReader();
      fr.onload = () => res(fr.result);
      fr.onerror = rej;
      fr.readAsDataURL(file);
    });
  }

  function dataURLtoFile(dataURL, filename = 'image.png') {
    const [head, body] = dataURL.split(',');
    const mime = (/data:(.*?);base64/).exec(head)?.[1] || 'image/png';
    const bin  = atob(body);
    const u8   = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    return new File([u8], filename, { type: mime });
  }

  function renderThumbs() {
    if (!thumbs) return;
    thumbs.innerHTML = images.map((src, i) => `
      <div class="thumb">
        <img src="${src}" alt="preview-${i + 1}">
        <button type="button" class="thumb-del" data-idx="${i}" aria-label="hapus">×</button>
      </div>
    `).join('');
    thumbs.querySelectorAll('.thumb-del').forEach(btn => {
      btn.onclick = () => {
        images.splice(+btn.dataset.idx, 1);
        updateUI();
      };
    });
  }

  function updateUI() {
    // Mutual exclusivity: if images exist, hide link row.
    if (images.length > 0) {
      if (linkRow) linkRow.classList.add('hidden');
      if (linkInput) linkInput.value = '';
      figmaEmbed = '';
      figmaRaw   = '';
      if (clearLink) clearLink.hidden = true;
    } else {
      if (linkRow) linkRow.classList.remove('hidden');
    }

    // Allow-download is only relevant when uploading images
    if (dlRow) dlRow.style.display = images.length > 0 ? 'flex' : 'none';
    if (!images.length && allowDl) allowDl.checked = false;

    // Hide dropzone if Figma link present or max images reached
    const reachedMax = images.length >= MAX_IMG;
    if (dz) {
      if (nonEmpty(figmaEmbed) || reachedMax) dz.classList.add('hidden');
      else dz.classList.remove('hidden');
    }

    if (btnUpload) btnUpload.disabled = !(images.length > 0 || nonEmpty(figmaEmbed));
    renderThumbs();
  }

  // Drag & Drop
  if (dz) {
    ['dragenter', 'dragover'].forEach(evt => {
      dz.addEventListener(evt, e => { e.preventDefault(); dz.classList.add('dragover'); });
    });
    ['dragleave', 'drop'].forEach(evt => {
      dz.addEventListener(evt, e => { e.preventDefault(); dz.classList.remove('dragover'); });
    });
    dz.addEventListener('drop', async e => {
      const files = [...(e.dataTransfer?.files || [])].filter(f => f.type.startsWith('image/'));
      for (const f of files) {
        if (images.length >= MAX_IMG) break;
        if (f.size > MAX_MB * 1024 * 1024) { alert('Ukuran file melebihi 50MB'); continue; }
        images.push(await readAsDataURL(f));
      }
      updateUI();
    });
  }

  // File picker
  if (fileInput) {
    fileInput.addEventListener('change', async () => {
      const files = [...fileInput.files].filter(f => f.type.startsWith('image/'));
      for (const f of files) {
        if (images.length >= MAX_IMG) break;
        if (f.size > MAX_MB * 1024 * 1024) { alert('Ukuran file melebihi 50MB'); continue; }
        images.push(await readAsDataURL(f));
      }
      fileInput.value = '';
      updateUI();
    });
  }

  // Figma link
  if (linkInput) {
    linkInput.addEventListener('input', () => {
      const raw = linkInput.value.trim();
      const emb = toFigmaEmbed(raw);
      figmaEmbed = emb;
      figmaRaw   = emb ? raw : '';
      if (clearLink) clearLink.hidden = !nonEmpty(emb);
      updateUI();
    });
  }
  if (clearLink) {
    clearLink.addEventListener('click', () => {
      if (linkInput) linkInput.value = '';
      figmaEmbed = '';
      figmaRaw   = '';
      clearLink.hidden = true;
      updateUI();
    });
  }

  // Cancel
  if (btnCancel) {
    btnCancel.addEventListener('click', () => {
      images = [];
      figmaEmbed = '';
      figmaRaw   = '';
      if (form) form.reset();
      updateUI();
    });
  }

  // Submit
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (btnUpload?.disabled) return;

      const cat = (catSel?.value || '').toLowerCase();
      if (!cat) { alert('Silakan pilih kategori.'); return; }

      const fd = new FormData();
      fd.append('category', cat);

      const desc = (descInput?.value || '').trim();
      fd.append('title', desc);
      fd.append('description', desc);

      // Settings (optional; backend akan abaikan field yang tidak dipakai)
      fd.append('visibility', (visibilitySel?.value || 'public'));
      fd.append('allow_comments', (commentsOn && commentsOn.checked) ? 1 : 0);
      fd.append('allow_download', (allowDl && allowDl.checked) ? 1 : 0);
      if (publishAtIn && publishAtIn.value) fd.append('publish_at', publishAtIn.value);

      if (nonEmpty(figmaEmbed)) {
        // Kirim RAW link ke backend (bukan embed)
        fd.append('figma_url', figmaRaw);
      } else if (images.length > 0) {
        // Backend saat ini menerima 1 gambar → kirim gambar pertama
        const file = dataURLtoFile(images[0], 'image.png');
        fd.append('image', file);
      } else {
        alert('Masukkan gambar atau link Figma.');
        return;
      }

      try {
        await apiFetch('/designs/create.php', { method: 'POST', body: fd });
        // Upload sukses → kembali ke halaman Akun (berkas ini berada di /pages/)
        window.location.href = 'Account.html';
      } catch (err) {
        alert('Upload gagal: ' + (err.message || err));
      }
    });
  }

  updateUI();
})();
