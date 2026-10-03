const router = require('express').Router();
const { pool } = require('../../db');
const { HttpError, wrap, clean, toId } = require('../../utils');
const { logEvent } = require('../../audit');

const TARGETS = ['semua', 'dosen', 'mahasiswa'];

function readBody(body) {
  const judul = clean(body.judul);
  const isi = clean(body.isi);
  const target = typeof body.target_role === 'string' ? body.target_role : '';
  if (!judul || !isi || !TARGETS.includes(target)) throw new HttpError(400, 'Semua field wajib diisi.');
  if (judul.length > 255) throw new HttpError(400, 'Judul maksimal 255 karakter.');
  if (isi.length > 20000) throw new HttpError(400, 'Isi pengumuman terlalu panjang.');
  return { judul, isi, target };
}

// GET /api/admin/announcements
router.get('/', wrap(async (req, res) => {
  const [rows] = await pool.query('SELECT id, judul, isi, target_role, created_at FROM pengumuman ORDER BY created_at DESC, id DESC');
  res.json(rows);
}));

// POST /api/admin/announcements
router.post('/', wrap(async (req, res) => {
  const { judul, isi, target } = readBody(req.body);
  const [r] = await pool.query(
    'INSERT INTO pengumuman (judul, isi, target_role, penulis_id) VALUES (?, ?, ?, ?)',
    [judul, isi, target, req.user.id]
  );
  await logEvent(req, req.user.id, 'create_announcement', { judul }, 'pengumuman', r.insertId);
  res.status(201).json({ id: r.insertId, message: 'Pengumuman berhasil dipublikasikan.' });
}));

// PUT /api/admin/announcements/:id
router.put('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const { judul, isi, target } = readBody(req.body);
  const [r] = await pool.query('UPDATE pengumuman SET judul = ?, isi = ?, target_role = ? WHERE id = ?', [judul, isi, target, id]);
  if (!r.affectedRows) throw new HttpError(404, 'Pengumuman tidak ditemukan.');
  await logEvent(req, req.user.id, 'update_announcement', { judul }, 'pengumuman', id);
  res.json({ message: 'Pengumuman berhasil diperbarui.' });
}));

// DELETE /api/admin/announcements/:id
router.delete('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const [r] = await pool.query('DELETE FROM pengumuman WHERE id = ?', [id]);
  if (!r.affectedRows) throw new HttpError(404, 'Pengumuman tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_announcement', {}, 'pengumuman', id);
  res.json({ message: 'Pengumuman berhasil dihapus.' });
}));

module.exports = router;
