(function(){
  const base = window.__PATH_PREFIX__ || "";
  const auth = safeParse(localStorage.getItem("authUser"));
  if (!auth) { window.location.href = base + "login.html"; return; }

  const KEY = (uid)=> `kreasiku_profile_${uid}`;
  const uid = auth.uid || auth.email || auth.name || "anonymous";

  // DOM
  const sbAvatar = qs("#sbAvatar"), sbName = qs("#sbName"), sbClass = qs("#sbClass");

  const pv = qs("#profileView");
  const pvPhoto = qs("#pvPhoto"), pvName = qs("#pvName"), pvClass = qs("#pvClass");
  const pvNameFull = qs("#pvNameFull"), pvClassLine = qs("#pvClassLine"), pvBio = qs("#pvBio");
  const btnGoEdit = qs("#btnGoEdit");

  const form = qs("#profileForm");
  const firstName = qs("#firstName"), lastName = qs("#lastName"), kelas = qs("#kelas"), bio = qs("#bio");
  const pePhoto = qs("#pePhoto"), photoInput = qs("#photoInput"), peDisplay = qs("#peDisplay");
  const btnCancel = qs("#btnCancel");

  function qs(s, r=document){ return r.querySelector(s); }
  function safeParse(s){ try{ return JSON.parse(s||"null"); }catch{ return null; } }
  const readAsDataURL = f => new Promise((res,rej)=>{ const fr=new FileReader(); fr.onload=()=>res(fr.result); fr.onerror=rej; fr.readAsDataURL(f); });

  function loadProfile(){
    const p = safeParse(localStorage.getItem(KEY(uid))) || {};
    // defaults
    const f = (auth.name || "").split(/[.\s_@-]+/).filter(Boolean);
    return {
      firstName: p.firstName ?? (f[0] || "User"),
      lastName : p.lastName  ?? (f[1] || ""),
      kelas    : p.kelas     ?? "DKV XI",
      bio      : p.bio       ?? "",
      photo    : p.photo     ?? "" // dataURL
    };
  }
  function saveProfile(p){
    localStorage.setItem(KEY(uid), JSON.stringify(p));
    // beri tahu header untuk refresh avatar
    window.dispatchEvent(new CustomEvent("profileUpdated", { detail:{ uid } }));
  }

  function applyToSidebar(p){
    sbName.textContent = `${p.firstName.toLowerCase()}${p.lastName ? " " + p.lastName.toLowerCase() : ""}`;
    sbClass.textContent = p.kelas || "-";
    if (p.photo){
      sbAvatar.innerHTML = "";
      const img = document.createElement("img");
      img.src = p.photo; img.alt = "Foto profil"; img.style.width="100%"; img.style.height="100%"; img.style.objectFit="cover"; img.style.borderRadius="50%";
      sbAvatar.appendChild(img);
    }else{
      sbAvatar.textContent = "👤";
    }
  }

  function applyToView(p){
    // foto
    if (p.photo){ pvPhoto.src = p.photo; pvPhoto.style.opacity="1"; }
    else { pvPhoto.removeAttribute("src"); pvPhoto.style.opacity=".2"; }

    const full = [p.firstName, p.lastName].filter(Boolean).join(" ").trim();
    pvName.textContent = full || "—";
    pvClass.textContent = p.kelas || "—";

    pvNameFull.textContent = full || "—";
    pvClassLine.textContent = p.kelas || "—";
    pvBio.textContent = (p.bio || "—");

    applyToSidebar(p);
  }

  function populateForm(p){
    firstName.value = p.firstName || "";
    lastName.value  = p.lastName  || "";
    kelas.value     = p.kelas     || "";
    bio.value       = p.bio       || "";
    pePhoto.src     = p.photo || "";
    peDisplay.textContent = [p.firstName, p.lastName].filter(Boolean).join(" ") || "—";
  }

  // Toggle modes
  btnGoEdit.addEventListener("click", ()=>{
    populateForm(loadProfile());
    pv.hidden = true;
    form.hidden = false;
  });
  btnCancel.addEventListener("click", ()=>{
    form.hidden = true;
    pv.hidden = false;
  });

  // Change photo
  photoInput.addEventListener("change", async ()=>{
    const f = photoInput.files?.[0];
    if (!f) return;
    const url = await readAsDataURL(f);
    pePhoto.src = url;
  });

  // Reflect display name while typing
  [firstName, lastName].forEach(inp=>{
    inp.addEventListener("input", ()=>{
      peDisplay.textContent = [firstName.value.trim(), lastName.value.trim()].filter(Boolean).join(" ") || "—";
    });
  });

  // Save
  form.addEventListener("submit", (e)=>{
    e.preventDefault();
    const current = loadProfile();
    const next = {
      firstName: firstName.value.trim() || current.firstName,
      lastName : lastName.value.trim()  || current.lastName,
      kelas    : kelas.value.trim()     || "",
      bio      : bio.value.trim(),
      photo    : pePhoto.getAttribute("src") || current.photo || ""
    };
    saveProfile(next);
    applyToView(next);

    // update avatar header segera jika ada
    const nav = document.querySelector(".nav-avatar");
    if (nav){
      nav.innerHTML = next.photo
        ? `<img src="${next.photo}" alt="Foto profil">`
        : `<span class="avatar-dot" aria-hidden="true">👤</span>`;
    }

    form.hidden = true;
    pv.hidden = false;
  });

  // Initial
  const prof = loadProfile();
  applyToView(prof);

  // Jika header sudah ada, sinkronkan avatar (ketika pertama kali masuk ke halaman ini)
  window.dispatchEvent(new CustomEvent("profileUpdated", { detail:{ uid } }));
})();
