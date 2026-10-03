(async () => {
  await Layout.init({ title: 'Manajemen Dosen', active: 'lecturers' });

  const body = Ui.$('#lecturers-body');
  const form = Ui.$('#lecturer-form');
  const submit = Ui.$('#lecturer-submit');
  let lecturers = [];
  let deleteUserId = null;

  function render() {
    if (!lecturers.length) {
      body.innerHTML = Ui.emptyRow(5, 'Belum ada data dosen.');
      return;
    }
    body.innerHTML = lecturers.map((l, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(l.nidn)}</td>
        <td>${Ui.esc(l.nama_lengkap)}</td>
        <td>${Ui.esc(l.email)}</td>
        <td>
          <button type="button" class="btn btn-sm btn-warning me-1" data-action="edit" data-user-id="${l.user_id}"><i class="bi bi-pencil-square"></i> Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-user-id="${l.user_id}"><i class="bi bi-trash"></i> Hapus</button>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      lecturers = await Api.get('/admin/lecturers');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(5, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  function openForm(l) {
    form.reset();
    Ui.$('#l-user-id').value = l ? l.user_id : '';
    Ui.$('#lecturerModalLabel').textContent = l ? 'Form Edit Dosen' : 'Form Tambah Dosen';
    Ui.$('#l-password').required = !l;
    Ui.$('#l-password-label').textContent = l ? 'Password Baru' : 'Password';
    Ui.$('#l-password-hint').textContent = l ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.';
    if (l) {
      Ui.$('#l-nama').value = l.nama_lengkap;
      Ui.$('#l-nidn').value = l.nidn;
      Ui.$('#l-email').value = l.email;
    }
    Ui.modal('lecturerModal').show();
  }

  Ui.$('#btn-add').addEventListener('click', () => openForm(null));

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const l = lecturers.find((x) => String(x.user_id) === btn.dataset.userId);
    if (!l) return;
    if (btn.dataset.action === 'edit') {
      openForm(l);
    } else {
      deleteUserId = l.user_id;
      Ui.$('#delete-name').textContent = l.nama_lengkap;
      Ui.modal('deleteModal').show();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const data = Ui.formData(form);
    const payload = { nama_lengkap: data.nama_lengkap, nidn: data.nidn, email: data.email, password: data.password };

    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = data.user_id
        ? await Api.put(`/admin/lecturers/${data.user_id}`, payload)
        : await Api.post('/admin/lecturers', payload);
      Ui.modal('lecturerModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('lecturerModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteUserId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/lecturers/${deleteUserId}`);
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(e.currentTarget, false);
      Ui.modal('deleteModal').hide();
      deleteUserId = null;
    }
  });

  await load();
})();
