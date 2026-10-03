(async () => {
  await Layout.init({ title: 'Manajemen Mahasiswa', active: 'students' });

  const body = Ui.$('#students-body');
  const form = Ui.$('#student-form');
  const submit = Ui.$('#student-submit');
  let students = [];
  let deleteUserId = null;

  function render() {
    if (!students.length) {
      body.innerHTML = Ui.emptyRow(7, 'Belum ada data mahasiswa.');
      return;
    }
    body.innerHTML = students.map((s, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(s.nim)}</td>
        <td>${Ui.esc(s.nama_lengkap)}</td>
        <td>${Ui.esc(s.jurusan)}</td>
        <td>${Ui.esc(s.angkatan)}</td>
        <td>${Ui.esc(s.email)}</td>
        <td><button type="button" class="btn btn-sm btn-danger" data-user-id="${s.user_id}"><i class="bi bi-trash"></i> Hapus</button></td>
      </tr>`).join('');
  }

  async function load() {
    try {
      students = await Api.get('/admin/students');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(7, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  Ui.$('#btn-add').addEventListener('click', () => {
    form.reset();
    Ui.modal('studentModal').show();
  });

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-user-id]');
    if (!btn) return;
    const s = students.find((x) => String(x.user_id) === btn.dataset.userId);
    if (!s) return;
    deleteUserId = s.user_id;
    Ui.$('#delete-name').textContent = s.nama_lengkap;
    Ui.modal('deleteModal').show();
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = await Api.post('/admin/students', Ui.formData(form));
      Ui.modal('studentModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('studentModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteUserId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/students/${deleteUserId}`);
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
