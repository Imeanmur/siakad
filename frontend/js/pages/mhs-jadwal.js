document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Jadwal Perkuliahan',
    active: 'jadwal',
    expectedRole: 'mahasiswa',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const myCoursesCount = document.getElementById('my-courses-count');
  const myTbody = document.getElementById('my-schedule-tbody');
  const allTbody = document.getElementById('all-schedule-tbody');
  const searchInput = document.getElementById('search-all-schedule');

  let allSchedules = [];

  function formatTime(start, end) {
    return `${(start || '').slice(0, 5)} - ${(end || '').slice(0, 5)} WIB`;
  }

  function getDayBadge(day) {
    const map = {
      'Senin': 'bg-primary',
      'Selasa': 'bg-info text-dark',
      'Rabu': 'bg-success',
      'Kamis': 'bg-warning text-dark',
      'Jumat': 'bg-danger',
      'Sabtu': 'bg-secondary',
    };
    return `<span class="badge ${map[day] || 'bg-light text-dark'} px-2 py-1">${Ui.esc(day || '-')}</span>`;
  }

  function renderEnrolled(list) {
    myCoursesCount.textContent = `${list.length} Kelas`;

    if (!list || list.length === 0) {
      myTbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-5 text-muted">
            <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
            Anda belum mengambil mata kuliah semester ini. Silakan isi KRS terlebih dahulu.
            <div class="mt-2"><a href="krs.html" class="btn btn-outline-primary btn-sm">Buka Halaman KRS</a></div>
          </td>
        </tr>
      `;
      return;
    }

    myTbody.innerHTML = list.map((item, idx) => `
      <tr>
        <td class="text-center text-muted fw-bold">${idx + 1}</td>
        <td>${getDayBadge(item.hari)}</td>
        <td class="text-dark small"><i class="bi bi-clock me-1 text-primary"></i>${Ui.esc(formatTime(item.jam_mulai, item.jam_selesai))}</td>
        <td><code class="fw-semibold text-dark">${Ui.esc(item.kode_matkul)}</code></td>
        <td class="fw-bold text-dark">${Ui.esc(item.nama_matkul)}</td>
        <td class="text-center"><span class="badge bg-primary-subtle text-primary">${item.sks} SKS</span></td>
        <td class="text-secondary small">${Ui.esc(item.nama_dosen)}</td>
      </tr>
    `).join('');
  }

  function renderAll(list) {
    if (!list || list.length === 0) {
      allTbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            Tidak ada jadwal kuliah yang cocok dengan pencarian Anda.
          </td>
        </tr>
      `;
      return;
    }

    allTbody.innerHTML = list.map((item, idx) => `
      <tr>
        <td class="text-center text-muted fw-bold">${idx + 1}</td>
        <td>${getDayBadge(item.hari)}</td>
        <td class="text-dark small"><i class="bi bi-clock me-1 text-primary"></i>${Ui.esc(formatTime(item.jam_mulai, item.jam_selesai))}</td>
        <td><code class="fw-semibold text-dark">${Ui.esc(item.kode_matkul)}</code></td>
        <td class="fw-bold text-dark">${Ui.esc(item.nama_matkul)}</td>
        <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">${item.sks} SKS</span></td>
        <td class="text-secondary small">${Ui.esc(item.nama_dosen)}</td>
      </tr>
    `).join('');
  }

  try {
    const data = await Api.get('/mahasiswa/schedules');
    renderEnrolled(data.enrolled_schedules || []);
    allSchedules = data.all_schedules || [];
    renderAll(allSchedules);
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat jadwal perkuliahan.')}</div>`;
  }

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      const filtered = allSchedules.filter((item) =>
        (item.nama_matkul || '').toLowerCase().includes(q) ||
        (item.kode_matkul || '').toLowerCase().includes(q) ||
        (item.nama_dosen || '').toLowerCase().includes(q) ||
        (item.hari || '').toLowerCase().includes(q)
      );
      renderAll(filtered);
    });
  }
});
