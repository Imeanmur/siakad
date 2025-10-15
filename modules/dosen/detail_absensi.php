<?php
$page_title = 'Manajemen Sesi Pertemuan';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'dosen' || !isset($_GET['jadwal_id'])) {
            header('Location: ' . BASE_URL);
            exit();
}
$jadwal_id = $_GET['jadwal_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_pertemuan'])) {
            $pertemuan_ke = $_POST['pertemuan_ke'];
            $judul = $_POST['judul_pertemuan'];
            $tanggal = $_POST['tanggal_pertemuan'];

            if (empty($pertemuan_ke) || empty($judul) || empty($tanggal)) {
                        $_SESSION['error_message'] = "Semua field wajib diisi.";
            } else {
                        $stmt = $pdo->prepare("INSERT INTO pertemuan (jadwal_id, pertemuan_ke, judul_pertemuan, tanggal_pertemuan) VALUES (?, ?, ?, ?)");
                        try {
                                    $stmt->execute([$jadwal_id, $pertemuan_ke, $judul, $tanggal]);
                                    $_SESSION['success_message'] = "Sesi pertemuan baru berhasil ditambahkan.";
                        } catch (PDOException $e) {
                                    if ($e->errorInfo[1] == 1062) { // Error code for duplicate entry
                                                $_SESSION['error_message'] = "Gagal! Pertemuan ke-{$pertemuan_ke} sudah ada untuk kelas ini.";
                                    } else {
                                                $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
                                    }
                        }
            }
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
}

$kelas = $pdo->query("SELECT mk.kode_matkul, mk.nama_matkul FROM jadwal_kuliah j JOIN mata_kuliah mk ON j.matkul_id = mk.id WHERE j.id = $jadwal_id")->fetch();
$pertemuan_list = $pdo->query("SELECT * FROM pertemuan WHERE jadwal_id = $jadwal_id ORDER BY pertemuan_ke ASC")->fetchAll(PDO::FETCH_ASSOC);

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

<a href="absensi.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Kelas</a>

<div class="card shadow-sm mb-4">
            <div class="card-header">
                        <h5 class="mb-0">Kelas: <?= htmlspecialchars($kelas['kode_matkul']) . ' - ' . htmlspecialchars($kelas['nama_matkul']); ?></h5>
            </div>
            <div class="card-body">
                        <h6 class="card-title">Tambah Sesi Pertemuan Baru</h6>
                        <form method="POST" class="row g-3">
                                    <div class="col-md-2"><label class="form-label">Pertemuan Ke-</label><input type="number" name="pertemuan_ke" class="form-control" min="1" required></div>
                                    <div class="col-md-6"><label class="form-label">Judul/Materi Pertemuan</label><input type="text" name="judul_pertemuan" class="form-control" required></div>
                                    <div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal_pertemuan" class="form-control" required></div>
                                    <div class="col-md-1 align-self-end"><button type="submit" name="add_pertemuan" class="btn btn-primary w-100">Tambah</button></div>
                        </form>
            </div>
</div>

<div class="card shadow-sm">
            <div class="card-header fw-bold">Daftar Sesi Pertemuan</div>
            <div class="card-body">
                        <table class="table table-hover">
                                    <thead>
                                                <tr>
                                                            <th>No</th>
                                                            <th>Judul Pertemuan</th>
                                                            <th>Tanggal</th>
                                                            <th>Aksi</th>
                                                </tr>
                                    </thead>
                                    <tbody>
                                                <?php if ($pertemuan_list): foreach ($pertemuan_list as $p): ?>
                                                                        <tr>
                                                                                    <td>Pertemuan Ke-<?= $p['pertemuan_ke']; ?></td>
                                                                                    <td><?= htmlspecialchars($p['judul_pertemuan']); ?></td>
                                                                                    <td><?= date('d M Y', strtotime($p['tanggal_pertemuan'])); ?></td>
                                                                                    <td><a href="kelola_absensi.php?pertemuan_id=<?= $p['id']; ?>" class="btn btn-success btn-sm">Kelola Absensi</a></td>
                                                                        </tr>
                                                            <?php endforeach;
                                                else: ?>
                                                            <tr>
                                                                        <td colspan="4" class="text-center">Belum ada sesi pertemuan yang dibuat.</td>
                                                            </tr>
                                                <?php endif; ?>
                                    </tbody>
                        </table>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>