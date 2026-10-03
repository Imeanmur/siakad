(async () => {
  await Layout.init({ title: 'Manajemen Mata Kuliah', active: 'courses' });

  const body = Ui.$('#courses-body');
  const form = Ui.$('#course-form');
  const submit = Ui.$('#course-submit');
  let courses = [];
  let deleteId = null;

  function render() {
    if (!courses.length) {
      body.innerHTML = Ui.emptyRow(5, 'Belum ada data mata kuliah.');
      return;
    }
    body.innerHTML = courses.map((c, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(c.kode_matkul)}</td>
        <td>${Ui.esc(c.nama_matkul)}</td>
        <td>${Ui.esc(c.sks)}</td>
        <td>
          <button type="button" class="btn btn-sm btn-warning me-1" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil-square"></i> Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${c.id}"><i class="bi bi-trash"></i> Hapus</button>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      courses = await Api.get('/admin/courses');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(5, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  function openForm(c) {
    form.reset();
    Ui.$('#c-id').value = c ? c.id : '';
    Ui.$('#courseModalLabel').textContent = c ? 'Form Edit Mata Kuliah' : 'Form Tambah Mata Kuliah';
    if (c) {
      Ui.$('#c-kode').value = c.kode_matkul;
      Ui.$('#c-nama').value = c.nama_matkul;
      Ui.$('#c-sks').value = c.sks;
    }
    Ui.modal('courseModal').show();
  }

  Ui.$('#btn-add').addEventListener('click', () => openForm(null));

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const c = courses.find((x) => String(x.id) === btn.dataset.id);
    if (!c) return;
    if (btn.dataset.action === 'edit') {
      openForm(c);
    } else {
      deleteId = c.id;
      Ui.$('#delete-name').textContent = c.nama_matkul;
      Ui.modal('deleteModal').show();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const data = Ui.formData(form);
    const payload = { kode_matkul: data.kode_matkul, nama_matkul: data.nama_matkul, sks: data.sks };

    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = data.id ? await Api.put(`/admin/courses/${data.id}`, payload) : await Api.post('/admin/courses', payload);
      Ui.modal('courseModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('courseModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/courses/${deleteId}`);
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
