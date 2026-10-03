(async () => {
  await Layout.init({ title: 'Permohonan Buka Blokir', active: 'unblock' });

  const reqBody = Ui.$('#requests-body');
  const archBody = Ui.$('#archive-body');
  let requests = [];

  const STATUS_BADGE = {
    approved: '<span class="badge bg-success">Disetujui</span>',
    denied: '<span class="badge bg-danger">Ditolak</span>',
    pending: '<span class="badge bg-warning text-dark">Menunggu</span>',
  };
  const statusBadge = (s) => STATUS_BADGE[s] || `<span class="badge bg-secondary">${Ui.esc(s)}</span>`;

  // ---- konfirmasi umum ----
  let onConfirm = null;
  function confirmAction(text, okLabel, okClass, fn) {
    Ui.$('#confirm-text').textContent = text;
    const ok = Ui.$('#confirm-ok');
    ok.textContent = okLabel;
    ok.className = `btn ${okClass}`;
    onConfirm = fn;
    Ui.modal('confirmModal').show();
  }
  Ui.$('#confirm-ok').addEventListener('click', async (e) => {
    const fn = onConfirm;
    onConfirm = null;
    if (!fn) return;
    Ui.busy(e.currentTarget, true);
    try {
      await fn();
    } finally {
      Ui.busy(e.currentTarget, false);
      Ui.modal('confirmModal').hide();
    }
  });

  // ---- permohonan ----
  function renderRequests() {
    if (!requests.length) {
      reqBody.innerHTML = Ui.emptyRow(8, 'Belum ada permohonan.');
      return;
    }
    reqBody.innerHTML = requests.map((r, i) => `
      <tr>
        <td>${i + 1}</td>
        <td>${Ui.esc(r.created_at)}</td>
        <td>${Ui.esc(r.nama)}</td>
        <td>${Ui.esc(r.nim)}</td>
        <td>${Ui.esc(r.email)}</td>
        <td class="pre-wrap" style="max-width: 360px">${Ui.esc(r.alasan)}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="text-nowrap">${r.status === 'pending' ? `
          <button type="button" class="btn btn-sm btn-success" data-action="approve" data-id="${r.id}">Setujui &amp; Buka Blokir</button>
          <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-action="deny" data-id="${r.id}">Tolak</button>` : '<em>-</em>'}
        </td>
      </tr>`).join('');
  }

  async function loadRequests() {
    try {
      requests = await Api.get('/admin/unblock-requests');
      renderRequests();
    } catch (err) {
      reqBody.innerHTML = Ui.emptyRow(8, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  reqBody.addEventListener('click', async (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    Ui.busy(btn, true, '...');
    try {
      const res = await Api.post(`/admin/unblock-requests/${btn.dataset.id}/${btn.dataset.action}`);
      Ui.alert('success', res.message);
    } catch (err) {
      Ui.alert('danger', err.message);
    }
    await loadRequests();
  });

  Ui.$('#btn-export').addEventListener('click', async (e) => {
    Ui.busy(e.currentTarget, true, 'Mengunduh...');
    try {
      await Api.download('/admin/unblock-requests/export', 'unblock_requests.csv');
    } catch (err) {
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(e.currentTarget, false);
    }
  });

  Ui.$('#btn-archive').addEventListener('click', () => {
    confirmAction(
      'Arsipkan permohonan buka blokir yang sudah selesai (disetujui/ditolak)?',
      'Arsipkan', 'btn-warning',
      async () => {
        try {
          const res = await Api.post('/admin/unblock-requests/archive');
          Ui.alert('info', res.message);
          await Promise.all([loadRequests(), loadArchive()]);
        } catch (err) {
          Ui.alert('danger', err.message);
        }
      }
    );
  });

  // ---- arsip ----
  async function loadArchive() {
    try {
      const rows = await Api.get('/admin/unblock-requests/archive');
      archBody.innerHTML = rows.length ? rows.map((a) => `
        <tr>
          <td>${Ui.esc(a.id)}</td>
          <td>${Ui.esc(a.nama)}</td>
          <td>${Ui.esc(a.nim)}</td>
          <td class="pre-wrap">${Ui.esc(a.alasan)}</td>
          <td>${statusBadge(a.status)}</td>
          <td>${Ui.esc(a.archived_at)}</td>
          <td><button type="button" class="btn btn-success btn-sm" data-restore="${a.id}">Restore</button></td>
        </tr>`).join('') : Ui.emptyRow(7, 'Arsip kosong.');
    } catch (err) {
      archBody.innerHTML = Ui.emptyRow(7, 'Gagal memuat data.');
      Ui.alert('danger', err.message);
    }
  }

  archBody.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-restore]');
    if (!btn) return;
    const id = btn.dataset.restore;
    confirmAction('Kembalikan permohonan ini ke dashboard?', 'Restore', 'btn-success', async () => {
      try {
        const res = await Api.post(`/admin/unblock-requests/archive/${id}/restore`);
        Ui.alert('success', res.message);
        await Promise.all([loadRequests(), loadArchive()]);
      } catch (err) {
        Ui.alert('danger', err.message);
      }
    });
  });

  Ui.$('#btn-erase').addEventListener('click', () => {
    confirmAction(
      'Yakin ingin menghancurkan seluruh data arsip secara permanen? Tindakan ini tidak dapat dibatalkan!',
      'Ya, Hancurkan', 'btn-danger',
      async () => {
        try {
          const res = await Api.del('/admin/unblock-requests/archive');
          Ui.alert('success', res.message);
          await loadArchive();
        } catch (err) {
          Ui.alert('danger', err.message);
        }
      }
    );
  });

  // ---- retensi ----
  const retentionForm = Ui.$('#retention-form');
  function showRetention(r) {
    Ui.$('#audit-days').value = r.audit_days;
    Ui.$('#token-hours').value = r.unblock_token_hours;
    Ui.$('#txt-days').textContent = r.audit_days;
    Ui.$('#txt-hours').textContent = r.unblock_token_hours;
  }

  async function loadRetention() {
    try {
      showRetention(await Api.get('/admin/unblock-requests/retention'));
    } catch (err) {
      Ui.alert('danger', err.message);
    }
  }

  retentionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!retentionForm.checkValidity()) { retentionForm.reportValidity(); return; }
    const btn = Ui.$('#retention-save');
    const data = Ui.formData(retentionForm);
    Ui.busy(btn, true, 'Menyimpan...');
    try {
      const res = await Api.put('/admin/unblock-requests/retention', {
        audit_days: Number(data.audit_days),
        unblock_token_hours: Number(data.unblock_token_hours),
      });
      showRetention(res);
      Ui.alert('success', res.message);
    } catch (err) {
      Ui.alert('danger', err.message);
    } finally {
      Ui.busy(btn, false);
    }
  });

  Ui.$('#btn-run-retention').addEventListener('click', () => {
    confirmAction(
      'Hapus unblock token kedaluwarsa dan audit log yang melewati masa simpan? Data yang dihapus tidak dapat dikembalikan.',
      'Jalankan', 'btn-danger',
      async () => {
        try {
          const res = await Api.post('/admin/unblock-requests/retention/run');
          Ui.alert('success', `${res.message} Token dihapus: ${res.deleted_tokens}, audit log dihapus: ${res.deleted_audit_logs}.`);
        } catch (err) {
          Ui.alert('danger', err.message);
        }
      }
    );
  });

  await Promise.all([loadRequests(), loadArchive(), loadRetention()]);
})();
