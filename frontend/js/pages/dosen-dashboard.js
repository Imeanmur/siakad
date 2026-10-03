document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Dashboard Dosen',
    active: 'dashboard',
    expectedRole: 'dosen',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const greetingTitle = document.getElementById('greeting-title');
  const dosenInfo = document.getElementById('dosen-info');
  const statClasses = document.getElementById('stat-classes');
  const announcementsEl = document.getElementById('announcements');

  try {
    const data = await Api.get('/dosen/dashboard');

    if (data.dosen) {
      greetingTitle.textContent = `Selamat Datang, ${data.dosen.nama_lengkap}!`;
      dosenInfo.textContent = `NIDN: ${data.dosen.nidn || '-'} | Role: Dosen Pengampu`;
    }

    if (statClasses) {
      statClasses.textContent = data.classes_count ?? 0;
    }

    if (announcementsEl) {
      if (!data.announcements || data.announcements.length === 0) {
        announcementsEl.innerHTML = '<div class="alert alert-secondary text-muted mb-0">Belum ada pengumuman untuk dosen saat ini.</div>';
      } else {
        announcementsEl.innerHTML = data.announcements.map((a) => `
          <div class="card shadow-sm mb-3 border-0 border-start border-4 border-primary">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <h5 class="card-title fs-6 fw-bold mb-0 text-dark">${Ui.esc(a.judul)}</h5>
                <small class="text-muted"><i class="bi bi-clock me-1"></i>${Ui.esc(Ui.formatDate(a.created_at))}</small>
              </div>
              <p class="card-text text-secondary mb-0 small">${Ui.esc(a.isi)}</p>
            </div>
          </div>
        `).join('');
      }
    }
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat data dashboard dosen.')}</div>`;
  }
});
