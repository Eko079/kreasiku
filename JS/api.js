(function () {
  // Sesuaikan BASE saat di localhost / hosting ber-subfolder
  if (!window.API_BASE) {
    // contoh default: /kreasiku/api (ubah kalau root-mapped jadi /api)
    window.API_BASE = '/kreasiku/api';
  }

  window.apiFetch = async (endpoint, options = {}) => {
    const res = await fetch(window.API_BASE + endpoint, {
      credentials: 'include',
      ...options
    });
    const ct = res.headers.get('content-type') || '';
    const json = ct.includes('application/json') ? await res.json() : null;
    if (!res.ok || !json?.ok) {
      const msg = json?.error || ('HTTP_' + res.status);
      throw new Error(msg);
    }
    return json;
  };
})();
