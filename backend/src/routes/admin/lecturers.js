const router = require('express').Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../../db');
const { HttpError, wrap, clean, isEmail, toId, checkPassword } = require('../../utils');
const { logEvent } = require('../../audit');

const ROLE_DOSEN = 2;

// GET /api/admin/lecturers
router.get('/', wrap(async (req, res) => {
  const [rows] = await pool.query(
    `SELECT d.id, d.nidn, d.nama_lengkap, u.email, u.id AS user_id
       FROM dosen d JOIN users u ON d.user_id = u.id
      ORDER BY d.nama_lengkap ASC`
  );
  res.json(rows);
}));

function readBody(body) {
  const nidn = clean(body.nidn);
  const nama = clean(body.nama_lengkap);
  const email = clean(body.email);
  if (!nidn || !nama || !email) throw new HttpError(400, 'NIDN, Nama Lengkap, dan Email tidak boleh kosong.');
  if (!isEmail(email)) throw new HttpError(400, 'Format email tidak valid.');
  if (nidn.length > 20) throw new HttpError(400, 'NIDN maksimal 20 karakter.');
  if (nama.length > 100) throw new HttpError(400, 'Nama maksimal 100 karakter.');
  return { nidn, nama, email };
}

// POST /api/admin/lecturers
router.post('/', wrap(async (req, res) => {
  const { nidn, nama, email } = readBody(req.body);
  if (!req.body.password) throw new HttpError(400, 'Semua field harus diisi.');
  checkPassword(req.body.password);

  const [dupUser] = await pool.query('SELECT id FROM users WHERE email = ? OR username = ?', [email, nidn]);
  const [dupDosen] = await pool.query('SELECT id FROM dosen WHERE nidn = ?', [nidn]);
  if (dupUser.length || dupDosen.length) throw new HttpError(409, 'Email atau NIDN sudah terdaftar.');

  const hash = await bcrypt.hash(req.body.password, 10);
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [u] = await conn.query(
      'INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)',
      [nidn, email, hash, ROLE_DOSEN]
    );
    await conn.query('INSERT INTO dosen (user_id, nidn, nama_lengkap) VALUES (?, ?, ?)', [u.insertId, nidn, nama]);
    await conn.commit();
    await logEvent(req, req.user.id, 'create_lecturer', { nidn }, 'dosen', u.insertId);
    res.status(201).json({ message: 'Dosen baru berhasil ditambahkan.' });
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}));

// PUT /api/admin/lecturers/:userId
router.put('/:userId', wrap(async (req, res) => {
  const userId = toId(req.params.userId);
  const { nidn, nama, email } = readBody(req.body);
  const password = typeof req.body.password === 'string' ? req.body.password : '';

  const [exists] = await pool.query('SELECT id FROM dosen WHERE user_id = ?', [userId]);
  if (!exists.length) throw new HttpError(404, 'Data dosen tidak ditemukan.');

  const [dupDosen] = await pool.query('SELECT id FROM dosen WHERE nidn = ? AND user_id != ?', [nidn, userId]);
  const [dupUser] = await pool.query('SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?', [email, nidn, userId]);
  if (dupDosen.length || dupUser.length) throw new HttpError(409, 'NIDN atau Email sudah digunakan oleh dosen lain.');

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    await conn.query('UPDATE dosen SET nidn = ?, nama_lengkap = ? WHERE user_id = ?', [nidn, nama, userId]);
    if (password) {
      checkPassword(password);
      const hash = await bcrypt.hash(password, 10);
      await conn.query('UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?', [nidn, email, hash, userId]);
    } else {
      await conn.query('UPDATE users SET username = ?, email = ? WHERE id = ?', [nidn, email, userId]);
    }
    await conn.commit();
    await logEvent(req, req.user.id, 'update_lecturer', { nidn }, 'dosen', userId);
    res.json({ message: 'Data dosen berhasil diperbarui.' });
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}));

// DELETE /api/admin/lecturers/:userId
router.delete('/:userId', wrap(async (req, res) => {
  const userId = toId(req.params.userId);
  const [result] = await pool.query(
    'DELETE u FROM users u JOIN dosen d ON d.user_id = u.id WHERE u.id = ?',
    [userId]
  );
  if (!result.affectedRows) throw new HttpError(404, 'Data dosen tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_lecturer', {}, 'dosen', userId);
  res.json({ message: 'Data dosen berhasil dihapus.' });
}));

module.exports = router;
