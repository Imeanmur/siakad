(async () => {
  await Layout.init({ title: 'Dashboard Admin', active: 'dashboard' });

  try {
    const s = await Api.get('/admin/stats');
    document.getElementById('stat-mahasiswa').textContent = s.mahasiswa;
    document.getElementById('stat-dosen').textContent = s.dosen;
    document.getElementById('stat-matkul').textContent = s.mata_kuliah;

    if (s.pending_unblock > 0) {
      document.getElementById('pending-count').textContent = s.pending_unblock;
      document.getElementById('pending-banner').classList.remove('d-none');
    }

    const box = document.getElementById('announcements');
    if (!s.announcements.length) {
      box.innerHTML = '<div class="card"><div class="card-body text-muted">Belum ada pengumuman.</div></div>';
    } else {
      box.innerHTML = s.announcements.map((a) => `
        <div class="card mb-3">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <h3 class="fs-5 mb-1">${Ui.esc(a.judul)}</h3>
              <span class="badge bg-secondary">${Ui.esc(Ui.cap(a.target_role))}</span>
            </div>
            <small class="text-muted">${Ui.dateTime(a.created_at)}</small>
            <p class="mb-0 mt-2 pre-wrap">${Ui.esc(a.isi)}</p>
          </div>
        </div>`).join('');
    }
  } catch (err) {
    Ui.alert('danger', err.message);
  }
})();
