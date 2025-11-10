window.API_BASE = '/kreasiku/api';

document.addEventListener("DOMContentLoaded", () => {
  const base = window.__PATH_PREFIX__ || "";

  loadHTML(base + "header.html", "#header", base + "css/header.css", base);
  loadHTML(base + "footer.html", "#footer", base + "css/footer.css", base);

  // ===== Optional marquee (aman kalau elemen tidak ada) =====
  const tracks = document.querySelectorAll(".marquee-track");
  if (tracks.length) {
    tracks.forEach(t => { t.innerHTML += t.innerHTML; });
    const chips = document.querySelectorAll(".feature-section .tag");
    chips.forEach(chip => {
      chip.addEventListener("mouseenter", () => tracks.forEach(tt => tt.style.animationPlayState = "paused"));
      chip.addEventListener("mouseleave", () => tracks.forEach(tt => tt.style.animationPlayState = "running"));
    });
  }
});

function loadHTML(file, targetSelector, cssFile, base) {
  fetch(file)
    .then(res => res.text())
    .then(html => {
      const slot = document.querySelector(targetSelector);
      if (!slot) return;
      slot.innerHTML = html;

      const authRaw = localStorage.getItem("authUser");
      const masukBtn = document.querySelector(".header-buttons .btn-link, .header-buttons a.btn-link");
      const startBtn = document.querySelector(".header-buttons .btn-start");

      if (targetSelector === "#header") {
        if (authRaw) {
          const wrap = masukBtn ? masukBtn.parentElement : null;
          if (wrap && masukBtn) {
            masukBtn.remove();
            const avatarLink = document.createElement("a");
            avatarLink.href = base + "pages/account/Account.html";
            avatarLink.className = "nav-avatar";
            avatarLink.setAttribute("aria-label", "Akun");
            wrap.prepend(avatarLink);
          }
          if (startBtn) startBtn.style.display = "none";

          // Sembunyikan "Masuk" lain di konten
          document.querySelectorAll("button, a").forEach(el => {
            const t = (el.textContent || "").trim().toLowerCase();
            if (t === "masuk") el.style.display = "none";
          });

          // Render avatar berdasarkan profil tersimpan
          applyHeaderAvatar();
          // Update jika profil berubah
          window.addEventListener("profileUpdated", applyHeaderAvatar);
          window.addEventListener("storage", e => { if (e.key && e.key.startsWith("kreasiku_profile_")) applyHeaderAvatar(); });
        } else {
          const navAvatar = document.querySelector(".nav-avatar");
          if (navAvatar) navAvatar.remove();
          if (startBtn) startBtn.style.display = "";
          if (masukBtn) masukBtn.style.display = "";
        }

        if (!document.querySelector("#navAvatarStyle")) {
          const style = document.createElement("style");
          style.id = "navAvatarStyle";
          style.textContent = `
            .nav-avatar{ display:inline-grid; place-items:center; width:36px; height:36px;
              border-radius:50%; background:#EDEBFF; text-decoration:none; margin-right:8px; overflow:hidden; }
            .nav-avatar .avatar-dot{ font-size:18px; }
            .nav-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
          `;
          document.head.appendChild(style);
        }

        // Simpan tinggi header ke CSS var (opsional)
        const headerEl = document.querySelector("#header");
        if (headerEl) {
          const h = headerEl.offsetHeight || 72;
          document.documentElement.style.setProperty("--header-h", h + "px");
          window.addEventListener("resize", () => {
            const hh = headerEl.offsetHeight || 72;
            document.documentElement.style.setProperty("--header-h", hh + "px");
          });
        }
      }

      if (cssFile && !document.querySelector(`link[href="${cssFile}"]`)) {
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = cssFile;
        document.head.appendChild(link);
      }
      fixRelativeUrls(slot, base);
    })
    .catch(() => {});
}

function applyHeaderAvatar(){
  const nav = document.querySelector(".nav-avatar");
  if (!nav) return;
  const auth = safeParse(localStorage.getItem("authUser"));
  if (!auth){ nav.innerHTML = `<span class="avatar-dot">👤</span>`; return; }
  const uid = auth.uid || auth.email || auth.name || "anonymous";
  const prof = safeParse(localStorage.getItem(`kreasiku_profile_${uid}`)) || {};
  nav.innerHTML = prof.photo
    ? `<img src="${prof.photo}" alt="Foto profil">`
    : `<span class="avatar-dot" aria-hidden="true">👤</span>`;
}

function safeParse(s){ try{ return JSON.parse(s||"null"); }catch{ return null; } }

/* Prefix relative paths */
function fixRelativeUrls(root, base = "") {
  const isRel = url =>
    url && !url.startsWith("#") && !url.startsWith("mailto:") && !url.startsWith("tel:") &&
    !/^https?:\/\//i.test(url) && !url.startsWith("/") && !url.startsWith("data:");

  root.querySelectorAll("a[href]").forEach(a => { const raw = a.getAttribute("href"); if (isRel(raw)) a.setAttribute("href", base + raw); });
  root.querySelectorAll("img[src]").forEach(img => {
    const raw = img.getAttribute("src"); if (isRel(raw)) img.setAttribute("src", base + raw);
    img.addEventListener("error", () => { img.style.display = "none"; const s = img.nextElementSibling; if (s) s.style.display = "inline"; });
  });
  root.querySelectorAll('link[rel="stylesheet"][href]').forEach(l => { const raw = l.getAttribute("href"); if (isRel(raw)) l.setAttribute("href", base + raw); });
  root.querySelectorAll("script[src]").forEach(s => { const raw = s.getAttribute("src"); if (isRel(raw)) s.setAttribute("src", base + raw); });
}
