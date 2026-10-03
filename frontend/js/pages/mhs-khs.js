document.addEventListener('DOMContentLoaded', async () => {
  const me = await Layout.init({
    title: 'Kartu Hasil Studi (KHS)',
    active: 'khs',
    expectedRole: 'mahasiswa',
  });
  if (!me) return;

  const alertArea = document.getElementById('alert-area');
  const badgeSemester = document.getElementById('badge-semester');
  const printSemester = document.getElementById('print-semester');
  const bioNim = document.getElementById('bio-nim');
  const bioNama = document.getElementById('bio-nama');
  const bioJurusan = document.getElementById('bio-jurusan');
  const bioSemester = document.getElementById('bio-semester');
  const statIps = document.getElementById('stat-ips');
  const statIpk = document.getElementById('stat-ipk');
  const statSksSem = document.getElementById('stat-sks-sem');
  const statSksKum = document.getElementById('stat-sks-kum');
  const countMatkul = document.getElementById('count-matkul');
  const tbody = document.getElementById('khs-tbody');
  const footSks = document.getElementById('foot-sks');
  const footBobot = document.getElementById('foot-bobot');
  const footIps = document.getElementById('foot-ips');
  const btnPrint = document.getElementById('btn-print');
  const printDate = document.getElementById('print-date');

  function getMutu(grade) {
    switch (grade) {
      case 'A': return 4.0;
      case 'B': return 3.0;
      case 'C': return 2.0;
      case 'D': return 1.0;
      default: return 0.0;
    }
  }

  function getGradeBadgeClass(grade) {
    switch (grade) {
      case 'A': return 'bg-success';
      case 'B': return 'bg-primary';
      case 'C': return 'bg-warning text-dark';
      case 'D': return 'bg-secondary';
      case 'E': return 'bg-danger';
      default: return 'bg-light text-muted border';
    }
  }

  btnPrint.addEventListener('click', () => {
    window.print();
  });

  // Format tanggal hari ini untuk cetak
  const today = new Date();
  const options = { day: 'numeric', month: 'long', year: 'numeric' };
  printDate.textContent = `Jakarta, ${today.toLocaleDateString('id-ID', options)}`;

  try {
    const data = await Api.get('/mahasiswa/khs');

    if (data.mahasiswa) {
      bioNim.textContent = data.mahasiswa.nim || '-';
      bioNama.textContent = data.mahasiswa.nama_lengkap || '-';
      bioJurusan.textContent = data.mahasiswa.jurusan || '-';
    }

    const semLabel = `Semester ${data.semester || 'Ganjil'} - Tahun Akademik ${data.tahun_ajaran || '2025/2026'}`;
    badgeSemester.textContent = semLabel;
    printSemester.textContent = semLabel;
    bioSemester.textContent = semLabel;

    statIps.textContent = Number(data.ips || 0).toFixed(2);
    statIpk.textContent = Number(data.ipk || 0).toFixed(2);
    statSksSem.textContent = `${data.total_sks_semester || 0} SKS Semester`;
    statSksKum.textContent = `${data.total_sks_kumulatif || 0} SKS Kumulatif`;

    footSks.textContent = data.total_sks_semester || 0;
    footBobot.textContent = Number(data.total_bobot_semester || 0).toFixed(2);
    footIps.textContent = Number(data.ips || 0).toFixed(2);

    const list = data.khs_semester || [];
    countMatkul.textContent = `${list.length} Mata Kuliah`;

    if (list.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4 text-muted">
            <i class="bi bi-file-earmark-x fs-3 d-block mb-1"></i>
            Belum ada nilai yang tersedia untuk semester aktif ini.
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = list.map((item, idx) => {
      const grade = item.grade_huruf || '-';
      const mutu = grade !== '-' ? getMutu(grade) : 0;
      const sks = Number(item.sks || 0);
      const bobotKali = (sks * mutu).toFixed(2);
      const badgeCls = getGradeBadgeClass(grade);

      return `
        <tr>
          <td class="text-center text-muted fw-bold">${idx + 1}</td>
          <td><code class="fw-semibold text-dark">${Ui.esc(item.kode_matkul)}</code></td>
          <td class="fw-semibold text-dark">${Ui.esc(item.nama_matkul)}</td>
          <td class="text-center">${sks}</td>
          <td class="text-center fw-semibold">${item.nilai_akhir != null ? Number(item.nilai_akhir).toFixed(2) : '-'}</td>
          <td class="text-center">
            <span class="badge ${badgeCls} px-2 py-1">${grade}</span>
          </td>
          <td class="text-center text-muted">${mutu.toFixed(2)}</td>
          <td class="text-center fw-bold text-dark">${bobotKali}</td>
        </tr>
      `;
    }).join('');
  } catch (err) {
    alertArea.innerHTML = `<div class="alert alert-danger">${Ui.esc(err.message || 'Gagal memuat kartu hasil studi.')}</div>`;
    tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">Gagal memuat data nilai KHS.</td></tr>`;
  }
});
