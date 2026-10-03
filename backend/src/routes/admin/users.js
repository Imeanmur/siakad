const router = require('express').Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../../db');
const { HttpError, wrap, clean, isEmail, toId, checkPassword } = require('../../utils');
const { logEvent } = require('../../audit');

const SUPER_ADMIN_ID = 1;

// GET /api/admin/roles
router.get('/roles', wrap(async (req, res) => {
  const [rows] = await pool.query('SELECT id, role_name FROM roles ORDER BY id');
  res.json(rows);
}));

// GET /api/admin/users
router.get('/users', wrap(async (req, res) => {
  const [rows] = await pool.query(
    `SELECT u.id, u.username, u.email, u.role_id, r.role_name, u.is_active
       FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC`
  );
  res.json(rows);
}));

async function assertRoleExists(roleId) {
  const [rows] = await pool.query('SELECT id FROM roles WHERE id = ?', [roleId]);
  if (!rows.length) throw new HttpError(400, 'Role tidak valid.');
}

// POST /api/admin/users
router.post('/users', wrap(async (req, res) => {
  const username = clean(req.body.username);
  const email = clean(req.body.email);
  const roleId = toId(req.body.role_id);
  if (!username || !email || !req.body.password) throw new HttpError(400, 'Semua field harus diisi.');
  if (username.length > 100) throw new HttpError(400, 'Nama maksimal 100 karakter.');
  if (!isEmail(email)) throw new HttpError(400, 'Format email tidak valid.');
  checkPassword(req.body.password);
  await assertRoleExists(roleId);

  const [dup] = await pool.query('SELECT id FROM users WHERE username = ? OR email = ?', [username, email]);
  if (dup.length) throw new HttpError(409, 'Nama atau Email sudah terdaftar.');

  const hash = await bcrypt.hash(req.body.password, 10);
  const [result] = await pool.query(
    'INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)',
    [username, email, hash, roleId]
  );
  await logEvent(req, req.user.id, 'create_user', { username }, 'user', result.insertId);
  res.status(201).json({ id: result.insertId, message: 'User baru berhasil ditambahkan!' });
}));

// PUT /api/admin/users/:id
router.put('/users/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const username = clean(req.body.username);
  const email = clean(req.body.email);
  const roleId = toId(req.body.role_id);
  if (!username || !email) throw new HttpError(400, 'Username, email, dan role tidak boleh kosong.');
  if (username.length > 100) throw new HttpError(400, 'Nama maksimal 100 karakter.');
  if (!isEmail(email)) throw new HttpError(400, 'Format email tidak valid.');
  await assertRoleExists(roleId);

  const [dup] = await pool.query('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?', [username, email, id]);
  if (dup.length) throw new HttpError(409, 'Nama atau Email sudah digunakan oleh user lain.');

  const password = typeof req.body.password === 'string' ? req.body.password : '';
  let result;
  if (password) {
    checkPassword(password);
    const hash = await bcrypt.hash(password, 10);
    [result] = await pool.query(
      'UPDATE users SET username = ?, email = ?, password = ?, role_id = ? WHERE id = ?',
      [username, email, hash, roleId, id]
    );
  } else {
    [result] = await pool.query(
      'UPDATE users SET username = ?, email = ?, role_id = ? WHERE id = ?',
      [username, email, roleId, id]
    );
  }
  if (!result.affectedRows) throw new HttpError(404, 'User tidak ditemukan.');
  await logEvent(req, req.user.id, 'update_user', { username }, 'user', id);
  res.json({ message: 'Data user berhasil diperbarui!' });
}));

// DELETE /api/admin/users/:id
router.delete('/users/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  if (id === SUPER_ADMIN_ID) throw new HttpError(403, 'Error: Super Admin tidak dapat dihapus.');
  if (id === req.user.id) throw new HttpError(403, 'Anda tidak dapat menghapus akun Anda sendiri.');
  const [result] = await pool.query('DELETE FROM users WHERE id = ?', [id]);
  if (!result.affectedRows) throw new HttpError(404, 'User tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_user', {}, 'user', id);
  res.json({ message: 'User berhasil dihapus.' });
}));

module.exports = router;
