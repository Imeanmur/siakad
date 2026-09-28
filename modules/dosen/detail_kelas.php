<?php
$page_title = 'Input Nilai Mahasiswa';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'dosen') {
            header('Location: ' . BASE_URL);
            exit();
}
if (!isset($_GET['jadwal_id'])) {
            header('Location: input_nilai.php');
            exit();
}
$jadwal_id = $_GET['jadwal_id'];

// LOGIKA SIMPAN NILAI
require_once '../../core/dlm.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grade'])) {
    $krs_detail_id = $_POST['krs_detail_id'];
    $nilai_tugas = $_POST['nilai_tugas'];
    $nilai_uts = $_POST['nilai_uts'];
    $nilai_uas = $_POST['nilai_uas'];

    // Hitung Nilai Akhir (contoh: Tugas 20%, UTS 30%, UAS 50%)
    $nilai_akhir = ($nilai_tugas * 0.2) + ($nilai_uts * 0.3) + ($nilai_uas * 0.5);

    // Tentukan Grade Huruf
    if ($nilai_akhir >= 85) $grade_huruf = 'A';
    elseif ($nilai_akhir >= 75) $grade_huruf = 'B';
    elseif ($nilai_akhir >= 65) $grade_huruf = 'C';
    elseif ($nilai_akhir >= 50) $grade_huruf = 'D';
    else $grade_huruf = 'E';

    // Gunakan INSERT ... ON DUPLICATE KEY UPDATE (UPSERT)
    $stmt = $pdo->prepare("
        INSERT INTO nilai (krs_detail_id, nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, grade_huruf)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        nilai_tugas = VALUES(nilai_tugas),
        nilai_uts = VALUES(nilai_uts),
        nilai_uas = VALUES(nilai_uas),
        nilai_akhir = VALUES(nilai_akhir),
        grade_huruf = VALUES(grade_huruf)
    ");
    $stmt->execute([$krs_detail_id, $nilai_tugas, $nilai_uts, $nilai_uas, $nilai_akhir, $grade_huruf]);
    dlm_log_event($pdo, $_SESSION['user_id'], 'input_nilai', [
        'krs_detail_id' => $krs_detail_id,
        'nilai_tugas' => $nilai_tugas,
        'nilai_uts' => $nilai_uts,
        'nilai_uas' => $nilai_uas,
        'nilai_akhir' => $nilai_akhir,
        'grade_huruf' => $grade_huruf
    ], 'nilai', $krs_detail_id);
    $_SESSION['success_message'] = "Nilai berhasil disimpan.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// AMBIL DATA KELAS DAN MAHASISWA
$stmt_kelas = $pdo->prepare("SELECT mk.kode_matkul, mk.nama_matkul FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id WHERE j.id = ?");
$stmt_kelas->execute([$jadwal_id]);
$kelas = $stmt_kelas->fetch();

$stmt_mhs = $pdo->prepare("
    SELECT m.nim, m.nama_lengkap, kd.id as krs_detail_id, n.nilai_tugas, n.nilai_uts, n.nilai_uas
    FROM krs_detail kd
    JOIN krs k ON kd.krs_id = k.id
    JOIN mahasiswa m ON k.mahasiswa_id = m.id
    LEFT JOIN nilai n ON kd.id = n.krs_detail_id
    WHERE kd.jadwal_id = ?
");
$stmt_mhs->execute([$jadwal_id]);
$mahasiswa_list = $stmt_mhs->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/sidebar.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success_message'];
                                                                                                unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<a href="input_nilai.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Kelas</a>
<div class="card shadow-sm">
            <div class="card-header">
                        <h5 class="mb-0">Daftar Mahasiswa Kelas: <?= htmlspecialchars($kelas['kode_matkul']) . ' - ' . htmlspecialchars($kelas['nama_matkul']); ?></h5>
            </div>
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-bordered">
                                                <thead class="table-light">
                                                            <tr>
                                                                        <th>#</th>
                                                                        <th>NIM</th>
                                                                        <th>Nama Mahasiswa</th>
                                                                        <th>Tugas</th>
                                                                        <th>UTS</th>
                                                                        <th>UAS</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($mahasiswa_list): $i = 1;
                                                                        foreach ($mahasiswa_list as $mhs): ?>
                                                                                    <form method="POST">
                                                                                                <tr>
                                                                                                            <input type="hidden" name="krs_detail_id" value="<?= $mhs['krs_detail_id']; ?>">
                                                                                                            <th><?= $i++; ?></th>
                                                                                                            <td><?= htmlspecialchars($mhs['nim']); ?></td>
                                                                                                            <td><?= htmlspecialchars($mhs['nama_lengkap']); ?></td>
                                                                                                            <td><input type="number" name="nilai_tugas" class="form-control" value="<?= $mhs['nilai_tugas'] ?? '0'; ?>" min="0" max="100"></td>
                                                                                                            <td><input type="number" name="nilai_uts" class="form-control" value="<?= $mhs['nilai_uts'] ?? '0'; ?>" min="0" max="100"></td>
                                                                                                            <td><input type="number" name="nilai_uas" class="form-control" value="<?= $mhs['nilai_uas'] ?? '0'; ?>" min="0" max="100"></td>
                                                                                                            <td><button type="submit" name="save_grade" class="btn btn-primary btn-sm">Simpan</button></td>
                                                                                                </tr>
                                                                                    </form>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="7" class="text-center">Tidak ada mahasiswa yang mengambil kelas ini.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>