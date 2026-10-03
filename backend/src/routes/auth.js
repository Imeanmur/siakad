const router = require('express').Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../db');
const { HttpError, wrap, clean } = require('../utils');
const { signToken, signPendingToken, verifyPendingToken, verifyPassword, isHashed, requireAuth } = require('../auth');
const { logEvent } = require('../audit');
const { sendOtpEmail } = require('../mailer');

const MAX_ATTEMPTS = 3;
const BLOCKED_MSG = 'Akun Anda telah diblokir. Silakan hubungi admin atau gunakan fitur bantuan.';

function maskEmail(email) {
  if (!email || !email.includes('@')) return email || '';
  const [local, domain] = email.split('@');
  if (local.length <= 2) return `${local[0]}*@${domain}`;
  return `${local[0]}${'*'.repeat(Math.min(local.length - 2, 4))}${local[local.length - 1]}@${domain}`;
}

function generateOtpCode() {
  return String(Math.floor(100000 + Math.random() * 900000));
}

async function createAndSendOtp(user) {
  // Cek cooldown 30 detik
  const [recent] = await pool.query(
    'SELECT next_resend_at FROM otp_codes WHERE user_id = ? AND used = 0 AND next_resend_at > NOW() ORDER BY id DESC LIMIT 1',
    [user.id]
  );
  if (recent.length) {
    throw new HttpError(429, 'Tunggu beberapa saat sebelum meminta kode OTP kembali.');
  }

  // Tandai kode lama sebagai non-aktif
  await pool.query('UPDATE otp_codes SET used = 1 WHERE user_id = ? AND used = 0', [user.id]);

  const code = generateOtpCode();
  await pool.query(
    `INSERT INTO otp_codes (user_id, code, expires_at, next_resend_at, attempts, used)
     VALUES (?, ?, NOW() + INTERVAL 5 MINUTE, NOW() + INTERVAL 30 SECOND, 0, 0)`,
    [user.id, code]
  );

  await sendOtpEmail(user.email, code);
  return code;
}

// POST /api/auth/login — Validasi username/password, lalu inisiasi kirim OTP
router.post('/login', wrap(async (req, res) => {
  const username = clean(req.body && req.body.username);
  const password = req.body && typeof req.body.password === 'string' ? req.body.password : '';

  if (!username || !password) {
    throw new HttpError(400, 'Email/NIM dan password tidak boleh kosong!');
  }

  const [rows] = await pool.query(
    `SELECT u.id, u.username, u.email, u.password, u.is_active, u.failed_attempts, u.blocked_at, r.role_name
       FROM users u JOIN roles r ON u.role_id = r.id
      WHERE u.username = ? OR u.email = ? LIMIT 1`,
    [username, username]
  );
  const user = rows[0];

  if (!user) throw new HttpError(401, 'Email/NIM atau password salah!');

  if (Number(user.is_active) === 0 || user.blocked_at) {
    throw new HttpError(423, BLOCKED_MSG, { blocked: true });
  }

  const ok = await verifyPassword(password, user.password);
  if (!ok) {
    const attempts = Number(user.failed_attempts) + 1;
    if (attempts >= MAX_ATTEMPTS) {
      await pool.query('UPDATE users SET failed_attempts = ?, blocked_at = NOW(), is_active = 0 WHERE id = ?', [attempts, user.id]);
      await logEvent(req, user.id, 'account_blocked', { username }, user.role_name, user.id);
      throw new HttpError(423, BLOCKED_MSG, { blocked: true });
    }
    await pool.query('UPDATE users SET failed_attempts = ? WHERE id = ?', [attempts, user.id]);
    if (attempts === MAX_ATTEMPTS - 1) {
      throw new HttpError(401, 'Password salah! Kesempatan terakhir sebelum akun Anda diblokir.');
    }
    throw new HttpError(401, 'Email/NIM atau password salah! Silakan coba lagi.');
  }

  // Sukses verifikasi password: reset hitungan gagal dan upgrade plaintext jika ada
  if (isHashed(user.password)) {
    await pool.query('UPDATE users SET failed_attempts = 0, blocked_at = NULL, is_active = 1 WHERE id = ?', [user.id]);
  } else {
    const hash = await bcrypt.hash(password, 10);
    await pool.query(
      'UPDATE users SET password = ?, failed_attempts = 0, blocked_at = NULL, is_active = 1 WHERE id = ?',
      [hash, user.id]
    );
  }

  // Buat pending token dan kirim kode OTP ke email pengguna
  const pendingToken = signPendingToken(user);
  await createAndSendOtp(user);
  await logEvent(req, user.id, 'otp_requested', { username }, user.role_name, user.id);

  res.json({
    otp_required: true,
    pending_token: pendingToken,
    email_masked: maskEmail(user.email),
    role: user.role_name,
    message: `Kode OTP verifikasi login telah dikirim ke email ${maskEmail(user.email)}.`,
  });
}));

// POST /api/auth/otp/resend — Kirim ulang kode OTP
router.post('/otp/resend', wrap(async (req, res) => {
  const pendingToken = req.body && req.body.pending_token;
  if (!pendingToken) throw new HttpError(400, 'Token verifikasi tidak ditemukan.');

  const payload = verifyPendingToken(pendingToken);
  const [rows] = await pool.query('SELECT id, username, email, role_id, is_active FROM users WHERE id = ?', [payload.sub]);
  const user = rows[0];
  if (!user || Number(user.is_active) === 0) {
    throw new HttpError(401, 'Pengguna tidak ditemukan atau dinonaktifkan.');
  }

  await createAndSendOtp(user);
  await logEvent(req, user.id, 'otp_resend', { username: user.username });

  res.json({
    success: true,
    message: `Kode OTP baru telah dikirim ke ${maskEmail(user.email)}.`,
  });
}));

// POST /api/auth/otp/verify — Verifikasi 6 digit OTP dan terbitkan session token
router.post('/otp/verify', wrap(async (req, res) => {
  const pendingToken = req.body && req.body.pending_token;
  const rawCode = clean(req.body && req.body.code);

  if (!pendingToken || !rawCode) {
    throw new HttpError(400, 'Token dan kode OTP wajib diisi.');
  }

  const payload = verifyPendingToken(pendingToken);
  const [users] = await pool.query(
    `SELECT u.id, u.username, u.email, u.is_active, r.role_name
       FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?`,
    [payload.sub]
  );
  const user = users[0];
  if (!user || Number(user.is_active) === 0) {
    throw new HttpError(401, 'Akun tidak valid atau telah dinonaktifkan.');
  }

  const [otps] = await pool.query(
    'SELECT * FROM otp_codes WHERE user_id = ? AND used = 0 ORDER BY id DESC LIMIT 1',
    [user.id]
  );
  const currentOtp = otps[0];
  if (!currentOtp) {
    throw new HttpError(400, 'Kode OTP tidak ditemukan. Silakan kirim ulang.');
  }

  // Cek kedaluwarsa
  const now = new Date();
  if (new Date(currentOtp.expires_at) < now) {
    throw new HttpError(400, 'Kode OTP telah kedaluwarsa. Silakan klik kirim ulang.');
  }

  // Cek jumlah percobaan salah
  if (currentOtp.attempts >= 5) {
    throw new HttpError(400, 'Terlalu banyak percobaan salah. Silakan kirim ulang kode OTP.');
  }

  if (currentOtp.code !== rawCode) {
    await pool.query('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [currentOtp.id]);
    throw new HttpError(400, 'Kode OTP salah. Silakan periksa kembali email Anda.');
  }

  // OTP valid: tandai terpakai
  await pool.query('UPDATE otp_codes SET used = 1 WHERE id = ?', [currentOtp.id]);
  await logEvent(req, user.id, 'login', { username: user.username, role: user.role_name }, user.role_name, user.id);

  res.json({
    token: signToken(user),
    user: { id: user.id, username: user.username, role: user.role_name },
  });
}));

// GET /api/auth/me
router.get('/me', requireAuth, (req, res) => {
  res.json({ user: req.user });
});

// POST /api/auth/logout — Sesi dibuang di klien & dicatat di audit
router.post('/logout', requireAuth, wrap(async (req, res) => {
  await logEvent(req, req.user.id, 'logout', {}, req.user.role, req.user.id);
  res.json({ ok: true });
}));

module.exports = router;
