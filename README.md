# SIAKAD — versi HTML/CSS/JavaScript + API Node.js

Proyek ini sekarang punya dua bagian baru (kode PHP lama tetap ada sebagai referensi):

| Folder | Isi | Di-host di |
|---|---|---|
| `frontend/` | HTML + CSS + JavaScript murni (Bootstrap via CDN) | **InfinityFree** (upload ke `htdocs`) |
| `backend/` | REST API Node.js + Express + MySQL | Hosting Node.js terpisah (mis. Render, Railway, VPS) |

> **Mengapa backend terpisah?** InfinityFree hanya menyajikan file statis/PHP dan tidak menjalankan Node.js.
> Database MySQL InfinityFree juga **tidak bisa diakses dari luar InfinityFree**, jadi backend memerlukan
> database MySQL sendiri (mis. Aiven, TiDB Cloud, Railway, atau MySQL di VPS). Cek ketentuan paket gratis masing-masing.

## Fitur & Modul yang Tersedia

Seluruh peran (**Admin**, **Dosen**, **Mahasiswa**) serta autentikasi berbasis **OTP Email (MFA)** telah selesai dikonversi:

1. **Autentikasi & Keamanan (MFA OTP)**:
   - Login multi-role (Admin, Dosen, Mahasiswa) dengan rate-limiting & proteksi auto-block 3x gagal.
   - Verifikasi 6-digit OTP via Email dengan hitung mundur 60 detik, paste support, dan resend cooldown.
   - Template email HTML responsif bergaya kartu resmi dengan fallback console log di server.
   - Dynamic theme pada halaman login & OTP berdasarkan waktu (Pagi, Siang, Sore, Malam).
   - Auto-upgrade password plaintext lama ke bcrypt saat login berhasil.

2. **Modul Admin**:
   - Dashboard analitik & ringkasan statistik.
   - Manajemen User (Admin, Dosen, Mahasiswa).
   - Manajemen Mahasiswa & Dosen.
   - Manajemen Mata Kuliah & Jadwal Kuliah.
   - Pengumuman Akademik (target: semua, dosen, mahasiswa).
   - Permohonan Buka Blokir Akun (setujui/tolak, export CSV, arsip/restore/secure erase, retensi data).

3. **Modul Dosen**:
   - Dashboard Dosen: ringkasan kelas mengajar dan pengumuman.
   - Input Nilai Mahasiswa: penilaian Tugas (20%), UTS (30%), UAS (50%) dengan kalkulasi otomatis Nilai Akhir & Grade Huruf (A-E), serta fitur simpan massal (*bulk save*).
   - Manajemen Sesi Pertemuan: pembuatan sesi pertemuan (Pertemuan Ke, Judul/Materi, Tanggal).
   - Presensi Kehadiran Mahasiswa: pencatatan status Hadir, Izin, Sakit, Alpa dengan tombol praktis *Semua Hadir*.

4. **Modul Mahasiswa**:
   - Dashboard Mahasiswa: profil akademik, bio, batas kuota SKS (maks 24 SKS), dan progress bar.
   - Kartu Rencana Studi (KRS): ambil dan hapus mata kuliah dengan validasi batas 24 SKS dan deteksi bentrok jadwal kuliah.
   - Kartu Hasil Studi (KHS): rincian nilai semester, perhitungan IPS (Indeks Prestasi Semester) dan IPK kumulatif, serta tombol **Cetak KHS** (format transkrip resmi PDF/print layout dengan tanda tangan DPA & Kaprodi).
   - Jadwal Kuliah: tab Jadwal Saya dan Seluruh Jadwal Universitas dengan filter pencarian instan.
   - Presensi Perkuliahan: ringkasan persentase kehadiran per mata kuliah dan rincian kehadiran tiap pertemuan.

## 1. Jalankan backend (lokal)

Butuh [Node.js](https://nodejs.org) 18+.

```bash
cd backend
npm install
copy .env.example .env      # Windows (Linux/Mac: cp .env.example .env)
# isi .env: kredensial DB, JWT_SECRET (acak, >= 32 karakter), FRONTEND_ORIGIN
npm start
```

Import `siakad.sql` ke MySQL (phpMyAdmin Laragon) terlebih dahulu. Cek: <http://localhost:3000/api/health>.

Buat `JWT_SECRET`:

```bash
node -e "console.log(require('crypto').randomBytes(48).toString('hex'))"
```

## 2. Jalankan front-end (lokal)

Sajikan folder `frontend/` lewat server statis, mis. ekstensi VS Code *Live Server* (port 5500) atau:

```bash
npx serve frontend -l 5500
```

Pastikan origin-nya ada di `FRONTEND_ORIGIN` backend (`http://localhost:5500`). Alamat API diatur di
`frontend/js/config.js` (`API_BASE`).

## 3. Deploy

1. **Backend**: deploy folder `backend/` ke hosting Node.js. Set environment variable yang sama dengan `.env`.
   `FRONTEND_ORIGIN` = alamat situs InfinityFree Anda (mis. `https://namasitus.infinityfreeapp.com`, tanpa garis miring akhir).
   Gunakan database yang sudah di-import `siakad.sql`; set `DB_SSL=true` bila penyedia mewajibkan SSL.
2. **Front-end**: ubah `API_BASE` di `frontend/js/config.js` menjadi URL HTTPS backend, mis. `https://api-anda.onrender.com/api`.
   Lalu upload **isi** folder `frontend/` (bukan folder-nya) ke `htdocs` InfinityFree lewat File Manager/FTP.
3. Buka situs, login dengan akun admin.

> API harus HTTPS karena situs InfinityFree diakses lewat HTTPS (browser memblokir *mixed content*).

## Catatan keamanan

- **Segera ganti** kata sandi admin default dan semua password plaintext di `siakad.sql`. Password plaintext lama
  tetap bisa login, tetapi otomatis di-upgrade menjadi hash bcrypt saat login berhasil.
- Berkas lama `core/init.php` berisi **App Password Gmail** yang tertulis di kode. Anggap sudah bocor:
  cabut/regenerasi di akun Google dan jangan commit kredensial baru. Backend baru memakai `.env` (di-`.gitignore`).
- Token login disimpan di `sessionStorage` (hilang saat tab ditutup) dan kedaluwarsa 30 menit tanpa aktivitas.
- Password baru minimal 8 karakter. Login dibatasi (rate limit) dan akun terblokir setelah 3 kali salah, seperti versi PHP.
- Jangan upload `siakad.sql`, folder `backend/`, atau `.env` ke `htdocs`.
