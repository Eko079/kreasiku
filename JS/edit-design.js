(function () {
  const $ = s => document.querySelector(s);
  const mediaBox   = $('#media');
  const edDesc     = $('#edDesc');
  const commentsOn = $('#commentsOn');
  const visibility = $('#visibility');
  const publishAt  = $('#publishAt');
  const allowDl    = $('#allowDownload');
  const catSel     = $('#categorySelect');

  const btnSave    = $('#btnSave');
  const btnDelete  = $('#btnDelete');

  const params = new URLSearchParams(location.search);
  const id = params.get('id');

  if (!id) {
    mediaBox.innerHTML = `<div class="muted">Karya tidak ditemukan.</div>`;
    btnSave.disabled = true;
    btnDelete.disabled = true;
    return;
  }

  function renderMedia(d) {
    if (d.kind === 'figma' && d.figma_url) {
      mediaBox.innerHTML = `
        <div class="figma-embed">
          <iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(d.figma_url)}" loading="lazy"></iframe>
        </div>`;
      // allow download hanya relevan image
      document.getElementById('dlRow').style.display = 'none';
    } else {
      mediaBox.innerHTML = `
        <div class="image-box">
          <img src="${d.media_url}" alt="${d.title || 'karya'}">
        </div>`;
      document.getElementById('dlRow').style.display = '';
    }
  }

  async function loadDetail() {
    const res = await apiFetch('/designs/detail.php?id=' + encodeURIComponent(id));
    const d = res.design;
    renderMedia(d);

    edDesc.value = d.description || '';
    commentsOn.checked = !!d.allow_comments;
    visibility.value  = d.visibility || 'public';
    allowDl.checked   = !!d.allow_download;
    if (d.publish_at) {
      // d.publish_at format "YYYY-mm-dd HH:ii:ss" -> ke datetime-local
      const t = d.publish_at.replace(' ', 'T').slice(0, 16);
      publishAt.value = t;
    } else {
      publishAt.value = '';
    }
    if (d.category) catSel.value = d.category;
  }

  async function saveChanges() {
    const payload = {
      id,
      description: edDesc.value.trim(),
      allow_comments: commentsOn.checked ? 1 : 0,
      allow_download: allowDl.checked ? 1 : 0,
      visibility: visibility.value,
      category: catSel.value || '',
      publish_at: publishAt.value || null
    };
    await apiFetch('/designs/update.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
  }

  async function deleteDesign() {
    if (!confirm('Hapus karya ini? Tindakan ini tidak bisa dibatalkan.')) return;
    await apiFetch('/designs/delete.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ id })
    });
    window.location.href = '../pages/Account.html';
  }

  btnSave?.addEventListener('click', async () => {
    try {
      btnSave.disabled = true;
      await saveChanges();
      alert('Perubahan disimpan.');
    } catch (e) {
      alert('Gagal menyimpan: ' + e.message);
    } finally {
      btnSave.disabled = false;
    }
  });

  btnDelete?.addEventListener('click', async () => {
    try {
      btnDelete.disabled = true;
      await deleteDesign();
    } catch (e) {
      alert('Gagal menghapus: ' + e.message);
    } finally {
      btnDelete.disabled = false;
    }
  });

  loadDetail().catch(err => {
    mediaBox.innerHTML = `<div class="muted">Gagal memuat: ${err.message}</div>`;
  });
})();
