(function(){
  const contactFetch = typeof apiFetch === 'function'
    ? apiFetch
    : async (endpoint, opts={}) => {
        const res = await fetch((window.API_BASE || '/kreasiku/api') + endpoint, {
          credentials:'include', ...opts
        });
        const data = await res.json();
        if (!res.ok || !data?.ok) throw new Error(data?.error || 'HTTP_'+res.status);
        return data;
      };

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.contact-form');
    if (!form) return;
    const status = document.createElement('p');
    status.className = 'contact-status';
    form.appendChild(status);

    setupSuggestions(form);

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const nama = document.getElementById('nama').value.trim();
      const email = document.getElementById('email').value.trim();
      const pesan = document.getElementById('pesan').value.trim();
      if (!nama || !email || !pesan) {
        status.textContent = 'Lengkapi semua kolom terlebih dahulu.';
        status.classList.add('error');
        return;
      }
      status.textContent = 'Mengirim...';
      status.classList.remove('error');
      try {
        await contactFetch('/contact.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ name:nama, email, message:pesan })
        });
        status.textContent = 'Pesan terkirim! Kami akan menghubungi Anda kembali.';
        form.reset();
      } catch (err) {
        status.textContent = 'Gagal mengirim: ' + err.message;
        status.classList.add('error');
      }
    });
  });

  function setupSuggestions(form) {
    const auth = safeJSON(localStorage.getItem('authUser'));
    if (!auth) return;
    const sugWrap = document.createElement('div');
    sugWrap.className = 'contact-suggestions';
    const nameChip = document.createElement('button');
    nameChip.type = 'button';
    nameChip.className = 'contact-chip';
    nameChip.textContent = auth.name || 'Gunakan nama saya';
    nameChip.addEventListener('click', () => {
      document.getElementById('nama').value = auth.name || auth.email?.split('@')[0] || '';
    });
    const emailChip = document.createElement('button');
    emailChip.type = 'button';
    emailChip.className = 'contact-chip';
    emailChip.textContent = auth.email || 'Gunakan email saya';
    emailChip.addEventListener('click', () => {
      document.getElementById('email').value = auth.email || '';
    });
    const nameInput = document.getElementById('nama');
    const emailInput = document.getElementById('email');
    if (nameInput && !nameInput.value && (auth.name || auth.email)) {
      nameInput.value = auth.name || auth.email.split('@')[0];
    }
    if (emailInput && !emailInput.value && auth.email) {
      emailInput.value = auth.email;
    }
    sugWrap.appendChild(nameChip);
    sugWrap.appendChild(emailChip);
    form.insertBefore(sugWrap, form.firstElementChild);
  }

  function safeJSON(str){
    try { return JSON.parse(str || 'null'); }
    catch { return null; }
  }
})();
