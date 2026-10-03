const router = require('express').Router();
const { requireAuth, requireRole } = require('../../auth');
const { pool } = require('../../db');
const { wrap } = require('../../utils');

// Semua endpoint di bawah /api/admin hanya untuk role admin.
router.use(requireAuth, requireRole('admin'));

// GET /api/admin/stats  — data dashboard
router.get('/stats', wrap(async (req, res) => {
  const [[mhs]] = await pool.query('SELECT COUNT(*) AS n FROM mahasiswa');
  const [[dsn]] = await pool.query('SELECT COUNT(*) AS n FROM dosen');
  const [[mk]] = await pool.query('SELECT COUNT(*) AS n FROM mata_kuliah');
  const [[pending]] = await pool.query("SELECT COUNT(*) AS n FROM unblock_requests WHERE status = 'pending'");
  const [announcements] = await pool.query('SELECT id, judul, isi, target_role, created_at FROM pengumuman ORDER BY created_at DESC, id DESC LIMIT 5');
  res.json({
    mahasiswa: mhs.n,
    dosen: dsn.n,
    mata_kuliah: mk.n,
    pending_unblock: pending.n,
    announcements,
  });
}));

router.use('/', require('./users'));
router.use('/students', require('./students'));
router.use('/lecturers', require('./lecturers'));
router.use('/courses', require('./courses'));
router.use('/schedules', require('./schedules'));
router.use('/announcements', require('./announcements'));
router.use('/unblock-requests', require('./unblock'));

module.exports = router;
