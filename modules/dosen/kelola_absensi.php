<?php
$page_title = 'Kelola Absensi';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'dosen' || !isset($_GET['pertemuan_id'])) {
    header('Location: ' . BASE_URL);
    exit();
}
$pertemuan_id = $_GET['pertemuan_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_absensi'])) {
    $statuses = $_POST['status'];

    $sql = "
        INSERT INTO absensi (pertemuan_id, mahasiswa_id, status)
        VALUES (:pertemuan_id, :mahasiswa_id, :status)
        ON DUPLICATE KEY UPDATE status = VALUES(status)
    ";
    $stmt = $pdo->prepare($sql);

    foreach ($statuses as $mahasiswa_id => $status) {
        $stmt->execute([
            ':pertemuan_id' => $pertemuan_id,
            ':mahasiswa_id' => $mahasiswa_id,
            ':status' => $status
        ]);
    }
    $_SESSION['success_message'] = "Absensi berhasil disimpan/diperbarui.";
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

$stmt_pertemuan = $pdo->prepare("
    SELECT p.*, mk.nama_matkul, mk.kode_matkul, j.id as jadwal_id
    FROM pertemuan p
    JOIN jadwal_kuliah j ON p.jadwal_id = j.id
    JOIN mata_kuliah mk ON j.matkul_id = mk.id
    WHERE p.id = ?
");
$stmt_pertemuan->execute([$pertemuan_id]);
$pertemuan = $stmt_pertemuan->fetch();
if (!$pertemuan) die("Pertemuan tidak ditemukan.");

$stmt_mhs = $pdo->prepare("
    SELECT m.id as mahasiswa_id, m.nim, m.nama_lengkap, a.status
    FROM krs_detail kd
    JOIN krs k ON kd.krs_id = k.id
    JOIN mahasiswa m ON k.mahasiswa_id = m.id
    LEFT JOIN absensi a ON m.id = a.mahasiswa_id AND a.pertemuan_id = ?
    WHERE kd.jadwal_id = ?
    ORDER BY m.nama_lengkap ASC
");
$stmt_mhs->execute([$pertemuan_id, $pertemuan['jadwal_id']]);
$mahasiswa_list = $stmt_mhs->fetchAll(PDO::FETCH_ASSOC);

$status_options = ['Hadir', 'Izin', 'Sakit', 'Alpa'];

require_once '../../includes/sidebar.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= $_SESSION['success_message'];
                                                                                unset($_SESSION['success_message']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<a href="detail_absensi.php?jadwal_id=<?= $pertemuan['jadwal_id']; ?>" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Pertemuan</a>

<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Form Absensi: <?= htmlspecialchars($pertemuan['nama_matkul']); ?></h5>
        <p class="mb-0">Pertemuan Ke-<?= $pertemuan['pertemuan_ke']; ?>: <?= htmlspecialchars($pertemuan['judul_pertemuan']); ?> (<?= date('d M Y', strtotime($pertemuan['tanggal_pertemuan'])); ?>)</p>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>NIM</th>
                            <th>Nama Mahasiswa</th>
                            <th>Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($mahasiswa_list): $i = 1;
                            foreach ($mahasiswa_list as $mhs): ?>
                                <tr>
                                    <th><?= $i++; ?></th>
                                    <td><?= htmlspecialchars($mhs['nim']); ?></td>
                                    <td><?= htmlspecialchars($mhs['nama_lengkap']); ?></td>
                                    <td>
                                        <?php foreach ($status_options as $status): ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio"
                                                    name="status[<?= $mhs['mahasiswa_id']; ?>]"
                                                    id="status-<?= $mhs['mahasiswa_id']; ?>-<?= strtolower($status); ?>"
                                                    value="<?= $status; ?>"
                                                    <?php if (($mhs['status'] ?? 'Alpa') == $status) echo 'checked'; ?>>
                                                <label class="form-check-label" for="status-<?= $mhs['mahasiswa_id']; ?>-<?= strtolower($status); ?>"><?= $status; ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach;
                        else: ?>
                            <tr>
                                <td colspan="4" class="text-center">Tidak ada mahasiswa yang terdaftar di kelas ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-3">
                <button type="submit" name="save_absensi" class="btn btn-primary">Simpan Absensi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>