(async () => {
  await Layout.init({ title: 'Manajemen Jadwal Kuliah', active: 'schedules' });

  const body = Ui.$('#schedules-body');
  const form = Ui.$('#schedule-form');
  const submit = Ui.$('#schedule-submit');
  let schedules = [];
  let deleteId = null;

  function render() {
    if (!schedules.length) {
      body.innerHTML = Ui.emptyRow(6, 'Belum ada jadwal kuliah.');
      return;
    }
    body.innerHTML = schedules.map((j, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(j.kode_matkul)} - ${Ui.esc(j.nama_matkul)}</td>
        <td>${Ui.esc(j.nama_dosen)}</td>
        <td>${Ui.esc(j.hari)}</td>
        <td>${Ui.esc(Ui.hm(j.jam_mulai))} - ${Ui.esc(Ui.hm(j.jam_selesai))}</td>
        <td class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-warning" data-action="edit" data-id="${j.id}"><i class="bi bi-pencil-square"></i> Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${j.id}"><i class="bi bi-trash"></i> Hapus</button>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      const data = await Api.get('/admin/schedules');
      schedules = data.schedules;
      Ui.$('#j-matkul').innerHTML = '<option value="" disabled selected>-- Pilih Mata Kuliah --</option>' +
        data.courses.map((c) => `<option value="${c.id}">${Ui.esc(c.kode_matkul)} - ${Ui.esc(c.nama_matkul)}</option>`).join('');
      Ui.$('#j-dosen').innerHTML = '<option value="" disabled selected>-- Pilih Dosen --</option>' +
        data.lecturers.map((d) => `<option value="${d.id}">${Ui.esc(d.nama_lengkap)}</option>`).join('');
      Ui.$('#j-hari').innerHTML = '<option value="" disabled selected>-- Pilih Hari --</option>' +
        data.days.map((h) => `<option value="${Ui.esc(h)}">${Ui.esc(h)}</option>`).join('');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(6, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  function openForm(j) {
    form.reset();
    Ui.$('#j-id').value = j ? j.id : '';
    Ui.$('#scheduleModalLabel').textContent = j ? 'Form Edit Jadwal' : 'Form Buat Jadwal';
    if (j) {
      Ui.$('#j-matkul').value = String(j.matkul_id);
      Ui.$('#j-dosen').value = String(j.dosen_id);
      Ui.$('#j-hari').value = j.hari;
      Ui.$('#j-mulai').value = Ui.hm(j.jam_mulai);
      Ui.$('#j-selesai').value = Ui.hm(j.jam_selesai);
    }
    Ui.modal('scheduleModal').show();
  }

  Ui.$('#btn-add').addEventListener('click', () => openForm(null));

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const j = schedules.find((x) => String(x.id) === btn.dataset.id);
    if (!j) return;
    if (btn.dataset.action === 'edit') {
      openForm(j);
    } else {
      deleteId = j.id;
      Ui.$('#delete-name').textContent = j.nama_matkul;
      Ui.modal('deleteModal').show();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const data = Ui.formData(form);
    const payload = {
      matkul_id: Number(data.matkul_id),
      dosen_id: Number(data.dosen_id),
      hari: data.hari,
      jam_mulai: data.jam_mulai,
      jam_selesai: data.jam_selesai,
    };
    if (payload.jam_mulai >= payload.jam_selesai) {
      Ui.alert('danger', 'Jam mulai harus lebih awal dari jam selesai.');
      return;
    }

    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = data.id ? await Api.put(`/admin/schedules/${data.id}`, payload) : await Api.post('/admin/schedules', payload);
      Ui.modal('scheduleModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('scheduleModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/schedules/${deleteId}`);
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(e.currentTarget, false);
      Ui.modal('deleteModal').hide();
      deleteId = null;
    }
  });

  await load();
})();
