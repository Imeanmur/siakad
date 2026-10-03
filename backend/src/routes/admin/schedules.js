const router = require('express').Router();
const { pool } = require('../../db');
const { HttpError, wrap, toId, toHHMM } = require('../../utils');
const { logEvent } = require('../../audit');

const DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function readBody(body) {
  const matkulId = toId(body.matkul_id);
  const dosenId = toId(body.dosen_id);
  const hari = typeof body.hari === 'string' ? body.hari : '';
  const mulai = toHHMM(body.jam_mulai);
  const selesai = toHHMM(body.jam_selesai);
  if (!DAYS.includes(hari) || !mulai || !selesai) throw new HttpError(400, 'Semua field wajib diisi.');
  if (mulai >= selesai) throw new HttpError(400, 'Jam mulai harus lebih awal dari jam selesai.');
  return { matkulId, dosenId, hari, mulai, selesai };
}

// GET /api/admin/schedules  — daftar jadwal + opsi untuk dropdown form
router.get('/', wrap(async (req, res) => {
  const [schedules] = await pool.query(
    `SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.matkul_id, j.dosen_id,
            mk.kode_matkul, mk.nama_matkul, d.nama_lengkap AS nama_dosen
       FROM jadwal_kuliah j
       JOIN mata_kuliah mk ON j.matkul_id = mk.id
       JOIN dosen d ON j.dosen_id = d.id
      ORDER BY FIELD(j.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai`
  );
  const [courses] = await pool.query('SELECT id, kode_matkul, nama_matkul FROM mata_kuliah ORDER BY nama_matkul ASC');
  const [lecturers] = await pool.query('SELECT id, nama_lengkap FROM dosen ORDER BY nama_lengkap ASC');
  res.json({ schedules, courses, lecturers, days: DAYS });
}));

// POST /api/admin/schedules
router.post('/', wrap(async (req, res) => {
  const s = readBody(req.body);
  const [r] = await pool.query(
    'INSERT INTO jadwal_kuliah (matkul_id, dosen_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?)',
    [s.matkulId, s.dosenId, s.hari, s.mulai, s.selesai]
  );
  await logEvent(req, req.user.id, 'create_schedule', {}, 'jadwal_kuliah', r.insertId);
  res.status(201).json({ id: r.insertId, message: 'Jadwal baru berhasil ditambahkan.' });
}));

// PUT /api/admin/schedules/:id
router.put('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const s = readBody(req.body);
  const [r] = await pool.query(
    'UPDATE jadwal_kuliah SET matkul_id = ?, dosen_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ?',
    [s.matkulId, s.dosenId, s.hari, s.mulai, s.selesai, id]
  );
  if (!r.affectedRows) throw new HttpError(404, 'Jadwal tidak ditemukan.');
  await logEvent(req, req.user.id, 'update_schedule', {}, 'jadwal_kuliah', id);
  res.json({ message: 'Jadwal berhasil diperbarui.' });
}));

// DELETE /api/admin/schedules/:id
router.delete('/:id', wrap(async (req, res) => {
  const id = toId(req.params.id);
  const [r] = await pool.query('DELETE FROM jadwal_kuliah WHERE id = ?', [id]);
  if (!r.affectedRows) throw new HttpError(404, 'Jadwal tidak ditemukan.');
  await logEvent(req, req.user.id, 'delete_schedule', {}, 'jadwal_kuliah', id);
  res.json({ message: 'Jadwal berhasil dihapus.' });
}));

module.exports = router;
