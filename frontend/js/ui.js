/** Helper UI bersama. Semua data dari server HARUS lewat Ui.esc() sebelum dimasukkan ke innerHTML. */
const Ui = (() => {
  const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ESC[c]);

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /** Tampilkan alert Bootstrap di #alert-area (hilang otomatis untuk sukses). */
  function alert(type, message) {
    const area = $('#alert-area');
    if (!area) return;
    const el = document.createElement('div');
    el.className = `alert alert-${type} alert-dismissible fade show`;
    el.setAttribute('role', 'alert');
    el.innerHTML = '<span class="msg"></span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    el.querySelector('.msg').textContent = message;
    area.prepend(el);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (type === 'success') setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 5000);
  }

  /** Nonaktifkan tombol saat proses berjalan. */
  function busy(btn, on, label) {
    if (!btn) return;
    if (on) {
      btn.dataset.label = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>${esc(label || 'Memproses...')}`;
    } else {
      btn.disabled = false;
      if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
    }
  }

  const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

  /** Kumpulkan nilai form menjadi objek {name: value}. */
  const formData = (form) => Object.fromEntries(new FormData(form).entries());

  const emptyRow = (cols, text) => `<tr><td colspan="${cols}" class="text-center text-muted py-4">${esc(text)}</td></tr>`;

  const hm = (t) => String(t || '').slice(0, 5);

  const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  /** "2025-09-28 14:33:25" -> "28 Sep 2025, 14:33" */
  function dateTime(s) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(s || ''));
    return m ? `${Number(m[3])} ${MONTHS[Number(m[2]) - 1]} ${m[1]}, ${m[4]}:${m[5]}` : esc(s || '');
  }

  const cap = (s) => String(s || '').charAt(0).toUpperCase() + String(s || '').slice(1);

  return { esc, $, $$, alert, busy, modal, formData, emptyRow, hm, dateTime, cap };
})();
