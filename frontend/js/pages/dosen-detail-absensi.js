document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Input Presensi Mahasiswa',
    active: 'absensi',
    expectedRole: 'dosen',
  });
  if (!me) return;

  const urlParams = new URLSearchParams(window.location.search);
  const pertemuanId = urlParams.get('pertemuan_id');

  if (!pertemuanId) {
    window.location.replace('absensi.html');
    return;
  }

  const alertArea = document.getElementById('alert-area');
  const backBtn = document.getElementById('back-to-sessions');
  const courseCodeEl = document.getElementById('course-code');
  const sessionNumEl = document.getElementById('session-num');
  const courseTitleEl = document.getElementById('course-title');
  const sessionInfoEl = document.getElementById('session-info');
  const studentCountEl = document.getElementById('student-count');
  const tbody = document.getElementById('student-tbody');
  const allHadirBtn = document.getElementById('btn-all-hadir');
  const form = document.getElementById('attendanceForm');
  const saveTopBtn = document.getElementById('btn-save-attendance');
  const saveBottomBtn = document.getElementById('btn-save-bottom');

  let sessionData = null;
  let students = [];
  const statusOptions = ['Hadir', 'Izin', 'Sakit', 'Alpa'];

  function renderStudents() {
    studentCountEl.textContent = `${students.length} Mahasiswa`;

    if (!students || students.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="4" class="text-center py-5 text-muted">
            <i class="bi bi-people fs-2 d-block mb-2"></i>
            Tidak ada mahasiswa yang terdaftar di kelas ini.
          </td>
        </tr>
      `;
      saveTopBtn.disabled = true;
      saveBottomBtn.disabled = true;
      return;
    }

    tbody.innerHTML = students.map((s, idx) => {
      const currentStatus = s.status || 'Alpa';
      const radios = statusOptions.map((opt) => {
        const isChecked = currentStatus === opt ? 'checked' : '';
        let badgeColor = 'text-secondary';
        if (opt === 'Hadir') badgeColor = 'text-success fw-bold';
        else if (opt === 'Izin') badgeColor = 'text-primary';
        else if (opt === 'Sakit') badgeColor = 'text-warning';
        else if (opt === 'Alpa') badgeColor = 'text-danger';

        return `
          <div class="form-check form-check-inline">
            <input class="form-check-input status-radio" type="radio" 
              name="status_${s.mahasiswa_id}" 
              id="status_${s.mahasiswa_id}_${opt.toLowerCase()}" 
              value="${opt}" 
              data-mhs="${s.mahasiswa_id}" ${isChecked}>
            <label class="form-check-label ${badgeColor}" for="status_${s.mahasiswa_id}_${opt.toLowerCase()}">
              ${opt}
            </label>
          </div>
        `;
      }).join('');

      return `
        <tr>
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(s.nim)}</code></td>
          <td class="fw-semibold">${Ui.esc(s.nama_lengkap)}</td>
          <td>${radios}</td>
        </tr>
      `;
    }).join('');
  }

  // Tandai semua Hadir
  allHadirBtn.addEventListener('click', () => {
    students.forEach((s) => {
      const radio = document.getElementById(`status_${s.mahasiswa_id}_hadir`);
      if (radio) radio.checked = true;
    });
  });

  async function submitAttendance() {
    const statuses = {};
    const radios = document.querySelectorAll('.status-radio:checked');
    radios.forEach((r) => {
      const mhsId = r.dataset.mhs;
      if (mhsId) statuses[mhsId] = r.value;
    });

    const setBusy = (busy) => {
      saveTopBtn.disabled = busy;
      saveBottomBtn.disabled = busy;
      if (busy) {
        saveTopBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...`;
        saveBottomBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status"></span> Menyimpan...`;
      } else {
        saveTopBtn.innerHTML = `<i class="bi bi-save me-1"></i> Simpan Presensi`;
        saveBottomBtn.innerHTML = `<i class="bi bi-save me-1"></i> Simpan Presensi`;
      }
    };

    setBusy(true);
    try {
      const res = await Api.post(`/dosen/sessions/${pertemuanId}/attendance`, { statuses });
      Ui.alert(alertArea, res.message || 'Presensi berhasil disimpan.', 'success');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      Ui.alert(alertArea, err.message || 'Gagal menyimpan presensi.', 'danger');
    } finally {
      setBusy(false);
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitAttendance();
  });

  saveTopBtn.addEventListener('click', () => {
    submitAttendance();
  });

  // Muat data awal sesi dan absensi mahasiswa
  try {
    const res = await Api.get(`/dosen/sessions/${pertemuanId}`);
    sessionData = res.session;
    students = res.students || [];

    if (sessionData) {
      courseCodeEl.textContent = sessionData.kode_matkul;
      sessionNumEl.textContent = `Pertemuan Ke-${sessionData.pertemuan_ke}`;
      courseTitleEl.textContent = sessionData.nama_matkul;
      sessionInfoEl.innerHTML = `<strong>Materi:</strong> ${Ui.esc(sessionData.judul_pertemuan)} &bull; <i class="bi bi-calendar3 me-1"></i> ${Ui.esc(Ui.formatDate(sessionData.tanggal_pertemuan))}`;
      backBtn.href = `kelola-absensi.html?jadwal_id=${sessionData.jadwal_id}`;
    }

    renderStudents();
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat data presensi.')}</div>`;
    tbody.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Gagal memuat data mahasiswa.</td></tr>`;
  }
});
