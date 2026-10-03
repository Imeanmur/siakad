document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Dashboard Mahasiswa',
    active: 'dashboard',
    expectedRole: 'mahasiswa',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const greetingName = document.getElementById('greeting-name');
  const studentBio = document.getElementById('student-bio');
  const semesterBadge = document.getElementById('semester-badge');
  const statSks = document.getElementById('stat-sks');
  const statMaxSks = document.getElementById('stat-max-sks');
  const sksProgress = document.getElementById('sks-progress');
  const statCourses = document.getElementById('stat-courses');
  const announcementsContainer = document.getElementById('announcements-container');

  try {
    const data = await Api.get('/mahasiswa/dashboard');

    if (data.mahasiswa) {
      const m = data.mahasiswa;
      greetingName.textContent = `Selamat Datang, ${m.nama_lengkap}!`;
      studentBio.textContent = `NIM: ${m.nim} • Program Studi: ${m.jurusan || '-'} • Angkatan: ${m.angkatan || '-'}`;
    }

    if (data.krs_info) {
      const k = data.krs_info;
      semesterBadge.textContent = `Tahun Ajaran ${k.tahun_ajaran} ${k.semester}`;
      statSks.textContent = k.total_sks || 0;
      statMaxSks.textContent = `dari maks ${k.max_sks || 24} SKS`;
      statCourses.textContent = k.total_matkul || 0;

      const pct = Math.min(100, Math.round(((k.total_sks || 0) / (k.max_sks || 24)) * 100));
      sksProgress.style.width = `${pct}%`;
      if (pct > 85) sksProgress.className = 'progress-bar bg-warning';
      else sksProgress.className = 'progress-bar bg-primary';
    }

    if (data.announcements && data.announcements.length > 0) {
      announcementsContainer.innerHTML = data.announcements.map((a) => `
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
    } else {
      announcementsContainer.innerHTML = '<div class="alert alert-secondary text-muted mb-0">Belum ada pengumuman terbaru untuk mahasiswa saat ini.</div>';
    }
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat dashboard mahasiswa.')}</div>`;
  }
});
