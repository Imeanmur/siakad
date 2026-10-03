document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Detail Kelas & Input Nilai',
    active: 'input_nilai',
    expectedRole: 'dosen',
  });
  if (!me) return;

  const urlParams = new URLSearchParams(window.location.search);
  const jadwalId = urlParams.get('jadwal_id');

  if (!jadwalId) {
    window.location.replace('input-nilai.html');
    return;
  }

  const alertArea = document.getElementById('alert-area');
  const classCodeEl = document.getElementById('class-code');
  const classSksEl = document.getElementById('class-sks');
  const classNameEl = document.getElementById('class-name');
  const classScheduleEl = document.getElementById('class-schedule');
  const studentCountEl = document.getElementById('student-count');
  const tbody = document.getElementById('student-tbody');
  const saveAllBtn = document.getElementById('save-all-btn');

  let students = [];

  function calculateGrade(tugas, uts, uas) {
    const t = Math.min(100, Math.max(0, parseFloat(tugas) || 0));
    const m = Math.min(100, Math.max(0, parseFloat(uts) || 0));
    const f = Math.min(100, Math.max(0, parseFloat(uas) || 0));
    const akhir = Number(((t * 0.2) + (m * 0.3) + (f * 0.5)).toFixed(2));

    let letter = 'E';
    let badgeClass = 'bg-danger';

    if (akhir >= 85) { letter = 'A'; badgeClass = 'bg-success'; }
    else if (akhir >= 75) { letter = 'B'; badgeClass = 'bg-primary'; }
    else if (akhir >= 65) { letter = 'C'; badgeClass = 'bg-warning text-dark'; }
    else if (akhir >= 50) { letter = 'D'; badgeClass = 'bg-secondary'; }

    return { akhir, letter, badgeClass };
  }

  function renderStudents() {
    studentCountEl.textContent = `${students.length} Mahasiswa`;

    if (!students || students.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="9" class="text-center py-5 text-muted">
            <i class="bi bi-people fs-2 d-block mb-2"></i>
            Belum ada mahasiswa yang mengambil mata kuliah di kelas ini.
          </td>
        </tr>
      `;
      saveAllBtn.disabled = true;
      return;
    }

    tbody.innerHTML = students.map((s, idx) => {
      const calc = calculateGrade(s.nilai_tugas, s.nilai_uts, s.nilai_uas);
      return `
        <tr id="row-${s.krs_detail_id}">
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(s.nim)}</code></td>
          <td class="fw-semibold">${Ui.esc(s.nama_lengkap)}</td>
          <td class="text-center">
            <input type="number" min="0" max="100" step="any"
              class="form-control form-control-sm input-nilai-num mx-auto input-tugas"
              data-id="${s.krs_detail_id}" value="${s.nilai_tugas ?? 0}">
          </td>
          <td class="text-center">
            <input type="number" min="0" max="100" step="any"
              class="form-control form-control-sm input-nilai-num mx-auto input-uts"
              data-id="${s.krs_detail_id}" value="${s.nilai_uts ?? 0}">
          </td>
          <td class="text-center">
            <input type="number" min="0" max="100" step="any"
              class="form-control form-control-sm input-nilai-num mx-auto input-uas"
              data-id="${s.krs_detail_id}" value="${s.nilai_uas ?? 0}">
          </td>
          <td class="text-center fw-bold fs-6 col-akhir">
            ${calc.akhir.toFixed(2)}
          </td>
          <td class="text-center col-grade">
            <span class="badge ${calc.badgeClass} grade-badge">${calc.letter}</span>
          </td>
          <td class="text-center">
            <button type="button" class="btn btn-outline-primary btn-sm btn-save-row fw-semibold"
              data-id="${s.krs_detail_id}">
              <i class="bi bi-save me-1"></i>Simpan
            </button>
          </td>
        </tr>
      `;
    }).join('');

    attachRowEvents();
  }

  function attachRowEvents() {
    students.forEach((s) => {
      const row = document.getElementById(`row-${s.krs_detail_id}`);
      if (!row) return;

      const inTugas = row.querySelector('.input-tugas');
      const inUts = row.querySelector('.input-uts');
      const inUas = row.querySelector('.input-uas');
      const colAkhir = row.querySelector('.col-akhir');
      const colGrade = row.querySelector('.col-grade');
      const saveBtn = row.querySelector('.btn-save-row');

      const updateRowCalc = () => {
        const calc = calculateGrade(inTugas.value, inUts.value, inUas.value);
        colAkhir.textContent = calc.akhir.toFixed(2);
        colGrade.innerHTML = `<span class="badge ${calc.badgeClass} grade-badge">${calc.letter}</span>`;
        row.classList.add('table-warning');
      };

      inTugas.addEventListener('input', updateRowCalc);
      inUts.addEventListener('input', updateRowCalc);
      inUas.addEventListener('input', updateRowCalc);

      saveBtn.addEventListener('click', async () => {
        await saveStudentGrade(s.krs_detail_id, inTugas.value, inUts.value, inUas.value, saveBtn, row);
      });
    });
  }

  async function saveStudentGrade(krsDetailId, tugas, uts, uas, btn, row) {
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span>`;

    try {
      const res = await Api.post(`/dosen/classes/${jadwalId}/grades`, {
        krs_detail_id: krsDetailId,
        nilai_tugas: tugas,
        nilai_uts: uts,
        nilai_uas: uas,
      });

      row.classList.remove('table-warning');
      row.classList.add('table-success');
      setTimeout(() => row.classList.remove('table-success'), 1500);

      Ui.alert(alertArea, res.message || 'Nilai berhasil disimpan.', 'success');
    } catch (err) {
      Ui.alert(alertArea, err.message || 'Gagal menyimpan nilai.', 'danger');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalText;
    }
  }

  // Simpan semua nilai sekaligus
  saveAllBtn.addEventListener('click', async () => {
    if (students.length === 0) return;

    const originalHtml = saveAllBtn.innerHTML;
    saveAllBtn.disabled = true;
    saveAllBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...`;
    alertArea.innerHTML = '';

    let successCount = 0;
    let failCount = 0;

    for (const s of students) {
      const row = document.getElementById(`row-${s.krs_detail_id}`);
      if (!row) continue;

      const inTugas = row.querySelector('.input-tugas').value;
      const inUts = row.querySelector('.input-uts').value;
      const inUas = row.querySelector('.input-uas').value;

      try {
        await Api.post(`/dosen/classes/${jadwalId}/grades`, {
          krs_detail_id: s.krs_detail_id,
          nilai_tugas: inTugas,
          nilai_uts: inUts,
          nilai_uas: inUas,
        });
        row.classList.remove('table-warning');
        successCount++;
      } catch (e) {
        failCount++;
      }
    }

    saveAllBtn.disabled = false;
    saveAllBtn.innerHTML = originalHtml;

    if (failCount === 0) {
      Ui.alert(alertArea, `Semua (${successCount}) nilai mahasiswa berhasil disimpan!`, 'success');
    } else {
      Ui.alert(alertArea, `${successCount} nilai berhasil disimpan, ${failCount} gagal.`, 'warning');
    }
  });

  // Muat data awal kelas dan mahasiswa
  try {
    const res = await Api.get(`/dosen/classes/${jadwalId}/grades`);
    const c = res.class_info;
    if (c) {
      classCodeEl.textContent = c.kode_matkul;
      classSksEl.textContent = `${c.sks || 0} SKS`;
      classNameEl.textContent = c.nama_matkul;
      const jam = `${(c.jam_mulai || '').slice(0, 5)} - ${(c.jam_selesai || '').slice(0, 5)}`;
      classScheduleEl.innerHTML = `<i class="bi bi-calendar3 me-1 text-primary"></i> ${Ui.esc(c.hari)}, ${Ui.esc(jam)} WIB`;
    }
    students = res.students || [];
    renderStudents();
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat detail kelas.')}</div>`;
    tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">Gagal memuat data mahasiswa.</td></tr>`;
  }
});
