const router = require('express').Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../../db');
const { HttpError, wrap, clean, isEmail, toId, checkPassword } = require('../../utils');
const { logEvent } = require('../../audit');

const ROLE_MAHASISWA = 3;

// GET /api/admin/students
router.get('/', wrap(async (req, res) => {
  const [rows] = await pool.query(
    `SELECT m.id, m.nim, m.nama_lengkap, m.jurusan, m.angkatan, u.email, u.id AS user_id
       FROM mahasiswa m JOIN users u ON m.user_id = u.id
      ORDER BY m.nama_lengkap ASC`
  );
  res.json(rows);
}));

// POST /api/admin/students  — membuat akun user + data mahasiswa dalam satu transaksi
router.post('/', wrap(async (req, res) => {
  const email = clean(req.body.email);
  const nim = clean(req.body.nim);
  const nama = clean(req.body.nama_lengkap);
  const jurusan = clean(req.body.jurusan) || null;
  const angkatanRaw = clean(String(req.body.angkatan ?? ''));
  const angkatan = angkatanRaw ? Number(angkatanRaw) : null;

  if (!email || !nim || !nama || !req.body.password) throw new HttpError(400, 'Field yang wajib tidak boleh kosong.');
  if (!isEmail(email)) throw new HttpError(400, 'Format email tidak valid.');
  if (nim.length > 20) throw new HttpError(400, 'NIM maksimal 20 karakter.');
  if (nama.length > 100 || (jurusan && jurusan.length > 100)) throw new HttpError(400, 'Nama/Prodi maksimal 100 karakter.');
  if (angkatan !== null && (!Number.isInteger(angkatan) || angkatan < 1900 || angkatan > 2100)) {
    throw new HttpError(400, 'Angkatan tidak valid.');
  }
  checkPassword(req.body.password);

  const [dupUser] = await pool.query('SELECT id FROM users WHERE email = ? OR username = ?', [email, nim]);
  const [dupMhs] = await pool.query('SELECT id FROM mahasiswa WHERE nim = ?', [nim]);
  if (dupUser.length || dupMhs.length) throw new HttpError(409, 'Email atau NIM sudah terdaftar.');

  const hash = await bcrypt.hash(req.body.password, 10);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [u] = await conn.query(
      'INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)',
      [nim, email, hash, ROLE_MAHASISWA]
    );
    await conn.query(
      'INSERT INTO mahasiswa (user_id, nim, nama_lengkap, jurusan, angkatan) VALUES (?, ?, ?, ?, ?)',
      [u.insertId, nim, nama, jurusan, angkatan]
    );
    await conn.commit();
    await logEvent(req, req.user.id, 'create_student', { nim }, 'mahasiswa', u.insertId);
    res.status(201).json({ message: 'Mahasiswa baru berhasil ditambahkan.' });
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}));

// DELETE /api/admin/students/:userId  — hanya akun yang memang mahasiswa
router.delete('/:userId', wrap(async (req, res) => {
  const userId = toId(req.params.userId);
  const [result] = await pool.query(
    'DELETE u FROM users u JOIN mahasiswa m ON m.user_id = u.id WHERE u.id = ?',
    [userId]
  );
  if (!result.affectedRows) throw new HttpError(404, 'Data mahasiswa tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_student', {}, 'mahasiswa', userId);
  res.json({ message: 'Data mahasiswa berhasil dihapus.' });
}));

module.exports = router;
