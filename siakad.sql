-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 13 Okt 2025 pada 11.31
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `siakad`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `pertemuan_id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `status` enum('Hadir','Izin','Sakit','Alpa') NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `pertemuan_id`, `mahasiswa_id`, `status`, `updated_at`) VALUES
(1, 1, 1, 'Hadir', '2025-09-28 15:31:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `metadata` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nidn` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`id`, `user_id`, `nidn`, `nama_lengkap`) VALUES
(1, 3, '199002162022031003', 'Dedy Kiswanto, S.Kom., M.Kom.'),
(2, 5, '199309162022031010', 'Ichwanul Muslim Karo Karo, M.Kom.');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kuliah`
--

CREATE TABLE `jadwal_kuliah` (
  `id` int(11) NOT NULL,
  `matkul_id` int(11) NOT NULL,
  `dosen_id` int(11) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jadwal_kuliah`
--

INSERT INTO `jadwal_kuliah` (`id`, `matkul_id`, `dosen_id`, `hari`, `jam_mulai`, `jam_selesai`, `created_at`) VALUES
(1, 1, 1, 'Senin', '08:00:00', '10:30:00', '2025-09-28 12:22:39'),
(3, 2, 2, 'Selasa', '08:00:00', '10:30:00', '2025-09-28 12:38:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs`
--

CREATE TABLE `krs` (
  `id` int(11) NOT NULL,
  `mahasiswa_id` int(11) NOT NULL,
  `tahun_ajaran` varchar(10) NOT NULL,
  `semester` enum('Ganjil','Genap') NOT NULL,
  `status` enum('Draft','Final') DEFAULT 'Draft',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `krs`
--

INSERT INTO `krs` (`id`, `mahasiswa_id`, `tahun_ajaran`, `semester`, `status`, `created_at`) VALUES
(1, 1, '2025/2026', 'Ganjil', 'Draft', '2025-09-28 12:30:38'),
(2, 2, '2025/2026', 'Ganjil', 'Draft', '2025-10-09 17:33:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs_detail`
--

CREATE TABLE `krs_detail` (
  `id` int(11) NOT NULL,
  `krs_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `kode_matkul` varchar(20) DEFAULT NULL,
  `nama_matkul` varchar(100) DEFAULT NULL,
  `sks` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `krs_detail`
--

INSERT INTO `krs_detail` (`id`, `krs_id`, `jadwal_id`, `kode_matkul`, `nama_matkul`, `sks`) VALUES
(1, 1, 1, '3KOM40125', 'KEAMANAN DATA DAN JARINGAN', 3),
(2, 1, 3, '3KOM40126', 'PEMODELAN DAN SIMULASI', 3);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nim` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `angkatan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `user_id`, `nim`, `nama_lengkap`, `jurusan`, `angkatan`) VALUES
(1, 4, '4233550008', 'Riyan Wardhana', 'Ilmu Komputer', 2023),
(2, 2, '4233550027', 'Mhd Ilyasyah Drilanang', 'Ilmu Komputer', 2023);

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_kuliah`
--

CREATE TABLE `mata_kuliah` (
  `id` int(11) NOT NULL,
  `kode_matkul` varchar(20) NOT NULL,
  `nama_matkul` varchar(100) NOT NULL,
  `sks` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `mata_kuliah`
--

INSERT INTO `mata_kuliah` (`id`, `kode_matkul`, `nama_matkul`, `sks`, `created_at`) VALUES
(1, '3KOM40125', 'KEAMANAN DATA DAN JARINGAN', 3, '2025-09-28 10:05:06'),
(2, '3KOM40126', 'PEMODELAN DAN SIMULASI', 3, '2025-09-28 12:37:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `nilai`
--

CREATE TABLE `nilai` (
  `id` int(11) NOT NULL,
  `krs_detail_id` int(11) NOT NULL,
  `nilai_tugas` decimal(5,2) DEFAULT 0.00,
  `nilai_uts` decimal(5,2) DEFAULT 0.00,
  `nilai_uas` decimal(5,2) DEFAULT 0.00,
  `nilai_akhir` decimal(5,2) DEFAULT 0.00,
  `grade_huruf` char(2) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `nilai`
--

INSERT INTO `nilai` (`id`, `krs_detail_id`, `nilai_tugas`, `nilai_uts`, `nilai_uas`, `nilai_akhir`, `grade_huruf`, `updated_at`) VALUES
(1, 2, 95.00, 70.00, 80.00, 80.00, 'B', '2025-09-28 12:58:59');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `isi` text NOT NULL,
  `penulis_id` int(11) DEFAULT NULL,
  `target_role` enum('semua','dosen','mahasiswa') NOT NULL DEFAULT 'semua',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `isi`, `penulis_id`, `target_role`, `created_at`) VALUES
(1, 'Perkuliahan Daring', 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Nemo autem provident nesciunt delectus sit maxime accusantium saepe porro aperiam voluptas.', 1, 'mahasiswa', '2025-09-28 14:33:25'),
(2, 'PKM', 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Nemo autem provident nesciunt delectus sit maxime accusantium saepe porro aperiam voluptas.', 1, 'dosen', '2025-09-28 14:34:32'),
(3, 'Gempa Bumi', 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Nemo autem provident nesciunt delectus sit maxime accusantium saepe porro aperiam voluptas.', 1, 'semua', '2025-09-28 14:34:57');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pertemuan`
--

CREATE TABLE `pertemuan` (
  `id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `pertemuan_ke` int(11) NOT NULL,
  `judul_pertemuan` varchar(255) NOT NULL,
  `tanggal_pertemuan` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pertemuan`
--

INSERT INTO `pertemuan` (`id`, `jadwal_id`, `pertemuan_ke`, `judul_pertemuan`, `tanggal_pertemuan`, `created_at`) VALUES
(1, 3, 1, 'Monte Carlo', '2025-09-28', '2025-09-28 15:24:39');

-- --------------------------------------------------------

--
-- Struktur dari tabel `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'admin'),
(2, 'dosen'),
(3, 'mahasiswa');

-- --------------------------------------------------------

--
-- Struktur dari tabel `unblock_requests`
--

CREATE TABLE `unblock_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nim` varchar(50) NOT NULL,
  `alasan` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `unblock_requests`
--

INSERT INTO `unblock_requests` (`id`, `user_id`, `nama`, `nim`, `alasan`, `created_at`, `status`, `processed_at`, `processed_by`) VALUES
(5, 2, 'Mhd Ilyasyah Drilanang', '4233550027', 'test', '2025-10-09 17:34:25', 'approved', '2025-10-09 17:42:15', 1),
(6, 2, 'Mhd Ilyasyah Drilanang', '4233550027', 'awokawok', '2025-10-10 06:27:04', 'approved', '2025-10-10 06:29:16', 1),
(7, 2, 'Mhd Ilyasyah Drilanang', '4233550026', 'mencoba', '2025-10-13 08:31:06', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `unblock_requests_archive`
--

CREATE TABLE `unblock_requests_archive` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nim` varchar(50) NOT NULL,
  `alasan` text NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `unblock_tokens`
--

CREATE TABLE `unblock_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `unblock_tokens`
--

INSERT INTO `unblock_tokens` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(14, 2, 'd10d8fe9375e2e49c6e47d45cb6de786576af1f9375462cb02343baad3ae08a9', '2025-10-13 08:31:06', 1, '2025-10-13 08:29:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `blocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `is_active`, `created_at`, `failed_attempts`, `blocked_at`) VALUES
(1, 'admin', 'imeanmur@gmail.com', 'riyan2206', 1, 1, '2025-09-28 09:32:09', 0, NULL),
(2, '4233550027', 'mdrilanang@gmail.com', 'anang2018', 3, 0, '2025-10-06 23:46:42', 3, '2025-10-13 08:29:19'),
(3, '199002162022031003', 'dedykiswanto@unimed.ac.id', '$2y$10$zRPJbzAbQTJ8fI/MMjHrve.T9tZvzaJbDp0Q5cHydmKjxo0SsB3Ku', 2, 1, '2025-09-28 12:22:13', 0, NULL),
(4, '4233550008', 'riyanwardhana2@gmail.com', '$2y$10$UmmHw1DXXoynJxsY6wIfGuEHOH84WjO7120Zi4yP1Tzg3HrnoPifa', 3, 1, '2025-09-28 12:28:13', 0, NULL),
(5, '199309162022031010', 'ichwanul@unimed.ac.id', '$2y$10$LXkB3nZ0XJXaMqg0Q3PIHO1SdFZN.rH1hbLUVbM3VSvfnWpq5BUTO', 2, 1, '2025-09-28 12:36:11', 0, NULL),
(6, '4233550123', 'anangilyasyah@gmail.com', 'ilyas123', 3, 1, '2025-10-09 12:20:46', 0, NULL);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_absensi` (`pertemuan_id`,`mahasiswa_id`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`);

--
-- Indeks untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action_created` (`action`,`created_at`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nidn` (`nidn`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `matkul_id` (`matkul_id`),
  ADD KEY `dosen_id` (`dosen_id`);

--
-- Indeks untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mahasiswa_id` (`mahasiswa_id`);

--
-- Indeks untuk tabel `krs_detail`
--
ALTER TABLE `krs_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `krs_id` (`krs_id`),
  ADD KEY `jadwal_id` (`jadwal_id`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nim` (`nim`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_matkul` (`kode_matkul`);

--
-- Indeks untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `krs_detail_id` (`krs_detail_id`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penulis_id` (`penulis_id`);

--
-- Indeks untuk tabel `pertemuan`
--
ALTER TABLE `pertemuan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_pertemuan` (`jadwal_id`,`pertemuan_ke`);

--
-- Indeks untuk tabel `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indeks untuk tabel `unblock_requests`
--
ALTER TABLE `unblock_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indeks untuk tabel `unblock_requests_archive`
--
ALTER TABLE `unblock_requests_archive`
  ADD KEY `idx_archive_user` (`user_id`);

--
-- Indeks untuk tabel `unblock_tokens`
--
ALTER TABLE `unblock_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_token` (`user_id`,`token`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `krs`
--
ALTER TABLE `krs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `krs_detail`
--
ALTER TABLE `krs_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `mata_kuliah`
--
ALTER TABLE `mata_kuliah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `nilai`
--
ALTER TABLE `nilai`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pertemuan`
--
ALTER TABLE `pertemuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `unblock_requests`
--
ALTER TABLE `unblock_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `unblock_tokens`
--
ALTER TABLE `unblock_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`pertemuan_id`) REFERENCES `pertemuan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_kuliah`
--
ALTER TABLE `jadwal_kuliah`
  ADD CONSTRAINT `jadwal_kuliah_ibfk_1` FOREIGN KEY (`matkul_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jadwal_kuliah_ibfk_2` FOREIGN KEY (`dosen_id`) REFERENCES `dosen` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD CONSTRAINT `krs_ibfk_1` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs_detail`
--
ALTER TABLE `krs_detail`
  ADD CONSTRAINT `krs_detail_ibfk_1` FOREIGN KEY (`krs_id`) REFERENCES `krs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `krs_detail_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `nilai`
--
ALTER TABLE `nilai`
  ADD CONSTRAINT `nilai_ibfk_1` FOREIGN KEY (`krs_detail_id`) REFERENCES `krs_detail` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`penulis_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pertemuan`
--
ALTER TABLE `pertemuan`
  ADD CONSTRAINT `pertemuan_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kuliah` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `unblock_requests`
--
ALTER TABLE `unblock_requests`
  ADD CONSTRAINT `fk_unblockreq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `unblock_tokens`
--
ALTER TABLE `unblock_tokens`
  ADD CONSTRAINT `fk_unblock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
