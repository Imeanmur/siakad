document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Kelola Absensi',
    active: 'absensi',
    expectedRole: 'dosen',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const container = document.getElementById('class-list-container');
  const searchInput = document.getElementById('search-input');

  let classes = [];

  function renderClasses(list) {
    if (!list || list.length === 0) {
      container.innerHTML = `
        <div class="col-12">
          <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
              <i class="bi bi-calendar-x fs-1 text-muted mb-3 d-block"></i>
              <h5 class="fw-semibold text-dark">Tidak ada kelas ditemukan</h5>
              <p class="text-muted small mb-0">Anda belum memiliki jadwal mengajar atau pencarian tidak cocok.</p>
            </div>
          </div>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map((c) => {
      const jam = `${(c.jam_mulai || '').slice(0, 5)} - ${(c.jam_selesai || '').slice(0, 5)}`;
      return `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 shadow-sm border-0 class-card hover-lift">
            <div class="card-body p-4 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-success-subtle text-success fw-bold px-2 py-1 rounded">
                  ${Ui.esc(c.kode_matkul)}
                </span>
                <span class="badge bg-light text-secondary border">
                  <i class="bi bi-people me-1"></i>${c.total_mahasiswa || 0} Mahasiswa
                </span>
              </div>
              <h3 class="fs-5 fw-bold text-dark mb-2">${Ui.esc(c.nama_matkul)}</h3>
              <p class="text-muted small mb-4">
                <i class="bi bi-clock me-1 text-success"></i> ${Ui.esc(c.hari)}, ${Ui.esc(jam)} WIB
              </p>
              <div class="mt-auto pt-2 border-top">
                <a href="kelola-absensi.html?jadwal_id=${c.jadwal_id}" class="btn btn-outline-success w-100 fw-semibold">
                  <i class="bi bi-calendar2-check me-1"></i> Sesi Pertemuan & Absensi
                </a>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  try {
    classes = await Api.get('/dosen/classes');
    renderClasses(classes);
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat daftar kelas.')}</div>`;
    container.innerHTML = '';
  }

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      const filtered = classes.filter((c) =>
        (c.nama_matkul || '').toLowerCase().includes(q) ||
        (c.kode_matkul || '').toLowerCase().includes(q) ||
        (c.hari || '').toLowerCase().includes(q)
      );
      renderClasses(filtered);
    });
  }
});
