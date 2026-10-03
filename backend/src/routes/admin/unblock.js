const router = require('express').Router();
const { pool } = require('../../db');
const { HttpError, wrap, toId } = require('../../utils');
const { logEvent } = require('../../audit');

const DEFAULT_RETENTION = { audit_days: 180, unblock_token_hours: 24 };

// ---------------------------------------------------------------- permohonan

// GET /api/admin/unblock-requests
router.get('/', wrap(async (req, res) => {
  const [rows] = await pool.query(
    `SELECT ur.id, ur.user_id, ur.nama, ur.nim, ur.alasan, ur.status, ur.created_at, u.email
       FROM unblock_requests ur JOIN users u ON ur.user_id = u.id
      ORDER BY ur.created_at DESC, ur.id DESC`
  );
  res.json(rows);
}));

async function processRequest(req, res, action) {
  const id = toId(req.params.id);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [rows] = await conn.query("SELECT user_id FROM unblock_requests WHERE id = ? AND status = 'pending' FOR UPDATE", [id]);
    if (!rows.length) throw new HttpError(404, 'Permohonan tidak ditemukan atau sudah diproses.');

    if (action === 'approve') {
      await conn.query('UPDATE users SET is_active = 1, failed_attempts = 0, blocked_at = NULL WHERE id = ?', [rows[0].user_id]);
    }
    await conn.query(
      'UPDATE unblock_requests SET status = ?, processed_at = NOW(), processed_by = ? WHERE id = ?',
      [action === 'approve' ? 'approved' : 'denied', req.user.id, id]
    );
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
  await logEvent(req, req.user.id, `${action}_unblock`, { request_id: id }, 'unblock_request', id);
  res.json({ message: action === 'approve' ? 'Permohonan disetujui dan akun dibuka blokirnya.' : 'Permohonan ditolak.' });
}

router.post('/:id/approve', wrap((req, res) => processRequest(req, res, 'approve')));
router.post('/:id/deny', wrap((req, res) => processRequest(req, res, 'deny')));

// ----------------------------------------------------------------- export CSV

// Cegah CSV/formula injection saat dibuka di Excel.
function csvCell(v) {
  let s = v === null || v === undefined ? '' : String(v);
  if (/^[=+\-@\t\r]/.test(s)) s = `'${s}`;
  return `"${s.replace(/"/g, '""')}"`;
}

// GET /api/admin/unblock-requests/export
router.get('/export', wrap(async (req, res) => {
  const [rows] = await pool.query(
    `SELECT ur.*, u.email FROM unblock_requests ur JOIN users u ON ur.user_id = u.id
      ORDER BY ur.created_at DESC, ur.id DESC`
  );
  const header = ['ID', 'Tanggal', 'User ID', 'Nama', 'NIM', 'Email', 'Alasan', 'Status', 'Diproses Oleh', 'Diproses Pada'];
  const lines = [header.map(csvCell).join(',')];
  for (const r of rows) {
    lines.push([r.id, r.created_at, r.user_id, r.nama, r.nim, r.email, r.alasan, r.status, r.processed_by, r.processed_at].map(csvCell).join(','));
  }
  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', 'attachment; filename="unblock_requests.csv"');
  res.send('\uFEFF' + lines.join('\r\n'));
}));

// --------------------------------------------------------------------- arsip

const ARCHIVE_COLS = 'id, user_id, nama, nim, alasan, status, created_at, processed_at, processed_by';

// GET /api/admin/unblock-requests/archive
router.get('/archive', wrap(async (req, res) => {
  const [rows] = await pool.query('SELECT * FROM unblock_requests_archive ORDER BY archived_at DESC, id DESC');
  res.json(rows);
}));

// POST /api/admin/unblock-requests/archive  — pindahkan yang approved/denied ke arsip
router.post('/archive', wrap(async (req, res) => {
  const conn = await pool.getConnection();
  let count = 0;
  try {
    await conn.beginTransaction();
    const [eligible] = await conn.query(
      `SELECT id FROM unblock_requests
        WHERE status IN ('approved','denied')
          AND id NOT IN (SELECT id FROM unblock_requests_archive)`
    );
    const ids = eligible.map((r) => r.id);
    if (ids.length) {
      await conn.query(`INSERT INTO unblock_requests_archive (${ARCHIVE_COLS}) SELECT ${ARCHIVE_COLS} FROM unblock_requests WHERE id IN (?)`, [ids]);
      await conn.query('DELETE FROM unblock_requests WHERE id IN (?)', [ids]);
      count = ids.length;
    }
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
  await logEvent(req, req.user.id, 'archive_unblock', { count });
  res.json({ count, message: `Dipindahkan ke arsip: ${count} permohonan.` });
}));

// POST /api/admin/unblock-requests/archive/:id/restore
router.post('/archive/:id/restore', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [arch] = await conn.query('SELECT id FROM unblock_requests_archive WHERE id = ? FOR UPDATE', [id]);
    if (!arch.length) throw new HttpError(404, 'Data arsip tidak ditemukan.');
    const [live] = await conn.query('SELECT id FROM unblock_requests WHERE id = ?', [id]);
    if (live.length) throw new HttpError(409, 'Permohonan sudah ada di dashboard.');
    await conn.query(`INSERT INTO unblock_requests (${ARCHIVE_COLS}) SELECT ${ARCHIVE_COLS} FROM unblock_requests_archive WHERE id = ?`, [id]);
    await conn.query('DELETE FROM unblock_requests_archive WHERE id = ?', [id]);
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
  await logEvent(req, req.user.id, 'restore_unblock', { request_id: id }, 'unblock_request', id);
  res.json({ message: 'Permohonan berhasil dikembalikan ke dashboard.' });
}));

// DELETE /api/admin/unblock-requests/archive  — secure erase seluruh arsip
router.delete('/archive', wrap(async (req, res) => {
  await pool.query('TRUNCATE TABLE unblock_requests_archive');
  await logEvent(req, req.user.id, 'secure_erase_archive');
  res.json({ message: 'Seluruh data arsip telah dihancurkan secara permanen.' });
}));

// ------------------------------------------------------------------ retensi

async function getRetention() {
  const [rows] = await pool.query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('audit_days','unblock_token_hours')");
  const out = { ...DEFAULT_RETENTION };
  for (const r of rows) out[r.setting_key] = Number(r.setting_value);
  return out;
}

// GET /api/admin/unblock-requests/retention
router.get('/retention', wrap(async (req, res) => {
  res.json(await getRetention());
}));

// PUT /api/admin/unblock-requests/retention
router.put('/retention', wrap(async (req, res) => {
  const auditDays = Number(req.body.audit_days);
  const tokenHours = Number(req.body.unblock_token_hours);
  if (!Number.isInteger(auditDays) || auditDays < 1 || auditDays > 3650) throw new HttpError(400, 'Audit log harus 1-3650 hari.');
  if (!Number.isInteger(tokenHours) || tokenHours < 1 || tokenHours > 525600) throw new HttpError(400, 'Unblock token harus 1-525600 jam.');

  const upsert = 'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
  await pool.query(upsert, ['audit_days', String(auditDays)]);
  await pool.query(upsert, ['unblock_token_hours', String(tokenHours)]);
  await logEvent(req, req.user.id, 'set_retention', { auditDays, tokenHours });
  res.json({ message: 'Pengaturan retensi berhasil diperbarui!', audit_days: auditDays, unblock_token_hours: tokenHours });
}));

// POST /api/admin/unblock-requests/retention/run  — padanan dlm_run_retention()
router.post('/retention/run', wrap(async (req, res) => {
  const { audit_days: days, unblock_token_hours: hours } = await getRetention();
  const [tokens] = await pool.query('DELETE FROM unblock_tokens WHERE used = 1 OR expires_at < (NOW() - INTERVAL ? HOUR)', [hours]);
  const [logs] = await pool.query('DELETE FROM audit_logs WHERE created_at < (NOW() - INTERVAL ? DAY)', [days]);
  await logEvent(req, req.user.id, 'run_retention', { tokens: tokens.affectedRows, logs: logs.affectedRows });
  res.json({
    message: 'Pembersihan selesai.',
    deleted_tokens: tokens.affectedRows,
    deleted_audit_logs: logs.affectedRows,
  });
}));

module.exports = router;
