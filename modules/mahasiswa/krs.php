<?php
$page_title = 'Kartu Rencana Studi (KRS)';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'mahasiswa') {
            header('Location: ' . BASE_URL);
            exit();
}

// --- PENGATURAN DASAR ---
$user_id = $_SESSION['user_id'];
$max_sks = 24; // Batas maksimal SKS yang bisa diambil

// Asumsikan semester aktif (nanti bisa dibuat dinamis)
$current_tahun_ajaran = "2025/2026";
$current_semester = "Ganjil";

// --- AMBIL DATA MAHASISWA ---
$stmt_mhs = $pdo->prepare("SELECT id FROM mahasiswa WHERE user_id = ?");
$stmt_mhs->execute([$user_id]);
$mahasiswa = $stmt_mhs->fetch();
if (!$mahasiswa) die("Data mahasiswa tidak ditemukan.");
$mahasiswa_id = $mahasiswa['id'];

// --- CEK ATAU BUAT KRS UNTUK SEMESTER INI ---
$stmt_krs = $pdo->prepare("SELECT * FROM krs WHERE mahasiswa_id = ? AND tahun_ajaran = ? AND semester = ?");
$stmt_krs->execute([$mahasiswa_id, $current_tahun_ajaran, $current_semester]);
$krs = $stmt_krs->fetch();

// Jika belum ada, buat KRS baru untuk mahasiswa ini
if (!$krs) {
            $pdo->prepare("INSERT INTO krs (mahasiswa_id, tahun_ajaran, semester) VALUES (?, ?, ?)")
                        ->execute([$mahasiswa_id, $current_tahun_ajaran, $current_semester]);
            // Ambil lagi data KRS yang baru dibuat
            $stmt_krs->execute([$mahasiswa_id, $current_tahun_ajaran, $current_semester]);
            $krs = $stmt_krs->fetch();
}
$krs_id = $krs['id'];

// --- LOGIKA "AMBIL" MATA KULIAH (CREATE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
            $jadwal_id = $_POST['jadwal_id'];

            // Ambil detail jadwal yang akan diambil
            $stmt_jadwal = $pdo->prepare("SELECT mk.sks, j.hari, j.jam_mulai, j.jam_selesai, mk.kode_matkul, mk.nama_matkul FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id WHERE j.id = ?");
            $stmt_jadwal->execute([$jadwal_id]);
            $jadwal_baru = $stmt_jadwal->fetch();

            // Ambil KRS yang sudah diambil mahasiswa
            $stmt_krs_diambil = $pdo->prepare("SELECT j.hari, j.jam_mulai, j.jam_selesai, kd.sks FROM krs_detail kd JOIN jadwal_kuliah j ON kd.jadwal_id = j.id WHERE kd.krs_id = ?");
            $stmt_krs_diambil->execute([$krs_id]);
            $krs_diambil = $stmt_krs_diambil->fetchAll();

            $total_sks = array_sum(array_column($krs_diambil, 'sks'));

            // 1. Validasi SKS
            if (($total_sks + $jadwal_baru['sks']) > $max_sks) {
                        $_SESSION['error_message'] = "Gagal! Total SKS akan melebihi batas maksimal ({$max_sks} SKS).";
            } else {
                        // 2. Validasi Jadwal Bentrok
                        $bentrok = false;
                        foreach ($krs_diambil as $jadwal_lama) {
                                    if ($jadwal_lama['hari'] == $jadwal_baru['hari']) { // Jika harinya sama
                                                // Cek jika jam tumpang tindih
                                                if (($jadwal_baru['jam_mulai'] < $jadwal_lama['jam_selesai']) && ($jadwal_baru['jam_selesai'] > $jadwal_lama['jam_mulai'])) {
                                                            $bentrok = true;
                                                            break;
                                                }
                                    }
                        }

                        if ($bentrok) {
                                    $_SESSION['error_message'] = "Gagal! Jadwal bentrok dengan mata kuliah lain.";
                        } else {
                                    // Jika semua validasi lolos, tambahkan ke krs_detail
                                    $stmt_insert = $pdo->prepare("INSERT INTO krs_detail (krs_id, jadwal_id, kode_matkul, nama_matkul, sks) VALUES (?, ?, ?, ?, ?)");
                                    $stmt_insert->execute([$krs_id, $jadwal_id, $jadwal_baru['kode_matkul'], $jadwal_baru['nama_matkul'], $jadwal_baru['sks']]);
                                    $_SESSION['success_message'] = "Mata kuliah berhasil ditambahkan ke KRS.";
                        }
            }
            header("Location: " . BASE_URL . "modules/mahasiswa/krs.php");
            exit();
}

// --- LOGIKA "HAPUS" MATA KULIAH (DELETE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['remove_course'])) {
            $krs_detail_id = $_POST['krs_detail_id'];
            $pdo->prepare("DELETE FROM krs_detail WHERE id = ? AND krs_id = ?")->execute([$krs_detail_id, $krs_id]);
            $_SESSION['success_message'] = "Mata kuliah berhasil dihapus dari KRS.";
            header("Location: " . BASE_URL . "modules/mahasiswa/krs.php");
            exit();
}


// --- LOGIKA READ (Mengambil data untuk ditampilkan) ---
// 1. Ambil mata kuliah yang SUDAH diambil
$stmt_selected = $pdo->prepare("SELECT kd.id, kd.kode_matkul, kd.nama_matkul, kd.sks, d.nama_lengkap as nama_dosen, j.hari, j.jam_mulai, j.jam_selesai FROM krs_detail kd JOIN jadwal_kuliah j ON kd.jadwal_id = j.id JOIN dosen d ON j.dosen_id = d.id WHERE kd.krs_id = ?");
$stmt_selected->execute([$krs_id]);
$selected_courses = $stmt_selected->fetchAll();
$total_sks_diambil = array_sum(array_column($selected_courses, 'sks'));

// 2. Ambil jadwal yang TERSEDIA (dan belum diambil)
$stmt_available = $pdo->prepare("SELECT j.id, mk.kode_matkul, mk.nama_matkul, mk.sks, d.nama_lengkap as nama_dosen, j.hari, j.jam_mulai, j.jam_selesai FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id JOIN dosen d ON j.dosen_id = d.id WHERE j.id NOT IN (SELECT jadwal_id FROM krs_detail WHERE krs_id = ?)");
$stmt_available->execute([$krs_id]);
$available_schedules = $stmt_available->fetchAll();


require_once '../../includes/sidebar.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success_message'];
                                                                                                unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= $_SESSION['error_message'];
                                                                                                unset($_SESSION['error_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                        <h3 class="fs-4 mb-0">Pengisian KRS Semester <?= $current_semester ?> <?= $current_tahun_ajaran ?></h3>
                        <span class="badge bg-primary">Total SKS Diambil: <?= $total_sks_diambil ?></span>
                        <span class="badge bg-secondary">Batas SKS: <?= $max_sks ?></span>
            </div>
</div>

<div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Mata Kuliah yang Anda Ambil</div>
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                                <thead>
                                                            <tr>
                                                                        <th>No</th>
                                                                        <th>Mata Kuliah</th>
                                                                        <th>SKS</th>
                                                                        <th>Dosen</th>
                                                                        <th>Jadwal</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($selected_courses): $i = 1;
                                                                        foreach ($selected_courses as $course): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($course['kode_matkul']) . ' - ' . htmlspecialchars($course['nama_matkul']); ?></td>
                                                                                                <td><?= $course['sks']; ?></td>
                                                                                                <td><?= htmlspecialchars($course['nama_dosen']); ?></td>
                                                                                                <td><?= $course['hari'] . ', ' . date('H:i', strtotime($course['jam_mulai'])) . '-' . date('H:i', strtotime($course['jam_selesai'])); ?></td>
                                                                                                <td>
                                                                                                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus mata kuliah ini dari KRS?');">
                                                                                                                        <input type="hidden" name="krs_detail_id" value="<?= $course['id']; ?>">
                                                                                                                        <button type="submit" name="remove_course" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                                                                                            </form>
                                                                                                </td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="6" class="text-center">Anda belum memilih mata kuliah.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>


<div class="card shadow-sm">
            <div class="card-header fw-bold">Mata Kuliah yang Ditawarkan</div>
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                                <thead>
                                                            <tr>
                                                                        <th>No</th>
                                                                        <th>Mata Kuliah</th>
                                                                        <th>SKS</th>
                                                                        <th>Dosen</th>
                                                                        <th>Jadwal</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($available_schedules): $i = 1;
                                                                        foreach ($available_schedules as $schedule): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($schedule['kode_matkul']) . ' - ' . htmlspecialchars($schedule['nama_matkul']); ?></td>
                                                                                                <td><?= $schedule['sks']; ?></td>
                                                                                                <td><?= htmlspecialchars($schedule['nama_dosen']); ?></td>
                                                                                                <td><?= $schedule['hari'] . ', ' . date('H:i', strtotime($schedule['jam_mulai'])) . '-' . date('H:i', strtotime($schedule['jam_selesai'])); ?></td>
                                                                                                <td>
                                                                                                            <form method="POST">
                                                                                                                        <input type="hidden" name="jadwal_id" value="<?= $schedule['id']; ?>">
                                                                                                                        <button type="submit" name="add_course" class="btn btn-success btn-sm"><i class="bi bi-plus-circle"></i> Ambil</button>
                                                                                                            </form>
                                                                                                </td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="6" class="text-center">Tidak ada mata kuliah yang ditawarkan.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>