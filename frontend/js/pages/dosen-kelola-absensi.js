document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Sesi Pertemuan Kelas',
    active: 'absensi',
    expectedRole: 'dosen',
  });
  if (!me) return;

  const urlParams = new URLSearchParams(window.location.search);
  const jadwalId = urlParams.get('jadwal_id');

  if (!jadwalId) {
    window.location.replace('absensi.html');
    return;
  }

  const alertArea = document.getElementById('alert-area');
  const classCodeEl = document.getElementById('class-code');
  const classNameEl = document.getElementById('class-name');
  const sessionCountEl = document.getElementById('session-count');
  const tbody = document.getElementById('session-tbody');
  const addForm = document.getElementById('addSessionForm');
  const inPertemuanKe = document.getElementById('pertemuan_ke');
  const inJudul = document.getElementById('judul_pertemuan');
  const inTanggal = document.getElementById('tanggal_pertemuan');
  const modalEl = document.getElementById('addSessionModal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  let sessions = [];

  // Default input tanggal = hari ini
  inTanggal.value = new Date().toISOString().split('T')[0];

  function renderSessions() {
    sessionCountEl.textContent = `${sessions.length} Pertemuan`;

    // Sarankan pertemuan ke berikutnya
    const nextNum = sessions.reduce((max, s) => Math.max(max, Number(s.pertemuan_ke) || 0), 0) + 1;
    inPertemuanKe.value = nextNum;

    if (!sessions || sessions.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center py-5 text-muted">
            <i class="bi bi-calendar-plus fs-2 d-block mb-2"></i>
            Belum ada sesi pertemuan yang dibuat untuk kelas ini. Klik tombol di atas untuk membuat pertemuan baru.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = sessions.map((s) => `
      <tr>
        <td>
          <span class="badge bg-light text-dark border fw-bold px-2 py-1">
            Pertemuan Ke-${s.pertemuan_ke}
          </span>
        </td>
        <td class="fw-semibold text-dark">${Ui.esc(s.judul_pertemuan)}</td>
        <td class="text-secondary small">
          <i class="bi bi-calendar-event me-1 text-primary"></i> ${Ui.esc(Ui.formatDate(s.tanggal_pertemuan))}
        </td>
        <td class="text-center">
          <a href="detail-absensi.html?pertemuan_id=${s.id}" class="btn btn-success btn-sm fw-semibold">
            <i class="bi bi-person-check me-1"></i> Kelola Presensi
          </a>
        </td>
      </tr>
    `).join('');
  }

  async function loadData() {
    try {
      const res = await Api.get(`/dosen/classes/${jadwalId}/sessions`);
      if (res.class_info) {
        classCodeEl.textContent = res.class_info.kode_matkul;
        classNameEl.textContent = res.class_info.nama_matkul;
      }
      sessions = res.sessions || [];
      renderSessions();
    } catch (err) {
      alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat sesi pertemuan.')}</div>`;
      tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Gagal memuat data pertemuan.</td></tr>`;
    }
  }

  addForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btn-save-session');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...`;

    try {
      await Api.post(`/dosen/classes/${jadwalId}/sessions`, {
        pertemuan_ke: inPertemuanKe.value,
        judul_pertemuan: inJudul.value,
        tanggal_pertemuan: inTanggal.value,
      });

      modal.hide();
      inJudul.value = '';
      Ui.alert(alertArea, 'Sesi pertemuan baru berhasil dibuat.', 'success');
      await loadData();
    } catch (err) {
      alert(err.message || 'Gagal menambahkan pertemuan.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  });

  await loadData();
});
