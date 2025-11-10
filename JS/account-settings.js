(function(){
  const base = window.__PATH_PREFIX__ || "";
  const auth = parse(localStorage.getItem("authUser"));
  if (!auth){ window.location.href = base + "../../login.html"; return; }

  // seed createdAt di auth bila belum ada (supaya bisa ditampilkan)
  if (!auth.createdAt){
    auth.createdAt = new Date().toISOString();
    localStorage.setItem("authUser", JSON.stringify(auth));
  }

  const uid   = auth.uid || auth.email || auth.name || "anonymous";
  const DB    = "kreasiku_uploads";
  const SAVED = (u)=> `kreasiku_saved_${u}`;
  const PROF  = (u)=> `kreasiku_profile_${u}`;

  // ---- helpers
  function parse(s){ try{ return JSON.parse(s||"null"); }catch{ return null; } }
  const fmt = (iso)=> iso ? new Date(iso).toLocaleString("id-ID", {
    day:"2-digit", month:"long", year:"numeric", hour:"2-digit", minute:"2-digit"
  }) : "—";

  // ---- sidebar profile
  const prof = parse(localStorage.getItem(PROF(uid))) || {};
  const name = [prof.firstName || auth.name || "User", prof.lastName || ""].filter(Boolean).join(" ");
  const kelas= prof.kelas || "DKV XI";

  const sbAvatar = document.getElementById("sbAvatar");
  const sbName   = document.getElementById("sbName");
  const sbClass  = document.getElementById("sbClass");
  const ctAvatar = document.getElementById("ctAvatar");
  const ctName   = document.getElementById("ctName");
  const ctClass  = document.getElementById("ctClass");

  function setAvatar(el, photo){
    if (photo){
      el.innerHTML = "";
      const img = document.createElement("img");
      img.src = photo; img.alt = "Foto profil";
      img.style = "width:100%;height:100%;object-fit:cover;border-radius:50%";
      el.appendChild(img);
    }else{
      el.textContent = "👤";
    }
  }

  sbName.textContent = name.toLowerCase();
  sbClass.textContent = kelas;
  ctName.textContent = name;
  ctClass.textContent = kelas;
  setAvatar(sbAvatar, prof.photo);
  setAvatar(ctAvatar, prof.photo);

  // ---- info ringkas
  const uploads = parse(localStorage.getItem(DB)) || [];
  const myUploads = uploads.filter(x => (x.owner||"anonymous") === uid);

  document.getElementById("infoUid").textContent     = auth.uid || "—";
  document.getElementById("infoEmail").textContent   = auth.email || "—";
  document.getElementById("infoCreated").textContent = fmt(auth.createdAt);
  document.getElementById("infoUploads").textContent = String(myUploads.length);
  const savedList = parse(localStorage.getItem(SAVED(uid))) || [];
  document.getElementById("infoSaved").textContent   = String(savedList.length);

  // ---- actions
  document.getElementById("btnLogout").addEventListener("click", ()=>{
    if (!confirm("Logout dari Kreasiku?")) return;
    localStorage.removeItem("authUser");
    // header akan otomatis berubah ke 'Masuk' oleh main.js
    window.location.href = base + "../../login.html";
  });

  document.getElementById("btnDelete").addEventListener("click", ()=>{
    if (!confirm("Yakin ingin menghapus akun? Tindakan ini tidak dapat dibatalkan.")) return;
    if (!confirm("Semua karya yang pernah kamu unggah juga akan dihapus pada perangkat ini. Lanjutkan?")) return;

    // 1) hapus karya milik user dari DB lokal
    const all = parse(localStorage.getItem(DB)) || [];
    const rest = all.filter(x => (x.owner||"anonymous") !== uid);
    localStorage.setItem(DB, JSON.stringify(rest));

    // 2) hapus data saved & profil user
    localStorage.removeItem(SAVED(uid));
    localStorage.removeItem(PROF(uid));

    // 3) hapus sesi login
    localStorage.removeItem("authUser");

    // Catatan: saat backend tersedia, panggil endpoint delete di sini.

    alert("Akun telah dihapus.");
    window.location.href = base + "../../index.html";
  });

  // live refresh bila storage berubah dari tab lain
  window.addEventListener("storage", (e)=>{
    if ([DB, SAVED(uid), PROF(uid), "authUser"].includes(e.key)) location.reload();
  });
})();
