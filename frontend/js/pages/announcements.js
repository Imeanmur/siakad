(async () => {
  await Layout.init({ title: 'Manajemen Pengumuman', active: 'announcements' });

  const body = Ui.$('#announcements-body');
  const form = Ui.$('#announcement-form');
  const submit = Ui.$('#announcement-submit');
  let items = [];
  let deleteId = null;

  function render() {
    if (!items.length) {
      body.innerHTML = Ui.emptyRow(5, 'Belum ada pengumuman.');
      return;
    }
    body.innerHTML = items.map((a, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(a.judul)}</td>
        <td><span class="badge bg-secondary">${Ui.esc(Ui.cap(a.target_role))}</span></td>
        <td>${Ui.dateTime(a.created_at)}</td>
        <td>
          <button type="button" class="btn btn-sm btn-warning me-1" data-action="edit" data-id="${a.id}"><i class="bi bi-pencil-square"></i> Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${a.id}"><i class="bi bi-trash"></i> Hapus</button>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      items = await Api.get('/admin/announcements');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(5, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  function openForm(a) {
    form.reset();
    Ui.$('#a-id').value = a ? a.id : '';
    Ui.$('#formModalLabel').textContent = a ? 'Edit Pengumuman' : 'Buat Pengumuman';
    if (a) {
      Ui.$('#a-judul').value = a.judul;
      Ui.$('#a-isi').value = a.isi;
      Ui.$('#a-target').value = a.target_role;
    }
    Ui.modal('formModal').show();
  }

  Ui.$('#btn-add').addEventListener('click', () => openForm(null));

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const a = items.find((x) => String(x.id) === btn.dataset.id);
    if (!a) return;
    if (btn.dataset.action === 'edit') {
      openForm(a);
    } else {
      deleteId = a.id;
      Ui.$('#delete-name').textContent = a.judul;
      Ui.modal('deleteModal').show();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const data = Ui.formData(form);
    const payload = { judul: data.judul, isi: data.isi, target_role: data.target_role };

    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = data.id ? await Api.put(`/admin/announcements/${data.id}`, payload) : await Api.post('/admin/announcements', payload);
      Ui.modal('formModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('formModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/announcements/${deleteId}`);
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
