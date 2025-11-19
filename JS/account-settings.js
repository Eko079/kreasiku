(function(){
  const base = window.__PATH_PREFIX__ || "";
  const auth = parse(localStorage.getItem("authUser"));
  if (!auth){ window.location.href = base + "login.html"; return; }
  const uid = auth.uid || auth.email || auth.name || "anonymous";

  function parse(s){ try{ return JSON.parse(s||"null"); }catch{ return null; } }
  const fmt = (iso)=> iso ? new Date(iso).toLocaleString("id-ID",{day:"2-digit",month:"long",year:"numeric",hour:"2-digit",minute:"2-digit"}) : "—";

  const sbAvatar = document.getElementById("sbAvatar");
  const sbName   = document.getElementById("sbName");
  const sbClass  = document.getElementById("sbClass");
  const ctAvatar = document.getElementById("ctAvatar");
  const ctName   = document.getElementById("ctName");
  const ctClass  = document.getElementById("ctClass");

  function setAvatar(el, photo){
    if (!el) return;
    if (photo){
      el.innerHTML = `<img src="${photo}" alt="Foto profil" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`;
    } else {
      el.textContent = "👤";
    }
  }

  async function loadProfile(){
    try {
      const res = await apiFetch('/users/me');
      const profile = res.profile || {};
      const name = [profile.firstName || auth.name || "User", profile.lastName || ""].filter(Boolean).join(" ");
      sbName.textContent = name.toLowerCase();
      sbClass.textContent = profile.kelas || "—";
      ctName.textContent = name;
      ctClass.textContent = profile.kelas || "—";
      setAvatar(sbAvatar, profile.photo);
      setAvatar(ctAvatar, profile.photo);
      localStorage.setItem(`kreasiku_profile_${uid}`, JSON.stringify(profile));
      window.dispatchEvent(new CustomEvent('profileUpdated', { detail:{ uid } }));

      document.getElementById("infoUid").textContent     = res.user?.id || uid;
      document.getElementById("infoEmail").textContent   = res.user?.email || auth.email || "—";
      document.getElementById("infoCreated").textContent = fmt(res.user?.createdAt);
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + "login.html";
        return;
      }
      console.warn("Gagal memuat profil akun", err);
    }
  }

  async function loadCounters(){
    try {
      const designs = await apiFetch('/me/designs?perPage=1');
      document.getElementById("infoUploads").textContent = designs.meta?.total ?? designs.data?.length ?? 0;
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + "login.html";
        return;
      }
      document.getElementById("infoUploads").textContent = "0";
    }
    try {
      const saved = await apiFetch('/me/saved?perPage=1');
      document.getElementById("infoSaved").textContent = saved.meta?.total ?? saved.data?.length ?? 0;
    } catch (err) {
      if (err?.message === 'UNAUTHENTICATED') {
        window.location.href = base + "login.html";
        return;
      }
      document.getElementById("infoSaved").textContent = "0";
    }
  }

  const askConfirm = async (options, fallbackMessage) => {
    if (window.confirmAction) {
      return await window.confirmAction(options);
    }
    return confirm(fallbackMessage || options.message || 'Lanjutkan tindakan ini?');
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

  document.getElementById("btnLogout").addEventListener("click", async ()=>{
    const stepOne = await askConfirm({
      title: 'Keluar dari akun?',
      message: 'Apakah kamu yakin ingin keluar sekarang?',
      type: 'info',
      actionText: 'Ya',
      cancelText: 'Batal'
    }, "Keluar dari akun?");
    if (!stepOne) return;
    const ok = await askConfirm({
      title: 'Logout dari Kreasiku?',
      message: 'Kamu harus login kembali untuk mengakses karya dan komentar.',
      type: 'warning',
      actionText: 'Logout',
      cancelText: 'Batal'
    }, "Logout dari Kreasiku?");
    if (!ok) return;
    try { await apiFetch('/auth/logout', { method:'POST' }); } catch {}
    localStorage.removeItem("authUser");
    localStorage.removeItem(`kreasiku_profile_${uid}`);
    const goLogin = () => { window.location.href = base + "login.html"; };
    showPopup({
      title: 'Sampai jumpa!',
      message: 'Kamu berhasil logout. Sampai bertemu di kesempatan berikutnya.',
      type: 'info',
      actionText: 'Ke halaman login',
      onAction: goLogin,
      onClose: goLogin
    });
  });

  document.getElementById("btnDelete").addEventListener("click", async ()=>{
    const stepOne = await askConfirm({
      title: 'Hapus akun?',
      message: 'Tindakan ini tidak bisa dibatalkan.',
      type: 'warning',
      actionText: 'Lanjutkan',
      cancelText: 'Batal'
    }, "Yakin ingin menghapus akun?");
    if (!stepOne) return;
    const stepTwo = await askConfirm({
      title: 'Perhatian',
      message: 'Semua karya, komentar, dan data lainnya akan dihapus permanen. Tunggu beberapa detik sebelum melanjutkan.',
      type: 'warning',
      actionText: 'Saya mengerti',
      cancelText: 'Batal',
      delayMs: 6000
    }, "Semua data akan hilang. Lanjutkan?");
    if (!stepTwo) return;
    const ok = await askConfirm({
      title: 'Konfirmasi akhir',
      message: 'Tulis DELETE untuk menghapus akun secara permanen.',
      type: 'warning',
      actionText: 'Hapus akun',
      cancelText: 'Batal',
      requireText: 'DELETE',
      requireLabel: 'Tuliskan DELETE untuk konfirmasi',
      requirePlaceholder: 'DELETE',
      requireError: 'Masukkan DELETE sesuai instruksi.'
    }, "Ketik DELETE untuk menghapus akun.");
    if (!ok) return;
    try {
      await apiFetch('/users/me', { method:'DELETE' });
      localStorage.removeItem("authUser");
      localStorage.removeItem(`kreasiku_profile_${uid}`);
      const goLogin = () => { window.location.href = base + "login.html"; };
      showPopup({
        title: 'Akun terhapus',
        message: 'Terima kasih telah menggunakan Kreasiku. Kamu bisa membuat akun baru kapan saja.',
        type: 'success',
        actionText: 'Ke halaman login',
        onAction: goLogin,
        onClose: goLogin
      });
    } catch (err) {
      showPopup({
        title: 'Gagal menghapus akun',
        message: err.message || String(err),
        type: 'warning'
      });
    }
  });

  loadProfile();
  loadCounters();
})();
