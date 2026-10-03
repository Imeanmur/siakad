const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const crypto = require('crypto');
const { pool } = require('./db');
const { HttpError, wrap } = require('./utils');

const SESSION_MINUTES = 30; // sama dengan timeout 1800 detik di includes/header.php

function signToken(user) {
  return jwt.sign(
    { sub: user.id, role: user.role_name, username: user.username },
    process.env.JWT_SECRET,
    { expiresIn: `${SESSION_MINUTES}m` }
  );
}

function signPendingToken(user) {
  return jwt.sign(
    { sub: user.id, role: user.role_name, username: user.username, purpose: 'otp_pending' },
    process.env.JWT_SECRET,
    { expiresIn: '10m' }
  );
}

function verifyPendingToken(token) {
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    if (payload.purpose !== 'otp_pending') {
      throw new Error('Bukan token pending.');
    }
    return payload;
  } catch (err) {
    throw new HttpError(401, 'Sesi verifikasi OTP kedaluwarsa atau tidak valid. Silakan login kembali.');
  }
}

/**
 * Verifikasi password. Mendukung hash bcrypt (termasuk "$2y$" buatan PHP) dan
 * password plaintext lama di database (akan di-upgrade ke bcrypt saat login sukses).
 */
async function verifyPassword(plain, stored) {
  stored = String(stored || '');
  if (/^\$2[aby]\$/.test(stored)) {
    return bcrypt.compare(plain, stored.replace(/^\$2y\$/, '$2a$'));
  }
  const a = Buffer.from(plain);
  const b = Buffer.from(stored);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

const isHashed = (stored) => /^\$2[aby]\$/.test(String(stored || ''));

/**
 * Wajib login. Token dibaca dari header "Authorization: Bearer <token>".
 * Setiap request yang berhasil menerbitkan token baru lewat header X-New-Token
 * sehingga sesi berakhir setelah 30 menit tanpa aktivitas.
 */
const requireAuth = wrap(async (req, res, next) => {
  const header = req.headers.authorization || '';
  const token = header.startsWith('Bearer ') ? header.slice(7) : null;
  if (!token) throw new HttpError(401, 'Silakan login terlebih dahulu.');

  let payload;
  try {
    payload = jwt.verify(token, process.env.JWT_SECRET);
  } catch (err) {
    const expired = err.name === 'TokenExpiredError';
    throw new HttpError(401, expired ? 'Sesi Anda telah berakhir. Silakan login kembali.' : 'Token tidak valid.', { expired });
  }

  // Pastikan akun masih ada, aktif, dan role terbaru dari database.
  const [rows] = await pool.query(
    `SELECT u.id, u.username, u.is_active, u.blocked_at, r.role_name
       FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1`,
    [payload.sub]
  );
  const user = rows[0];
  if (!user || Number(user.is_active) === 0 || user.blocked_at) {
    throw new HttpError(401, 'Akun tidak aktif atau tidak ditemukan.');
  }

  req.user = { id: user.id, username: user.username, role: user.role_name };
  res.setHeader('X-New-Token', signToken(user));
  next();
});

const requireRole = (...roles) => (req, res, next) => {
  if (!req.user || !roles.includes(req.user.role)) {
    return next(new HttpError(403, 'Anda tidak memiliki akses ke sumber daya ini.'));
  }
  next();
};

module.exports = {
  signToken,
  signPendingToken,
  verifyPendingToken,
  verifyPassword,
  isHashed,
  requireAuth,
  requireRole,
};
