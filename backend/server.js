require('dotenv').config();

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const rateLimit = require('express-rate-limit');
const { ensureSchema } = require('./src/db');

if (!process.env.JWT_SECRET || process.env.JWT_SECRET.length < 32) {
  console.error('JWT_SECRET belum diisi atau terlalu pendek (minimal 32 karakter). Lihat .env.example.');
  process.exit(1);
}

const app = express();
// Di balik proxy hosting (Render, Railway, dll.) agar req.ip & rate limit memakai IP klien asli.
app.set('trust proxy', 1);

app.use(helmet({ crossOriginResourcePolicy: { policy: 'cross-origin' } }));

const allowedOrigins = (process.env.FRONTEND_ORIGIN || '')
  .split(',')
  .map((s) => s.trim().replace(/\/$/, ''))
  .filter(Boolean);

app.use(cors({
  origin(origin, cb) {
    // Izinkan tool non-browser (tanpa header Origin) seperti curl/health check.
    if (!origin || allowedOrigins.includes(origin)) return cb(null, true);
    return cb(null, false);
  },
  allowedHeaders: ['Content-Type', 'Authorization'],
  exposedHeaders: ['X-New-Token', 'Content-Disposition'],
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
}));

app.use(express.json({ limit: '100kb' }));

// Batasi percobaan login brute-force (di luar mekanisme blokir akun setelah 3 kali salah).
app.use('/api/auth/login', rateLimit({
  windowMs: 15 * 60 * 1000,
  limit: 30,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Terlalu banyak percobaan login. Coba lagi beberapa menit lagi.' },
}));

app.get('/api/health', (req, res) => res.json({ status: 'ok' }));
app.use('/api/auth', require('./src/routes/auth'));
app.use('/api/admin', require('./src/routes/admin'));
app.use('/api/dosen', require('./src/routes/dosen'));
app.use('/api/mahasiswa', require('./src/routes/mahasiswa'));

app.use('/api', (req, res) => res.status(404).json({ error: 'Endpoint tidak ditemukan.' }));

// Error handler terpusat
// eslint-disable-next-line no-unused-vars
app.use((err, req, res, next) => {
  if (err.status) {
    return res.status(err.status).json({ error: err.message, ...err.extra });
  }
  if (err.type === 'entity.parse.failed') {
    return res.status(400).json({ error: 'Format JSON tidak valid.' });
  }
  const mysqlMap = {
    ER_DUP_ENTRY: [409, 'Data duplikat: nilai tersebut sudah digunakan.'],
    ER_NO_REFERENCED_ROW_2: [400, 'Data referensi (mata kuliah/dosen/role) tidak ditemukan.'],
    ER_ROW_IS_REFERENCED_2: [409, 'Data tidak dapat dihapus karena masih digunakan data lain.'],
  };
  if (mysqlMap[err.code]) {
    const [status, error] = mysqlMap[err.code];
    return res.status(status).json({ error });
  }
  console.error(err);
  res.status(500).json({ error: 'Terjadi kesalahan pada server.' });
});

const port = Number(process.env.PORT || 3000);
ensureSchema()
  .then(() => app.listen(port, () => console.log(`SIAKAD API berjalan di port ${port}`)))
  .catch((err) => {
    console.error('Gagal terhubung/menyiapkan database:', err.message);
    process.exit(1);
  });
