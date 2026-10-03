(async () => {
  const me = await Layout.init({ title: 'Manajemen User', active: 'users' });

  const body = Ui.$('#users-body');
  const form = Ui.$('#user-form');
  const submit = Ui.$('#user-submit');
  let users = [];
  let roles = [];
  let deleteId = null;

  function render() {
    if (!users.length) {
      body.innerHTML = Ui.emptyRow(5, 'Belum ada data user.');
      return;
    }
    body.innerHTML = users.map((u, i) => `
      <tr>
        <th>${i + 1}</th>
        <td>${Ui.esc(u.username)}</td>
        <td>${Ui.esc(u.email)}</td>
        <td><span class="badge bg-info text-dark">${Ui.esc(Ui.cap(u.role_name))}</span></td>
        <td>
          <button type="button" class="btn btn-sm btn-warning me-1" data-action="edit" data-id="${u.id}"><i class="bi bi-pencil-square"></i> Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="${u.id}"><i class="bi bi-trash"></i> Hapus</button>
        </td>
      </tr>`).join('');
  }

  async function load() {
    try {
      [users, roles] = await Promise.all([Api.get('/admin/users'), Api.get('/admin/roles')]);
      Ui.$('#user-role').innerHTML = '<option value="" disabled selected>-- Pilih Role --</option>' +
        roles.map((r) => `<option value="${r.id}">${Ui.esc(Ui.cap(r.role_name))}</option>`).join('');
      render();
    } catch (err) {
      body.innerHTML = Ui.emptyRow(5, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  function openForm(user) {
    form.reset();
    Ui.$('#user-id').value = user ? user.id : '';
    Ui.$('#userModalLabel').textContent = user ? `Edit User: ${user.username}` : 'Form Tambah User Baru';
    Ui.$('#user-password').required = !user;
    Ui.$('#password-hint').textContent = user ? 'Kosongkan jika tidak ingin mengubah password.' : 'Minimal 8 karakter.';
    if (user) {
      Ui.$('#user-username').value = user.username;
      Ui.$('#user-email').value = user.email;
      Ui.$('#user-role').value = String(user.role_id);
    }
    Ui.modal('userModal').show();
  }

  Ui.$('#btn-add').addEventListener('click', () => openForm(null));

  body.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const user = users.find((u) => String(u.id) === btn.dataset.id);
    if (!user) return;
    if (btn.dataset.action === 'edit') {
      openForm(user);
    } else {
      deleteId = user.id;
      Ui.$('#delete-name').textContent = user.username;
      Ui.modal('deleteModal').show();
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const data = Ui.formData(form);
    const id = data.id;
    const payload = { username: data.username, email: data.email, password: data.password, role_id: Number(data.role_id) };

    Ui.busy(submit, true, 'Menyimpan...');
    try {
      const res = id ? await Api.put(`/admin/users/${id}`, payload) : await Api.post('/admin/users', payload);
      Ui.modal('userModal').hide();
      Ui.alert('success', res.message);
      await load();
    } catch (err) {
      Ui.modal('userModal').hide();
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(submit, false);
    }
  });

  Ui.$('#delete-confirm').addEventListener('click', async (e) => {
    if (deleteId === null) return;
    Ui.busy(e.currentTarget, true, 'Menghapus...');
    try {
      const res = await Api.del(`/admin/users/${deleteId}`);
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
