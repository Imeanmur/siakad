const router = require('express').Router();
const { pool } = require('../db');
const { requireAuth, requireRole } = require('../auth');
const { HttpError, wrap, toId } = require('../utils');
const { logEvent } = require('../audit');

// Hanya untuk peran mahasiswa
router.use(requireAuth, requireRole('mahasiswa'));

const CURRENT_TAHUN_AJARAN = '2025/2026';
const CURRENT_SEMESTER = 'Ganjil';
const MAX_SKS = 24;

function getAngkaMutu(grade) {
  switch (grade) {
    case 'A': return 4.0;
    case 'B': return 3.0;
    case 'C': return 2.0;
    case 'D': return 1.0;
    default: return 0.0;
  }
}

async function getMahasiswaProfile(userId) {
  const [rows] = await pool.query('SELECT id, user_id, nim, nama_lengkap, jurusan, angkatan FROM mahasiswa WHERE user_id = ? LIMIT 1', [userId]);
  if (!rows.length) throw new HttpError(404, 'Data profil mahasiswa tidak ditemukan.');
  return rows[0];
}

async function getOrCreateKrs(mahasiswaId) {
  const [rows] = await pool.query(
    'SELECT * FROM krs WHERE mahasiswa_id = ? AND tahun_ajaran = ? AND semester = ? LIMIT 1',
    [mahasiswaId, CURRENT_TAHUN_AJARAN, CURRENT_SEMESTER]
  );
  if (rows.length) return rows[0];

  const [res] = await pool.query(
    'INSERT INTO krs (mahasiswa_id, tahun_ajaran, semester, status) VALUES (?, ?, ?, "Draft")',
    [mahasiswaId, CURRENT_TAHUN_AJARAN, CURRENT_SEMESTER]
  );
  return { id: res.insertId, mahasiswa_id: mahasiswaId, tahun_ajaran: CURRENT_TAHUN_AJARAN, semester: CURRENT_SEMESTER, status: 'Draft' };
}

// GET /api/mahasiswa/dashboard
router.get('/dashboard', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);
  const krs = await getOrCreateKrs(mhs.id);

  const [announcements] = await pool.query(
    `SELECT id, judul, isi, created_at FROM pengumuman 
      WHERE target_role IN ('mahasiswa', 'semua') 
      ORDER BY created_at DESC, id DESC LIMIT 5`
  );

  const [[sksRow]] = await pool.query(
    'SELECT COALESCE(SUM(sks), 0) as total_sks, COUNT(*) as total_matkul FROM krs_detail WHERE krs_id = ?',
    [krs.id]
  );

  res.json({
    mahasiswa: mhs,
    announcements,
    krs_info: {
      tahun_ajaran: CURRENT_TAHUN_AJARAN,
      semester: CURRENT_SEMESTER,
      total_sks: Number(sksRow.total_sks),
      total_matkul: Number(sksRow.total_matkul),
      max_sks: MAX_SKS,
    }
  });
}));

// GET /api/mahasiswa/krs — Data pengisian KRS
router.get('/krs', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);
  const krs = await getOrCreateKrs(mhs.id);

  const [selected] = await pool.query(
    `SELECT kd.id, kd.jadwal_id, kd.kode_matkul, kd.nama_matkul, kd.sks, 
            d.nama_lengkap as nama_dosen, j.hari, j.jam_mulai, j.jam_selesai
       FROM krs_detail kd
       JOIN jadwal_kuliah j ON kd.jadwal_id = j.id
       JOIN dosen d ON j.dosen_id = d.id
      WHERE kd.krs_id = ?
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`,
    [krs.id]
  );

  const [available] = await pool.query(
    `SELECT j.id, mk.kode_matkul, mk.nama_matkul, mk.sks, d.nama_lengkap as nama_dosen,
            j.hari, j.jam_mulai, j.jam_selesai
       FROM jadwal_kuliah j
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
       JOIN dosen d ON j.dosen_id = d.id
      WHERE j.id NOT IN (SELECT jadwal_id FROM krs_detail WHERE krs_id = ?)
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`,
    [krs.id]
  );

  const totalSks = selected.reduce((sum, item) => sum + Number(item.sks || 0), 0);

  res.json({
    tahun_ajaran: CURRENT_TAHUN_AJARAN,
    semester: CURRENT_SEMESTER,
    max_sks: MAX_SKS,
    total_sks: totalSks,
    selected_courses: selected,
    available_schedules: available,
  });
}));

// POST /api/mahasiswa/krs/add — Ambil mata kuliah
router.post('/krs/add', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);
  const krs = await getOrCreateKrs(mhs.id);
  const jadwalId = toId(req.body.jadwal_id);

  // Ambil detail jadwal baru
  const [jadwalRows] = await pool.query(
    `SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, mk.kode_matkul, mk.nama_matkul, mk.sks
       FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id
      WHERE j.id = ? LIMIT 1`,
    [jadwalId]
  );
  if (!jadwalRows.length) throw new HttpError(404, 'Jadwal kuliah tidak ditemukan.');
  const jadwalBaru = jadwalRows[0];

  // Ambil mata kuliah yang sudah diambil
  const [krsDiambil] = await pool.query(
    `SELECT kd.id, kd.sks, j.hari, j.jam_mulai, j.jam_selesai
       FROM krs_detail kd JOIN jadwal_kuliah j ON kd.jadwal_id = j.id
      WHERE kd.krs_id = ?`,
    [krs.id]
  );

  const totalSksSekarang = krsDiambil.reduce((sum, item) => sum + Number(item.sks || 0), 0);
  if (totalSksSekarang + Number(jadwalBaru.sks) > MAX_SKS) {
    throw new HttpError(400, `Gagal! Total SKS akan melebihi batas maksimal (${MAX_SKS} SKS).`);
  }

  // Cek bentrok jadwal
  for (const jLama of krsDiambil) {
    if (jLama.hari === jadwalBaru.hari) {
      if (jadwalBaru.jam_mulai < jLama.jam_selesai && jadwalBaru.jam_selesai > jLama.jam_mulai) {
        throw new HttpError(400, `Gagal! Jadwal bentrok dengan mata kuliah yang sudah diambil pada hari ${jadwalBaru.hari}.`);
      }
    }
  }

  await pool.query(
    'INSERT INTO krs_detail (krs_id, jadwal_id, kode_matkul, nama_matkul, sks) VALUES (?, ?, ?, ?, ?)',
    [krs.id, jadwalId, jadwalBaru.kode_matkul, jadwalBaru.nama_matkul, jadwalBaru.sks]
  );

  await logEvent(req, req.user.id, 'krs_add_course', { kode: jadwalBaru.kode_matkul, sks: jadwalBaru.sks }, 'krs', krs.id);

  res.status(201).json({ message: 'Mata kuliah berhasil ditambahkan ke KRS.' });
}));

// DELETE /api/mahasiswa/krs/:krsDetailId — Hapus mata kuliah dari KRS
router.delete('/krs/:krsDetailId', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);
  const krs = await getOrCreateKrs(mhs.id);
  const detailId = toId(req.params.krsDetailId);

  const [del] = await pool.query('DELETE FROM krs_detail WHERE id = ? AND krs_id = ?', [detailId, krs.id]);
  if (!del.affectedRows) throw new HttpError(404, 'Mata kuliah tidak ditemukan dalam KRS Anda.');

  await logEvent(req, req.user.id, 'krs_remove_course', { krsDetailId: detailId }, 'krs', krs.id);

  res.json({ message: 'Mata kuliah berhasil dihapus dari KRS.' });
}));

// GET /api/mahasiswa/khs — Kartu Hasil Studi
router.get('/khs', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);

  // Nilai semester aktif
  const [khsSemester] = await pool.query(
    `SELECT kd.kode_matkul, kd.nama_matkul, kd.sks, n.nilai_akhir, n.grade_huruf
       FROM krs k
       JOIN krs_detail kd ON k.id = kd.krs_id
       LEFT JOIN nilai n ON kd.id = n.krs_detail_id
      WHERE k.mahasiswa_id = ? AND k.tahun_ajaran = ? AND k.semester = ?`,
    [mhs.id, CURRENT_TAHUN_AJARAN, CURRENT_SEMESTER]
  );

  let totalSksSemester = 0;
  let totalBobotSemester = 0;
  for (const item of khsSemester) {
    if (item.grade_huruf) {
      const sks = Number(item.sks || 0);
      const mutu = getAngkaMutu(item.grade_huruf);
      totalSksSemester += sks;
      totalBobotSemester += sks * mutu;
    }
  }
  const ips = totalSksSemester > 0 ? Number((totalBobotSemester / totalSksSemester).toFixed(2)) : 0;

  // Nilai kumulatif seluruh semester
  const [allGrades] = await pool.query(
    `SELECT kd.sks, n.grade_huruf
       FROM krs k
       JOIN krs_detail kd ON k.id = kd.krs_id
       JOIN nilai n ON kd.id = n.krs_detail_id
      WHERE k.mahasiswa_id = ? AND n.grade_huruf IS NOT NULL`,
    [mhs.id]
  );

  let totalSksKumulatif = 0;
  let totalBobotKumulatif = 0;
  for (const item of allGrades) {
    const sks = Number(item.sks || 0);
    const mutu = getAngkaMutu(item.grade_huruf);
    totalSksKumulatif += sks;
    totalBobotKumulatif += sks * mutu;
  }
  const ipk = totalSksKumulatif > 0 ? Number((totalBobotKumulatif / totalSksKumulatif).toFixed(2)) : 0;

  res.json({
    mahasiswa: mhs,
    tahun_ajaran: CURRENT_TAHUN_AJARAN,
    semester: CURRENT_SEMESTER,
    khs_semester: khsSemester,
    total_sks_semester: totalSksSemester,
    total_bobot_semester: Number(totalBobotSemester.toFixed(2)),
    ips,
    total_sks_kumulatif: totalSksKumulatif,
    ipk,
  });
}));

// GET /api/mahasiswa/schedules — Jadwal kuliah semester ini
router.get('/schedules', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);

  // Jadwal kelas yang diambil mahasiswa
  const [mySchedules] = await pool.query(
    `SELECT mk.kode_matkul, mk.nama_matkul, mk.sks, d.nama_lengkap as nama_dosen,
            j.hari, j.jam_mulai, j.jam_selesai
       FROM krs_detail kd
       JOIN krs k ON kd.krs_id = k.id
       JOIN jadwal_kuliah j ON kd.jadwal_id = j.id
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
       JOIN dosen d ON j.dosen_id = d.id
      WHERE k.mahasiswa_id = ? AND k.tahun_ajaran = ? AND k.semester = ?
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`,
    [mhs.id, CURRENT_TAHUN_AJARAN, CURRENT_SEMESTER]
  );

  // Semua jadwal yang dibuka universitas semester ini
  const [allSchedules] = await pool.query(
    `SELECT mk.kode_matkul, mk.nama_matkul, mk.sks, d.nama_lengkap as nama_dosen,
            j.hari, j.jam_mulai, j.jam_selesai
       FROM jadwal_kuliah j
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
       JOIN dosen d ON j.dosen_id = d.id
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`
  );

  res.json({
    enrolled_schedules: mySchedules,
    all_schedules: allSchedules,
  });
}));

// GET /api/mahasiswa/attendance — Rekap atau detail absensi
router.get('/attendance', wrap(async (req, res) => {
  const mhs = await getMahasiswaProfile(req.user.id);
  const jadwalId = req.query.jadwal_id ? toId(req.query.jadwal_id) : null;

  if (jadwalId) {
    // Detail pertemuan untuk kelas tertentu
    const [meetings] = await pool.query(
      `SELECT p.pertemuan_ke, p.judul_pertemuan, p.tanggal_pertemuan, a.status,
              mk.nama_matkul, mk.kode_matkul
         FROM pertemuan p
         JOIN jadwal_kuliah j ON p.jadwal_id = j.id
         JOIN mata_kuliah mk ON j.matkul_id = mk.id
         LEFT JOIN absensi a ON p.id = a.pertemuan_id AND a.mahasiswa_id = ?
        WHERE p.jadwal_id = ?
        ORDER BY p.pertemuan_ke ASC`,
      [mhs.id, jadwalId]
    );

    const summary = { Hadir: 0, Izin: 0, Sakit: 0, Alpa: 0 };
    for (const m of meetings) {
      const status = m.status || 'Alpa';
      summary[status] = (summary[status] || 0) + 1;
    }
    const totalPertemuan = meetings.length;
    const persentaseHadir = totalPertemuan > 0 ? Math.round((summary.Hadir / totalPertemuan) * 100) : 0;

    return res.json({
      jadwal_id: jadwalId,
      class_info: meetings[0] ? { kode_matkul: meetings[0].kode_matkul, nama_matkul: meetings[0].nama_matkul } : null,
      meetings,
      summary,
      total_pertemuan: totalPertemuan,
      persentase_hadir: persentaseHadir,
    });
  }

  // Daftar seluruh kelas yang diambil beserta statistik kehadiran
  const [classes] = await pool.query(
    `SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul, d.nama_lengkap as nama_dosen,
            (SELECT COUNT(*) FROM pertemuan p WHERE p.jadwal_id = j.id) as total_pertemuan,
            (SELECT COUNT(*) FROM absensi a JOIN pertemuan p ON a.pertemuan_id = p.id WHERE p.jadwal_id = j.id AND a.mahasiswa_id = ? AND a.status = 'Hadir') as hadir_count
       FROM krs_detail kd
       JOIN krs k ON kd.krs_id = k.id
       JOIN jadwal_kuliah j ON kd.jadwal_id = j.id
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
       JOIN dosen d ON j.dosen_id = d.id
      WHERE k.mahasiswa_id = ? AND k.tahun_ajaran = ? AND k.semester = ?`,
    [mhs.id, mhs.id, CURRENT_TAHUN_AJARAN, CURRENT_SEMESTER]
  );

  res.json({
    classes: classes.map((c) => ({
      ...c,
      persentase: c.total_pertemuan > 0 ? Math.round((c.hadir_count / c.total_pertemuan) * 100) : 0
    }))
  });
}));

module.exports = router;
