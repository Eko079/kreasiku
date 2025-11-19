(function () {
  const $ = s => document.querySelector(s);
  const grid  = $('#resultGrid');
  const empty = $('#emptyState');
  const meta  = $('#resultMeta');

  const qInput = $('#q');
  const sortSel= $('#sortSel');
  const catSel = $('#catSel');
  const dlSel  = $('#dlSel');
  const btnSearch = $('#btnSearch');
  const typingInput = qInput;

  const API_BASE = (window.API_BASE || '/kreasiku/api').replace(/\/$/, '');
  const ROOT_BASE = API_BASE.replace(/\/api$/, '');

  const normalizeMedia = (src) => {
    if (!src) return '';
    if (/^https?:\/\//i.test(src)) return src;
    if (src.startsWith('/')) return src;
    const clean = src.replace(/^\.?\//,'').replace(/\\/g,'/');
    if (clean.startsWith('storage/')) return ROOT_BASE + '/' + clean;
    if (clean.startsWith('uploads/')) return ROOT_BASE + '/storage/' + clean;
    return ROOT_BASE + '/storage/' + clean;
  };

  function fmtDate(iso) {
    try {
      return new Date(iso).toLocaleDateString('id-ID',{ day:'2-digit', month:'long', year:'numeric' });
    } catch { return ''; }
  }

  function card(it) {
    const isFigma = (it.kind === 'figma' && it.figma_url);
    let media;
    if (isFigma) {
      media = `<iframe src="https://www.figma.com/embed?embed_host=kreasiku&url=${encodeURIComponent(it.figma_url)}" loading="lazy"></iframe>`;
    } else {
      const firstImage = Array.isArray(it.images) && it.images.length ? it.images[0] : (it.media_url || it.media_path || '');
      const img = normalizeMedia(firstImage);
      media = img
        ? `<img src="${img}" alt="${it.title || 'Karya'}">`
        : `<div class="card-empty">Tidak ada gambar</div>`;
    }
    const url = "../pages/Detail.html?id=" + encodeURIComponent(it.id);
    const badgeLabel = isFigma ? 'Figma' : ((it.category || 'IMG') + '').toUpperCase();
    const catLabel = ((it.category || 'Umum') + '').toUpperCase();
    const dateLabel = fmtDate(it.created_at);
    return `
      <div class="card-item card-item--featured">
        <a class="card" href="${url}">
          <div class="card-media">
            ${media}
            <span class="card-badge">${badgeLabel}</span>
            <div class="card-overlay"><span>${it.title || it.category || 'Karya'}</span></div>
          </div>
        </a>
        <div class="card-date"><span>${catLabel}</span><span>${dateLabel || ''}</span></div>
      </div>`;
  }

  async function runSearch() {
    const params = new URLSearchParams();
    const qVal = qInput?.value.trim();
    if (qVal) params.set('q', qVal);
    if (catSel?.value) params.set('category', catSel.value);
    if (sortSel?.value) params.set('sort', sortSel.value);
    if (dlSel?.value) params.set('download', dlSel.value);

    const query = params.toString();
    const res = await apiFetch('/search.php' + (query ? ('?' + query) : ''));
    let items = Array.isArray(res.items) ? res.items : [];
    const currentSort = sortSel?.value || 'relevant';
    if (currentSort === 'likes') {
      items = [...items].sort((a,b) => (b.likes ?? 0) - (a.likes ?? 0));
    } else if (currentSort === 'stars') {
      items = [...items].sort((a,b) => (b.saves ?? 0) - (a.saves ?? 0));
    }
    const total = res.total ?? items.length;
    meta.textContent = `${total} hasil`;

    if (!items.length) {
      grid.innerHTML = '';
      empty.hidden = false;
      if (manualTrigger && window.notifyUser) {
        window.notifyUser({
          title: 'Tidak ada hasil',
          message: 'Coba gunakan kata kunci lain atau ubah filter pencarian. Kamu juga bisa mengunggah karya terbaru agar muncul di galeri.',
          type: 'info'
        });
      }
      manualTrigger = false;
      return;
    }
    empty.hidden = true;
    grid.innerHTML = items.map(card).join('');
    manualTrigger = false;
  }

  let manualTrigger = false;

  btnSearch?.addEventListener('click', (e) => {
    e.preventDefault();
    manualTrigger = true;
    runSearch();
  });
  qInput?.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      manualTrigger = true;
      runSearch();
    }
  });
  let inputTimer;
  qInput?.addEventListener('input', () => {
    clearTimeout(inputTimer);
    manualTrigger = false;
    inputTimer = setTimeout(runSearch, 350);
  });
  [sortSel, catSel, dlSel].forEach(el => el?.addEventListener('change', () => {
    manualTrigger = false;
    runSearch();
  }));

  const urlParams = new URLSearchParams(location.search);
  const urlQ = urlParams.get('q') || '';
  if (qInput) qInput.value = urlQ;
  if (urlParams.get('category')) catSel.value = urlParams.get('category');

  runSearch().catch(err => {
    manualTrigger = false;
    grid.innerHTML = `<div class="muted">Gagal memuat: ${err.message}</div>`;
  });

  setupTypingPlaceholder();

  function setupTypingPlaceholder() {
    if (!typingInput || !typingInput.hasAttribute('data-typing-placeholder')) return;
    const examples = [
      'Portofolio DKV siswa',
      'Poster lomba desain',
      'CV kreatif SMK',
      'Board game packaging',
      'Mockup kemasan makanan',
      'Logo ekstrakurikuler',
      'Kartu nama guru tamu',
      'Brosur pameran sekolah',
      'Layout majalah kampus',
      'Template buku tahunan',
      'Desain spanduk pentas seni',
      'UI kiosk sekolah',
      'Undangan wisuda SMK',
      'Branding produk UMKM',
      'Story board animasi'
    ];
    let order = shuffleArray(examples);
    let index = 0;
    let char = 0;
    let typing = true;
    let timer;

    const typeSpeed = 80;
    const deleteSpeed = 40;
    const pauseBetween = 1600;

    const loop = () => {
      const text = order[index];
      if (typing) {
        typingInput.setAttribute('placeholder', text.slice(0, char));
        if (char < text.length) {
          char++;
          timer = setTimeout(loop, typeSpeed);
        } else {
          typing = false;
          timer = setTimeout(loop, pauseBetween);
        }
      } else {
        if (char > 0) {
          char--;
          typingInput.setAttribute('placeholder', text.slice(0, char));
          timer = setTimeout(loop, deleteSpeed);
        } else {
          typing = true;
          index += 1;
          if (index >= order.length) {
            order = shuffleArray(examples);
            index = 0;
          }
          timer = setTimeout(loop, 300);
        }
      }
    };

    typingInput.addEventListener('focus', () => {
      typingInput.setAttribute('placeholder', '');
      clearTimeout(timer);
    });
    typingInput.addEventListener('blur', () => {
      clearTimeout(timer);
      char = 0;
      typing = true;
      loop();
    });

    loop();

    function shuffleArray(arr) {
      const copy = arr.slice();
      for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
      }
      return copy;
    }
  }
})();
