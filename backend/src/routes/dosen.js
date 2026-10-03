const router = require('express').Router();
const { pool } = require('../db');
const { requireAuth, requireRole } = require('../auth');
const { HttpError, wrap, clean, toId } = require('../utils');
const { logEvent } = require('../audit');

// Hanya untuk peran dosen
router.use(requireAuth, requireRole('dosen'));

async function getDosenProfile(userId) {
  const [rows] = await pool.query('SELECT id, nidn, nama_lengkap FROM dosen WHERE user_id = ? LIMIT 1', [userId]);
  if (!rows.length) throw new HttpError(404, 'Data profil dosen tidak ditemukan.');
  return rows[0];
}

// GET /api/dosen/dashboard
router.get('/dashboard', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const [announcements] = await pool.query(
    `SELECT id, judul, isi, created_at FROM pengumuman 
      WHERE target_role IN ('dosen', 'semua') 
      ORDER BY created_at DESC, id DESC LIMIT 5`
  );
  const [[classesCount]] = await pool.query(
    'SELECT COUNT(*) as n FROM jadwal_kuliah WHERE dosen_id = ?',
    [dosen.id]
  );

  res.json({
    dosen,
    classes_count: classesCount.n,
    announcements,
  });
}));

// GET /api/dosen/classes
router.get('/classes', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const [classes] = await pool.query(
    `SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul, j.hari, j.jam_mulai, j.jam_selesai,
            (SELECT COUNT(*) FROM krs_detail kd WHERE kd.jadwal_id = j.id) as total_mahasiswa
       FROM jadwal_kuliah j
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
      WHERE j.dosen_id = ?
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`,
    [dosen.id]
  );
  res.json(classes);
}));

// GET /api/dosen/classes/:jadwalId/grades
router.get('/classes/:jadwalId/grades', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const jadwalId = toId(req.params.jadwalId);

  const [classes] = await pool.query(
    `SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul, mk.sks, j.hari, j.jam_mulai, j.jam_selesai
       FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id
      WHERE j.id = ? AND j.dosen_id = ?`,
    [jadwalId, dosen.id]
  );
  if (!classes.length) throw new HttpError(404, 'Kelas tidak ditemukan atau Anda bukan pengampu kelas ini.');

  const [students] = await pool.query(
    `SELECT m.id as mahasiswa_id, m.nim, m.nama_lengkap, kd.id as krs_detail_id,
            COALESCE(n.nilai_tugas, 0) as nilai_tugas,
            COALESCE(n.nilai_uts, 0) as nilai_uts,
            COALESCE(n.nilai_uas, 0) as nilai_uas,
            COALESCE(n.nilai_akhir, 0) as nilai_akhir,
            n.grade_huruf
       FROM krs_detail kd
       JOIN krs k ON kd.krs_id = k.id
       JOIN mahasiswa m ON k.mahasiswa_id = m.id
       LEFT JOIN nilai n ON kd.id = n.krs_detail_id
      WHERE kd.jadwal_id = ?
      ORDER BY m.nama_lengkap ASC`,
    [jadwalId]
  );

  res.json({
    class_info: classes[0],
    students,
  });
}));

// POST /api/dosen/classes/:jadwalId/grades — Simpan / perbarui nilai
router.post('/classes/:jadwalId/grades', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const jadwalId = toId(req.params.jadwalId);
  const krsDetailId = toId(req.body.krs_detail_id);

  // Pastikan kelas ini milik dosen yang bersangkutan
  const [classes] = await pool.query('SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_id = ?', [jadwalId, dosen.id]);
  if (!classes.length) throw new HttpError(403, 'Akses ditolak.');

  const tugas = Math.min(100, Math.max(0, Number(req.body.nilai_tugas || 0)));
  const uts = Math.min(100, Math.max(0, Number(req.body.nilai_uts || 0)));
  const uas = Math.min(100, Math.max(0, Number(req.body.nilai_uas || 0)));

  // Hitung Nilai Akhir (Tugas 20%, UTS 30%, UAS 50%)
  const akhir = Number(((tugas * 0.2) + (uts * 0.3) + (uas * 0.5)).toFixed(2));

  let grade = 'E';
  if (akhir >= 85) grade = 'A';
  else if (akhir >= 75) grade = 'B';
  else if (akhir >= 65) grade = 'C';
  else if (akhir >= 50) grade = 'D';

  await pool.query(
    `INSERT INTO nilai (krs_detail_id, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, grade_huruf)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       nilai_tugas = VALUES(nilai_tugas),
       nilai_uts = VALUES(nilai_uts),
       nilai_uas = VALUES(nilai_uas),
       nilai_akhir = VALUES(nilai_akhir),
       grade_huruf = VALUES(grade_huruf)`,
    [krsDetailId, tugas, uts, uas, akhir, grade]
  );

  await logEvent(req, req.user.id, 'input_nilai', { krs_detail_id: krsDetailId, nilai_akhir: akhir, grade_huruf: grade }, 'nilai', krsDetailId);

  res.json({
    message: 'Nilai berhasil disimpan.',
    nilai: { nilai_tugas: tugas, nilai_uts: uts, nilai_uas: uas, nilai_akhir: akhir, grade_huruf: grade }
  });
}));

// GET /api/dosen/classes/:jadwalId/sessions — Daftar sesi pertemuan
router.get('/classes/:jadwalId/sessions', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const jadwalId = toId(req.params.jadwalId);

  const [classes] = await pool.query(
    `SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul 
       FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id
      WHERE j.id = ? AND j.dosen_id = ?`,
    [jadwalId, dosen.id]
  );
  if (!classes.length) throw new HttpError(404, 'Kelas tidak ditemukan.');

  const [sessions] = await pool.query(
    'SELECT * FROM pertemuan WHERE jadwal_id = ? ORDER BY pertemuan_ke ASC',
    [jadwalId]
  );

  res.json({
    class_info: classes[0],
    sessions,
  });
}));

// POST /api/dosen/classes/:jadwalId/sessions — Tambah sesi pertemuan baru
router.post('/classes/:jadwalId/sessions', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const jadwalId = toId(req.params.jadwalId);

  const [classes] = await pool.query('SELECT id FROM jadwal_kuliah WHERE id = ? AND dosen_id = ?', [jadwalId, dosen.id]);
  if (!classes.length) throw new HttpError(403, 'Akses ditolak.');

  const pertemuanKe = Number(req.body.pertemuan_ke);
  const judul = clean(req.body.judul_pertemuan);
  const tanggal = clean(req.body.tanggal_pertemuan);

  if (!Number.isInteger(pertemuanKe) || pertemuanKe <= 0 || !judul || !tanggal) {
    throw new HttpError(400, 'Semua field sesi pertemuan wajib diisi dengan benar.');
  }

  const [dup] = await pool.query('SELECT id FROM pertemuan WHERE jadwal_id = ? AND pertemuan_ke = ?', [jadwalId, pertemuanKe]);
  if (dup.length) {
    throw new HttpError(409, `Pertemuan ke-${pertemuanKe} sudah ada untuk kelas ini.`);
  }

  const [result] = await pool.query(
    'INSERT INTO pertemuan (jadwal_id, pertemuan_ke, judul_pertemuan, tanggal_pertemuan) VALUES (?, ?, ?, ?)',
    [jadwalId, pertemuanKe, judul, tanggal]
  );

  await logEvent(req, req.user.id, 'create_session', { jadwalId, pertemuanKe, judul }, 'pertemuan', result.insertId);

  res.status(201).json({
    id: result.insertId,
    message: 'Sesi pertemuan baru berhasil ditambahkan.',
  });
}));

// GET /api/dosen/sessions/:pertemuanId — Detail absensi pertemuan
router.get('/sessions/:pertemuanId', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const pertemuanId = toId(req.params.pertemuanId);

  const [rows] = await pool.query(
    `SELECT p.*, mk.nama_matkul, mk.kode_matkul, j.id as jadwal_id
       FROM pertemuan p
       JOIN jadwal_kuliah j ON p.jadwal_id = j.id
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
      WHERE p.id = ? AND j.dosen_id = ?`,
    [pertemuanId, dosen.id]
  );
  if (!rows.length) throw new HttpError(404, 'Sesi pertemuan tidak ditemukan.');
  const session = rows[0];

  const [students] = await pool.query(
    `SELECT m.id as mahasiswa_id, m.nim, m.nama_lengkap, a.status
       FROM krs_detail kd
       JOIN krs k ON kd.krs_id = k.id
       JOIN mahasiswa m ON k.mahasiswa_id = m.id
       LEFT JOIN absensi a ON m.id = a.mahasiswa_id AND a.pertemuan_id = ?
      WHERE kd.jadwal_id = ?
      ORDER BY m.nama_lengkap ASC`,
    [pertemuanId, session.jadwal_id]
  );

  res.json({
    session,
    students,
    status_options: ['Hadir', 'Izin', 'Sakit', 'Alpa'],
  });
}));

// POST /api/dosen/sessions/:pertemuanId/attendance — Simpan kehadiran
router.post('/sessions/:pertemuanId/attendance', wrap(async (req, res) => {
  const dosen = await getDosenProfile(req.user.id);
  const pertemuanId = toId(req.params.pertemuanId);
  const statuses = req.body.statuses || {}; // { [mahasiswa_id]: 'Hadir' | 'Izin' | 'Sakit' | 'Alpa' }

  const [rows] = await pool.query(
    `SELECT p.id FROM pertemuan p JOIN jadwal_kuliah j ON p.jadwal_id = j.id WHERE p.id = ? AND j.dosen_id = ?`,
    [pertemuanId, dosen.id]
  );
  if (!rows.length) throw new HttpError(403, 'Akses ditolak.');

  const validStatuses = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    for (const [mhsIdStr, status] of Object.entries(statuses)) {
      const mhsId = Number(mhsIdStr);
      if (!Number.isInteger(mhsId) || !validStatuses.includes(status)) continue;

      await conn.query(
        `INSERT INTO absensi (pertemuan_id, mahasiswa_id, status)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)`,
        [pertemuanId, mhsId, status]
      );
    }
    await conn.commit();
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }

  await logEvent(req, req.user.id, 'save_attendance', { pertemuanId });

  res.json({ message: 'Absensi berhasil disimpan.' });
}));

module.exports = router;
