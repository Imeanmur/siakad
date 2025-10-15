<?php
$page_title = 'Pilih Kelas untuk Absensi';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'dosen') {
            header('Location: ' . BASE_URL);
            exit();
}

$user_id = $_SESSION['user_id'];
$dosen = $pdo->query("SELECT id FROM dosen WHERE user_id = $user_id")->fetch();
if (!$dosen) die("Data dosen tidak ditemukan.");
$dosen_id = $dosen['id'];

$classes = $pdo->query("
    SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul
    FROM jadwal_kuliah j
    JOIN mata_kuliah mk ON j.matkul_id = mk.id
    WHERE j.dosen_id = $dosen_id
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/sidebar.php';
?>

<div class="card shadow-sm">
            <div class="card-header fw-bold">Pilih Kelas untuk Mengelola Absensi</div>
            <div class="card-body">
                        <p>Berikut adalah daftar kelas yang Anda ampu. Silakan pilih kelas untuk membuat sesi pertemuan dan mencatat kehadiran mahasiswa.</p>
                        <div class="list-group">
                                    <?php if ($classes): foreach ($classes as $class): ?>
                                                            <a href="detail_absensi.php?jadwal_id=<?= $class['jadwal_id']; ?>" class="list-group-item list-group-item-action">
                                                                        <strong><?= htmlspecialchars($class['kode_matkul']); ?></strong> - <?= htmlspecialchars($class['nama_matkul']); ?>
                                                            </a>
                                                <?php endforeach;
                                    else: ?>
                                                <div class="alert alert-warning">Anda tidak memiliki kelas yang diampu.</div>
                                    <?php endif; ?>
                        </div>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>