document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Presensi Perkuliahan',
    active: 'absensi',
    expectedRole: 'mahasiswa',
  });
  if (!me) return;

  const urlParams = new URLSearchParams(window.location.search);
  const jadwalId = urlParams.get('jadwal_id');

  const alertArea = document.getElementById('alert-area');
  const overviewSection = document.getElementById('overview-section');
  const detailSection = document.getElementById('detail-section');

  function getStatusBadge(status) {
    switch (status) {
      case 'Hadir':
        return '<span class="badge bg-success px-3 py-1">Hadir</span>';
      case 'Izin':
        return '<span class="badge bg-primary px-3 py-1">Izin</span>';
      case 'Sakit':
        return '<span class="badge bg-warning text-dark px-3 py-1">Sakit</span>';
      case 'Alpa':
      default:
        return '<span class="badge bg-danger px-3 py-1">Alpa</span>';
    }
  }

  function getPctBadge(pct) {
    if (pct >= 80) return `<span class="badge bg-success fs-6">${pct}%</span>`;
    if (pct >= 75) return `<span class="badge bg-warning text-dark fs-6">${pct}%</span>`;
    return `<span class="badge bg-danger fs-6">${pct}%</span>`;
  }

  // --- 1. OVERVIEW MODE ---
  if (!jadwalId) {
    overviewSection.classList.remove('d-none');
    detailSection.classList.add('d-none');

    const overviewCount = document.getElementById('overview-count');
    const overviewTbody = document.getElementById('overview-tbody');

    try {
      const data = await Api.get('/mahasiswa/attendance');
      const classes = data.classes || [];
      overviewCount.textContent = `${classes.length} Kelas`;

      if (classes.length === 0) {
        overviewTbody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="bi bi-inbox fs-2 d-block mb-2"></i>
              Anda belum mengambil mata kuliah semester ini atau jadwal belum memiliki sesi absensi.
            </td>
          </tr>
        `;
        return;
      }

      overviewTbody.innerHTML = classes.map((c, idx) => `
        <tr>
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(c.kode_matkul)}</code></td>
          <td class="fw-bold text-dark">${Ui.esc(c.nama_matkul)}</td>
          <td class="text-secondary small">${Ui.esc(c.nama_dosen)}</td>
          <td class="text-center fw-semibold">${c.total_pertemuan || 0} Sesi</td>
          <td class="text-center fw-semibold text-success">${c.hadir_count || 0}</td>
          <td class="text-center">${getPctBadge(c.persentase || 0)}</td>
          <td class="text-center">
            <a href="absensi.html?jadwal_id=${c.jadwal_id}" class="btn btn-outline-primary btn-sm fw-semibold">
              <i class="bi bi-eye me-1"></i> Rincian
            </a>
          </td>
        </tr>
      `).join('');
    } catch (err) {
      alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat rekap presensi.')}</div>`;
      overviewTbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Gagal memuat data presensi.</td></tr>`;
    }
    return;
  }

  // --- 2. DETAIL MODE ---
  overviewSection.classList.add('d-none');
  detailSection.classList.remove('d-none');

  const detailCode = document.getElementById('detail-code');
  const detailTitle = document.getElementById('detail-title');
  const statHadir = document.getElementById('stat-hadir');
  const statIzin = document.getElementById('stat-izin');
  const statSakit = document.getElementById('stat-sakit');
  const statAlpa = document.getElementById('stat-alpa');
  const statPersen = document.getElementById('stat-persen');
  const statTotalSesi = document.getElementById('stat-total-sesi');
  const detailTbody = document.getElementById('detail-tbody');

  try {
    const res = await Api.get(`/mahasiswa/attendance?jadwal_id=${jadwalId}`);

    if (res.class_info) {
      detailCode.textContent = res.class_info.kode_matkul;
      detailTitle.textContent = res.class_info.nama_matkul;
    }

    if (res.summary) {
      statHadir.textContent = res.summary.Hadir || 0;
      statIzin.textContent = res.summary.Izin || 0;
      statSakit.textContent = res.summary.Sakit || 0;
      statAlpa.textContent = res.summary.Alpa || 0;
    }

    statPersen.textContent = `${res.persentase_hadir || 0}%`;
    statTotalSesi.textContent = `${res.total_pertemuan || 0} Sesi Pertemuan`;

    const meetings = res.meetings || [];
    if (meetings.length === 0) {
      detailTbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
            Belum ada sesi pertemuan yang dibuat oleh dosen pengampu kelas ini.
          </td>
        </tr>
      `;
      return;
    }

    detailTbody.innerHTML = meetings.map((m) => `
      <tr>
        <td>
          <span class="badge bg-light text-dark border fw-bold px-2 py-1">
            Pertemuan Ke-${m.pertemuan_ke}
          </span>
        </td>
        <td class="fw-semibold text-dark">${Ui.esc(m.judul_pertemuan)}</td>
        <td class="text-secondary small">
          <i class="bi bi-calendar-event me-1 text-primary"></i> ${Ui.esc(Ui.formatDate(m.tanggal_pertemuan))}
        </td>
        <td class="text-center">${getStatusBadge(m.status)}</td>
      </tr>
    `).join('');
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat rincian presensi kelas.')}</div>`;
    detailTbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Gagal memuat rincian pertemuan.</td></tr>`;
  }
});
