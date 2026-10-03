/**
 * Membangun kerangka dashboard (sidebar + navbar) dan melindungi halaman
 * berdasarkan hak akses (Role-Based Access Control: admin, dosen, mahasiswa).
 */
const Layout = (() => {
  const MENUS = {
    admin: [
      { key: 'dashboard', href: 'index.html', icon: 'bi-speedometer2', label: 'Dashboard' },
      { key: 'users', href: 'users.html', icon: 'bi-people', label: 'Manajemen User' },
      { key: 'students', href: 'students.html', icon: 'bi-person-fill', label: 'Manajemen Mahasiswa' },
      { key: 'lecturers', href: 'lecturers.html', icon: 'bi-people', label: 'Manajemen Dosen' },
      { key: 'courses', href: 'courses.html', icon: 'bi-book', label: 'Manajemen Matkul' },
      { key: 'schedules', href: 'schedules.html', icon: 'bi-calendar-week', label: 'Manajemen Jadwal' },
      { key: 'announcements', href: 'announcements.html', icon: 'bi-megaphone', label: 'Pengumuman' },
      { key: 'unblock', href: 'unblock-requests.html', icon: 'bi-unlock', label: 'Permohonan Buka Blokir' },
    ],
    dosen: [
      { key: 'dashboard', href: 'index.html', icon: 'bi-speedometer2', label: 'Dashboard' },
      { key: 'input_nilai', href: 'input-nilai.html', icon: 'bi-pencil-square', label: 'Input Nilai' },
      { key: 'absensi', href: 'absensi.html', icon: 'bi-check-circle-fill', label: 'Absensi' },
    ],
    mahasiswa: [
      { key: 'dashboard', href: 'index.html', icon: 'bi-speedometer2', label: 'Dashboard' },
      { key: 'krs', href: 'krs.html', icon: 'bi-card-list', label: 'Isi KRS' },
      { key: 'khs', href: 'khs.html', icon: 'bi-journal-text', label: 'Lihat KHS' },
      { key: 'jadwal', href: 'jadwal.html', icon: 'bi-calendar-check', label: 'Lihat Jadwal' },
      { key: 'absensi', href: 'absensi.html', icon: 'bi-person-check-fill', label: 'Lihat Absensi' },
    ],
  };

  function shell(title, active, role, username) {
    const list = MENUS[role] || [];
    const links = list.map((m) => `
      <a href="${m.href}" class="list-group-item list-group-item-action bg-dark text-white${m.key === active ? ' active' : ''}">
        <i class="bi ${m.icon} me-2"></i>${Ui.esc(m.label)}
      </a>`).join('');

    return `
      <div class="d-flex" id="wrapper">
        <div class="bg-dark border-right" id="sidebar-wrapper">
          <div class="sidebar-heading text-white text-center py-4 fs-5 fw-bold">SIAKAD</div>
          <div class="list-group list-group-flush my-3">
            ${links}
            <a href="#" id="logout-link" class="list-group-item list-group-item-action bg-dark text-danger mt-auto">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
          </div>
        </div>
        <div id="page-content-wrapper">
          <nav class="navbar navbar-expand-lg navbar-light bg-transparent py-4 px-4">
            <div class="d-flex align-items-center w-100">
              <i class="bi bi-list fs-4 me-3" id="menu-toggle" role="button" aria-label="Tampilkan/sembunyikan menu"></i>
              <h1 class="fs-2 m-0">${Ui.esc(title)}</h1>
              <div class="ms-auto text-muted small d-none d-md-flex align-items-center gap-2">
                <span class="badge bg-secondary text-capitalize">${Ui.esc(role)}</span>
                <span><i class="bi bi-person-circle me-1"></i>${Ui.esc(username)}</span>
              </div>
            </div>
          </nav>
          <div class="container-fluid px-4 pb-5" id="content"></div>
        </div>
      </div>`;
  }

  /**
   * @param {{title: string, active: string, expectedRole?: string}} opts
   */
  async function init({ title, active, expectedRole = 'admin' }) {
    document.title = `${title} - SIAKAD`;

    const halt = () => new Promise(() => {});
    if (!Api.getToken()) { Api.goLogin(); return halt(); }

    let me;
    try {
      me = (await Api.get('/auth/me')).user;
    } catch (err) {
      if (err.status === 401) return halt();
      document.getElementById('page-loader').innerHTML =
        `<div class="alert alert-danger m-4">${Ui.esc(err.message)}</div>`;
      return halt();
    }

    // Jika halaman diperuntukkan untuk role tertentu dan role pengguna berbeda
    if (expectedRole && me.role !== expectedRole) {
      const redirectMap = {
        admin: '../admin/index.html',
        dosen: '../dosen/index.html',
        mahasiswa: '../mahasiswa/index.html',
      };
      window.location.replace(redirectMap[me.role] || '../login.html');
      return halt();
    }

    const main = document.getElementById('page-main');
    const holder = document.createElement('div');
    holder.innerHTML = shell(title, active, me.role, me.username);
    document.body.insertBefore(holder.firstElementChild, main);
    document.getElementById('content').appendChild(main);
    main.classList.remove('d-none');
    document.getElementById('page-loader').remove();

    document.getElementById('menu-toggle').addEventListener('click', () => {
      document.getElementById('wrapper').classList.toggle('toggled');
    });

    document.getElementById('logout-link').addEventListener('click', async (e) => {
      e.preventDefault();
      try { await Api.post('/auth/logout'); } catch { /* ignore */ }
      Api.goLogin('logout');
    });

    return me;
  }

  return { init };
})();
