(function(){
  const auth = JSON.parse(localStorage.getItem('authUser') || "null");
  const base = window.__PATH_PREFIX__ || "";
  if (!auth){ window.location.href = base + "login.html"; return; }

  // Update sidebar profile
  function updateSidebarProfile() {
    const sbAvatar = document.getElementById("sbAvatar");
    const sbName   = document.getElementById("sbName");
    const sbClass  = document.getElementById("sbClass");

    if (!auth) return;

    const uid = auth.uid || auth.email || auth.name || "anonymous";
    const profile = JSON.parse(localStorage.getItem(`kreasiku_profile_${uid}`) || "null");

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

  updateSidebarProfile();
  window.addEventListener("storage", (e) => {
    if (e.key && (e.key.startsWith("kreasiku_profile_") || e.key === "authUser")) {
      updateSidebarProfile();
    }
  });

  const galleryEl = document.getElementById("myGallery");
  const DB  = "kreasiku_uploads";
  const me  = auth.uid || auth.email || auth.name || "anonymous";

  const load = () => { try{ return JSON.parse(localStorage.getItem(DB) || "[]"); }catch{ return []; } };

  function card(it){
    const media = it.figma
      ? `<iframe src="${it.figma}" loading="lazy"></iframe>`
      : `<img src="${it.images[0]}" alt="${it.title||'Karya'}">`;
    // base sudah "../../", jadi arahkan langsung ke "pages/...":
    const url = `${base}pages/EditDesign.html?id=${encodeURIComponent(it.id)}`;
    return `
      <a class="card" href="${url}" title="Edit karya">
        ${media}
        <div class="title-overlay"><span>${it.title || it.category}</span></div>
      </a>`;
  }

  function render(){
    const items = load().filter(x => (x.owner || "anonymous") === me);
    if (!items.length){
      // Link kosong juga langsung ke "pages/Upload.html"
      galleryEl.innerHTML = `<div class="muted">Belum ada karya. <a href="${base}pages/Upload.html">Upload sekarang</a>.</div>`;
      return;
    }
    galleryEl.innerHTML = items.map(card).join("");
  }

  render();

  window.addEventListener("storage", (e)=>{
    if (e.key === DB) render();
  });
})();
