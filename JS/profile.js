(function(){
  const base = window.__PATH_PREFIX__ || "";
  const auth = safeParse(localStorage.getItem("authUser"));
  if (!auth) { window.location.href = base + "login.html"; return; }

  const uid = auth.uid || auth.email || auth.name || "anonymous";

  const qs = (s, r=document)=>r.querySelector(s);
  const sbAvatar = qs("#sbAvatar"), sbName = qs("#sbName"), sbClass = qs("#sbClass");
  const pv = qs("#profileView");
  const pvPhoto = qs("#pvPhoto"), pvName = qs("#pvName"), pvClass = qs("#pvClass");
  const pvNameFull = qs("#pvNameFull"), pvClassLine = qs("#pvClassLine"), pvBio = qs("#pvBio");
  const btnGoEdit = qs("#btnGoEdit");

  const form = qs("#profileForm");
  const firstName = qs("#firstName"), lastName = qs("#lastName"), kelas = qs("#kelas"), bio = qs("#bio");
  const pePhoto = qs("#pePhoto"), photoInput = qs("#photoInput"), peDisplay = qs("#peDisplay");
  const btnCancel = qs("#btnCancel");

  let currentProfile = safeParse(localStorage.getItem(`kreasiku_profile_${uid}`)) || {
    firstName: auth.name || 'User',
    lastName: '',
    kelas: '',
    bio: '',
    photo: ''
  };
  applyView(currentProfile);

  function safeParse(s){ try{ return JSON.parse(s||"null"); }catch{ return null; } }

  function cacheProfile(profile){
    localStorage.setItem(`kreasiku_profile_${uid}`, JSON.stringify(profile));
    window.dispatchEvent(new CustomEvent("profileUpdated", { detail:{ uid } }));
  }

  function applySidebar(profile){
    const full = [profile.firstName, profile.lastName].filter(Boolean).join(" ").toLowerCase();
    if (sbName) sbName.textContent = full || (auth.name || auth.email || "pengguna");
    if (sbClass) sbClass.textContent = profile.kelas || "—";
    if (sbAvatar){
      if (profile.photo){
        sbAvatar.innerHTML = `<img src="${profile.photo}" alt="Foto profil">`;
      } else {
        sbAvatar.textContent = "👤";
      }
    }
  }

  function applyView(profile){
    const full = [profile.firstName, profile.lastName].filter(Boolean).join(" ").trim();
    if (profile.photo){
      pvPhoto.src = profile.photo;
      pvPhoto.style.opacity = "1";
    } else {
      pvPhoto.removeAttribute("src");
      pvPhoto.style.opacity = ".2";
    }
    pvName.textContent = full || "—";
    pvClass.textContent = profile.kelas || "—";
    pvNameFull.textContent = full || "—";
    pvClassLine.textContent = profile.kelas || "—";
    pvBio.textContent = profile.bio || "—";
    applySidebar(profile);
  }

  function populateForm(profile){
    firstName.value = profile.firstName || "";
    lastName.value  = profile.lastName  || "";
    kelas.value     = profile.kelas     || "";
    bio.value       = profile.bio       || "";
    if (profile.photo) {
      pePhoto.src = profile.photo;
    } else {
      pePhoto.removeAttribute("src");
    }
    peDisplay.textContent = [profile.firstName, profile.lastName].filter(Boolean).join(" ") || "—";
  }

  async function fetchProfile(){
    const res = await apiFetch('/users/me');
    const profile = res.profile || {};
    currentProfile = profile;
    cacheProfile(profile);
    applyView(profile);
  }

  btnGoEdit.addEventListener("click", ()=>{
    if (currentProfile) populateForm(currentProfile);
    pv.hidden = true;
    form.hidden = false;
  });

  btnCancel.addEventListener("click", ()=>{
    form.hidden = true;
    pv.hidden = false;
    photoInput.value = '';
  });

  photoInput.addEventListener("change", ()=>{
    const file = photoInput.files?.[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    pePhoto.src = url;
  });

  [firstName, lastName].forEach(inp=>{
    inp.addEventListener("input", ()=>{
      peDisplay.textContent = [firstName.value.trim(), lastName.value.trim()].filter(Boolean).join(" ") || "—";
    });
  });

  form.addEventListener("submit", async (e)=>{
    e.preventDefault();
    try {
      const fd = new FormData();
      fd.append('firstName', firstName.value.trim());
      fd.append('lastName', lastName.value.trim());
      fd.append('kelas', kelas.value.trim());
      fd.append('bio', bio.value.trim());
      if (photoInput.files?.[0]) fd.append('avatar', photoInput.files[0]);
      const res = await apiFetch('/users/me', { method:'POST', body: fd });
      currentProfile = res.profile || currentProfile;
      cacheProfile(currentProfile);
      applyView(currentProfile);
      form.hidden = true;
      pv.hidden = false;
      photoInput.value = '';
    } catch (err) {
      alert('Gagal menyimpan profil: ' + (err.message || err));
    }
  });

  fetchProfile().catch(err=>{
    if (err?.message === 'UNAUTHENTICATED') {
      window.location.href = base + "login.html";
      return;
    }
    console.error('Gagal memuat profil', err);
    const fallback = safeParse(localStorage.getItem(`kreasiku_profile_${uid}`)) || { firstName: auth.name || 'User', lastName:'', kelas:'', bio:'', photo:'' };
    currentProfile = fallback;
    applyView(fallback);
  });
})();
