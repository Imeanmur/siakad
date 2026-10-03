/**
 * Klien API: menyimpan token di sessionStorage, mengirim header Authorization,
 * dan memperbarui token (sesi geser 30 menit) dari header X-New-Token.
 */
const Api = (() => {
  const TOKEN_KEY = 'siakad_token';
  const USER_KEY = 'siakad_user';

  class ApiError extends Error {
    constructor(message, status, data) {
      super(message);
      this.status = status;
      this.data = data || {};
    }
  }

  const getToken = () => sessionStorage.getItem(TOKEN_KEY);
  const getUser = () => {
    try { return JSON.parse(sessionStorage.getItem(USER_KEY)); } catch { return null; }
  };
  const setSession = (token, user) => {
    sessionStorage.setItem(TOKEN_KEY, token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(user));
  };
  const clearSession = () => {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
  };

  // Halaman login ada di root situs; halaman dashboard di subfolder (data-root="../").
  const rootPath = () => document.body.dataset.root || '';
  const goLogin = (status) => {
    clearSession();
    window.location.replace(rootPath() + 'login.html' + (status ? `?status=${status}` : ''));
  };

  async function send(method, path, body) {
    const headers = {};
    const token = getToken();
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    let res;
    try {
      res = await fetch(window.SIAKAD_CONFIG.API_BASE + path, {
        method,
        headers,
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch {
      throw new ApiError('Tidak dapat terhubung ke server. Periksa koneksi Anda.', 0);
    }

    const fresh = res.headers.get('X-New-Token');
    if (fresh && getToken()) sessionStorage.setItem(TOKEN_KEY, fresh);
    return res;
  }

  async function request(method, path, body) {
    const res = await send(method, path, body);
    let data = {};
    try { data = await res.json(); } catch { /* body kosong */ }

    if (!res.ok) {
      // Sesi habis / token ditolak pada halaman yang dilindungi -> kembali ke login.
      if (res.status === 401 && getToken() && !path.startsWith('/auth/login')) {
        goLogin(data.expired ? 'timeout' : null);
      }
      throw new ApiError(data.error || 'Terjadi kesalahan.', res.status, data);
    }
    return data;
  }

  /** Unduh respons sebagai file (mis. CSV) dengan menyertakan token. */
  async function download(path, filename) {
    const res = await send('GET', path);
    if (!res.ok) {
      let data = {};
      try { data = await res.json(); } catch { /* abaikan */ }
      throw new ApiError(data.error || 'Gagal mengunduh berkas.', res.status, data);
    }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  return {
    ApiError,
    getToken, getUser, setSession, clearSession, goLogin,
    get: (p) => request('GET', p),
    post: (p, b) => request('POST', p, b === undefined ? {} : b),
    put: (p, b) => request('PUT', p, b),
    del: (p) => request('DELETE', p),
    download,
  };
})();
