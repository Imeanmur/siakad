document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Kartu Rencana Studi (KRS)',
    active: 'krs',
    expectedRole: 'mahasiswa',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const semesterBadge = document.getElementById('krs-semester');
  const sksTotalEl = document.getElementById('krs-sks-total');
  const sksMaxEl = document.getElementById('krs-sks-max');
  const sksProgressEl = document.getElementById('krs-progress');
  const sksRemainingEl = document.getElementById('krs-sks-remaining');
  const countSelectedEl = document.getElementById('count-selected');
  const selectedTbody = document.getElementById('selected-tbody');
  const footTotalSks = document.getElementById('foot-total-sks');
  const availableTbody = document.getElementById('available-tbody');
  const searchInput = document.getElementById('search-available');

  let krsData = null;
  let availableList = [];

  function renderSelected() {
    const list = krsData.selected_courses || [];
    countSelectedEl.textContent = `${list.length} Mata Kuliah`;
    footTotalSks.textContent = krsData.total_sks || 0;

    if (list.length === 0) {
      selectedTbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
            Belum ada mata kuliah yang diambil. Silakan pilih dari daftar jadwal tersedia di bawah.
          </td>
        </tr>
      `;
      return;
    }

    selectedTbody.innerHTML = list.map((c, idx) => {
      const jam = `${(c.jam_mulai || '').slice(0, 5)} - ${(c.jam_selesai || '').slice(0, 5)}`;
      return `
        <tr>
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(c.kode_matkul)}</code></td>
          <td class="fw-bold text-dark">${Ui.esc(c.nama_matkul)}</td>
          <td class="text-center"><span class="badge bg-primary-subtle text-primary">${c.sks} SKS</span></td>
          <td class="text-secondary small">${Ui.esc(c.nama_dosen)}</td>
          <td class="text-secondary small">
            <i class="bi bi-clock me-1 text-primary"></i>${Ui.esc(c.hari)}, ${Ui.esc(jam)}
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm btn-delete-krs" data-id="${c.id}" title="Hapus dari KRS">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');

    selectedTbody.querySelectorAll('.btn-delete-krs').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        if (!confirm('Apakah Anda yakin ingin menghapus mata kuliah ini dari KRS?')) return;

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;

        try {
          const res = await Api.del(`/mahasiswa/krs/${id}`);
          Ui.alert(alertArea, res.message || 'Mata kuliah berhasil dihapus dari KRS.', 'success');
          await loadKrs();
        } catch (err) {
          Ui.alert(alertArea, err.message || 'Gagal menghapus mata kuliah.', 'danger');
          btn.disabled = false;
          btn.innerHTML = `<i class="bi bi-trash"></i>`;
        }
      });
    });
  }

  function renderAvailable(list) {
    if (!list || list.length === 0) {
      availableTbody.innerHTML = `
        <tr>
          <td colspan="7" class="text-center py-4 text-muted">
            Tidak ada jadwal kuliah yang cocok atau semua matkul telah diambil.
          </td>
        </tr>
      `;
      return;
    }

    const currentTotal = krsData.total_sks || 0;
    const maxSks = krsData.max_sks || 24;

    availableTbody.innerHTML = list.map((c, idx) => {
      const jam = `${(c.jam_mulai || '').slice(0, 5)} - ${(c.jam_selesai || '').slice(0, 5)}`;
      const willExceed = currentTotal + Number(c.sks) > maxSks;

      return `
        <tr>
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(c.kode_matkul)}</code></td>
          <td class="fw-bold text-dark">${Ui.esc(c.nama_matkul)}</td>
          <td class="text-center"><span class="badge bg-secondary-subtle text-secondary">${c.sks} SKS</span></td>
          <td class="text-secondary small">${Ui.esc(c.nama_dosen)}</td>
          <td class="text-secondary small">
            <i class="bi bi-clock me-1 text-success"></i>${Ui.esc(c.hari)}, ${Ui.esc(jam)}
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-primary btn-sm btn-add-krs fw-semibold" 
              data-id="${c.id}" ${willExceed ? 'disabled title="Melebihi batas maksimal 24 SKS"' : ''}>
              <i class="bi bi-plus-lg me-1"></i>Ambil
            </button>
          </td>
        </tr>
      `;
    }).join('');

    availableTbody.querySelectorAll('.btn-add-krs').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const id = btn.dataset.id;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;

        try {
          const res = await Api.post('/mahasiswa/krs/add', { jadwal_id: id });
          Ui.alert(alertArea, res.message || 'Mata kuliah berhasil ditambahkan ke KRS!', 'success');
          await loadKrs();
        } catch (err) {
          Ui.alert(alertArea, err.message || 'Gagal menambahkan mata kuliah.', 'danger');
          btn.disabled = false;
          btn.innerHTML = `<i class="bi bi-plus-lg me-1"></i>Ambil`;
        }
      });
    });
  }

  async function loadKrs() {
    try {
      krsData = await Api.get('/mahasiswa/krs');

      semesterBadge.textContent = `Tahun Ajaran ${krsData.tahun_ajaran} ${krsData.semester}`;
      sksTotalEl.textContent = krsData.total_sks || 0;
      sksMaxEl.textContent = `/ ${krsData.max_sks || 24} SKS`;

      const remaining = Math.max(0, (krsData.max_sks || 24) - (krsData.total_sks || 0));
      sksRemainingEl.textContent = `Sisa kuota: ${remaining} SKS`;

      const pct = Math.min(100, Math.round(((krsData.total_sks || 0) / (krsData.max_sks || 24)) * 100));
      sksProgressEl.style.width = `${pct}%`;
      if (pct >= 100) {
        sksProgressEl.className = 'progress-bar bg-danger';
      } else if (pct >= 80) {
        sksProgressEl.className = 'progress-bar bg-warning';
      } else {
        sksProgressEl.className = 'progress-bar bg-primary';
      }

      availableList = krsData.available_schedules || [];
      renderSelected();
      renderAvailable(availableList);
    } catch (err) {
      alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat data KRS.')}</div>`;
    }
  }

  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      const filtered = availableList.filter((c) =>
        (c.nama_matkul || '').toLowerCase().includes(q) ||
        (c.kode_matkul || '').toLowerCase().includes(q) ||
        (c.nama_dosen || '').toLowerCase().includes(q) ||
        (c.hari || '').toLowerCase().includes(q)
      );
      renderAvailable(filtered);
    });
  }

  await loadKrs();
});
