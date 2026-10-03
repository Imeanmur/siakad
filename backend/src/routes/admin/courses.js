const router = require('express').Router();
const { pool } = require('../../db');
const { HttpError, wrap, clean, toId } = require('../../utils');
const { logEvent } = require('../../audit');

function readBody(body) {
  const kode = clean(body.kode_matkul).toUpperCase();
  const nama = clean(body.nama_matkul);
  const sks = Number(clean(String(body.sks ?? '')));
  if (!kode || !nama || !body.sks) throw new HttpError(400, 'Semua field harus diisi.');
  if (kode.length > 20) throw new HttpError(400, 'Kode MK maksimal 20 karakter.');
  if (nama.length > 100) throw new HttpError(400, 'Nama mata kuliah maksimal 100 karakter.');
  if (!Number.isInteger(sks) || sks < 1 || sks > 10) throw new HttpError(400, 'SKS harus angka 1-10.');
  return { kode, nama, sks };
}

// GET /api/admin/courses
router.get('/', wrap(async (req, res) => {
  const [rows] = await pool.query('SELECT id, kode_matkul, nama_matkul, sks FROM mata_kuliah ORDER BY kode_matkul ASC');
  res.json(rows);
}));

// POST /api/admin/courses
router.post('/', wrap(async (req, res) => {
  const { kode, nama, sks } = readBody(req.body);
  const [dup] = await pool.query('SELECT id FROM mata_kuliah WHERE kode_matkul = ?', [kode]);
  if (dup.length) throw new HttpError(409, 'Kode Mata Kuliah sudah ada.');
  const [r] = await pool.query('INSERT INTO mata_kuliah (kode_matkul, nama_matkul, sks) VALUES (?, ?, ?)', [kode, nama, sks]);
  await logEvent(req, req.user.id, 'create_course', { kode }, 'mata_kuliah', r.insertId);
  res.status(201).json({ id: r.insertId, message: 'Mata kuliah baru berhasil ditambahkan!' });
}));

// PUT /api/admin/courses/:id
router.put('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const { kode, nama, sks } = readBody(req.body);
  const [dup] = await pool.query('SELECT id FROM mata_kuliah WHERE kode_matkul = ? AND id != ?', [kode, id]);
  if (dup.length) throw new HttpError(409, 'Kode Mata Kuliah sudah digunakan oleh mata kuliah lain.');
  const [r] = await pool.query('UPDATE mata_kuliah SET kode_matkul = ?, nama_matkul = ?, sks = ? WHERE id = ?', [kode, nama, sks, id]);
  if (!r.affectedRows) throw new HttpError(404, 'Mata kuliah tidak ditemukan.');
  await logEvent(req, req.user.id, 'update_course', { kode }, 'mata_kuliah', id);
  res.json({ message: 'Data mata kuliah berhasil diperbarui!' });
}));

// DELETE /api/admin/courses/:id
router.delete('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const [r] = await pool.query('DELETE FROM mata_kuliah WHERE id = ?', [id]);
  if (!r.affectedRows) throw new HttpError(404, 'Mata kuliah tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_course', {}, 'mata_kuliah', id);
  res.json({ message: 'Mata kuliah berhasil dihapus.' });
}));

module.exports = router;
