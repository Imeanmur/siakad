<?php
$page_title = 'Rekap Absensi';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'mahasiswa') {
            header('Location: ' . BASE_URL);
            exit();
}

$user_id = $_SESSION['user_id'];
$current_tahun_ajaran = "2025/2026";
$current_semester = "Ganjil";

$mahasiswa = $pdo->query("SELECT id FROM mahasiswa WHERE user_id = $user_id")->fetch();
if (!$mahasiswa) die("Data mahasiswa tidak ditemukan.");
$mahasiswa_id = $mahasiswa['id'];

$jadwal_id = $_GET['jadwal_id'] ?? null;

if ($jadwal_id) {
            $stmt_detail = $pdo->prepare("
        SELECT p.pertemuan_ke, p.judul_pertemuan, p.tanggal_pertemuan, a.status,
               mk.nama_matkul, mk.kode_matkul
        FROM pertemuan p
        JOIN jadwal_kuliah j ON p.jadwal_id = j.id
        JOIN mata_kuliah mk ON j.matkul_id = mk.id
        LEFT JOIN absensi a ON p.id = a.pertemuan_id AND a.mahasiswa_id = ?
        WHERE p.jadwal_id = ?
        ORDER BY p.pertemuan_ke ASC
    ");
            $stmt_detail->execute([$mahasiswa_id, $jadwal_id]);
            $detail_absensi = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

            $summary = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpa' => 0];
            $total_pertemuan = count($detail_absensi);
            foreach ($detail_absensi as $detail) {
                        $status = $detail['status'] ?? 'Alpa';
                        $summary[$status]++;
            }
            $persentase_hadir = ($total_pertemuan > 0) ? round(($summary['Hadir'] / $total_pertemuan) * 100) : 0;
} else {
            $stmt_kelas = $pdo->prepare("
        SELECT j.id as jadwal_id, mk.kode_matkul, mk.nama_matkul, d.nama_lengkap as nama_dosen
        FROM krs_detail kd
        JOIN krs k ON kd.krs_id = k.id
        JOIN jadwal_kuliah j ON kd.jadwal_id = j.id
        JOIN mata_kuliah mk ON j.matkul_id = mk.id
        JOIN dosen d ON j.dosen_id = d.id
        WHERE k.mahasiswa_id = ? AND k.tahun_ajaran = ? AND k.semester = ?
    ");
            $stmt_kelas->execute([$mahasiswa_id, $current_tahun_ajaran, $current_semester]);
            $kelas_diambil = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);
}

require_once '../../includes/sidebar.php';
?>

<?php if ($jadwal_id):
?>
            <a href="absensi.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Kelas</a>
            <div class="card shadow-sm">
                        <div class="card-header">
                                    <h5 class="mb-0">Detail Absensi: <?= htmlspecialchars($detail_absensi[0]['kode_matkul'] ?? '') . ' - ' . htmlspecialchars($detail_absensi[0]['nama_matkul'] ?? 'Kelas Tidak Ditemukan'); ?></h5>
                        </div>
                        <div class="card-body">
                                    <div class="row text-center mb-3">
                                                <div class="col">
                                                            <div class="card p-2">
                                                                        <h6 class="mb-0">Hadir</h6>
                                                                        <p class="fs-4 mb-0"><?= $summary['Hadir'] ?></p>
                                                            </div>
                                                </div>
                                                <div class="col">
                                                            <div class="card p-2">
                                                                        <h6 class="mb-0">Izin</h6>
                                                                        <p class="fs-4 mb-0"><?= $summary['Izin'] ?></p>
                                                            </div>
                                                </div>
                                                <div class="col">
                                                            <div class="card p-2">
                                                                        <h6 class="mb-0">Sakit</h6>
                                                                        <p class="fs-4 mb-0"><?= $summary['Sakit'] ?></p>
                                                            </div>
                                                </div>
                                                <div class="col">
                                                            <div class="card p-2">
                                                                        <h6 class="mb-0">Alpa</h6>
                                                                        <p class="fs-4 mb-0"><?= $summary['Alpa'] ?></p>
                                                            </div>
                                                </div>
                                                <div class="col">
                                                            <div class="card bg-primary text-white p-2">
                                                                        <h6 class="mb-0">Kehadiran</h6>
                                                                        <p class="fs-4 mb-0"><?= $persentase_hadir ?>%</p>
                                                            </div>
                                                </div>
                                    </div>
                                    <div class="table-responsive">
                                                <table class="table table-striped table-bordered">
                                                            <thead class="table-light">
                                                                        <tr>
                                                                                    <th>Pertemuan Ke-</th>
                                                                                    <th>Materi</th>
                                                                                    <th>Tanggal</th>
                                                                                    <th>Status</th>
                                                                        </tr>
                                                            </thead>
                                                            <tbody>
                                                                        <?php if ($detail_absensi): foreach ($detail_absensi as $detail): ?>
                                                                                                <tr>
                                                                                                            <td><?= $detail['pertemuan_ke']; ?></td>
                                                                                                            <td><?= htmlspecialchars($detail['judul_pertemuan']); ?></td>
                                                                                                            <td><?= date('d M Y', strtotime($detail['tanggal_pertemuan'])); ?></td>
                                                                                                            <td>
                                                                                                                        <?php $status = $detail['status'] ?? 'Alpa';
                                                                                                                        $badge_class = ['Hadir' => 'success', 'Izin' => 'info', 'Sakit' => 'warning', 'Alpa' => 'danger'];
                                                                                                                        echo "<span class='badge bg-{$badge_class[$status]}'>$status</span>"; ?>
                                                                                                            </td>
                                                                                                </tr>
                                                                                    <?php endforeach;
                                                                        else: ?>
                                                                                    <tr>
                                                                                                <td colspan="4" class="text-center">Belum ada data pertemuan untuk kelas ini.</td>
                                                                                    </tr>
                                                                        <?php endif; ?>
                                                            </tbody>
                                                </table>
                                    </div>
                        </div>
            </div>
<?php else:
?>
            <div class="card shadow-sm">
                        <div class="card-header fw-bold">Pilih Kelas untuk Melihat Rekap Absensi</div>
                        <div class="card-body">
                                    <div class="list-group">
                                                <?php if ($kelas_diambil): foreach ($kelas_diambil as $kelas): ?>
                                                                        <a href="absensi.php?jadwal_id=<?= $kelas['jadwal_id']; ?>" class="list-group-item list-group-item-action">
                                                                                    <strong><?= htmlspecialchars($kelas['kode_matkul']); ?> - <?= htmlspecialchars($kelas['nama_matkul']); ?></strong>
                                                                                    <br><small class="text-muted">Dosen: <?= htmlspecialchars($kelas['nama_dosen']); ?></small>
                                                                        </a>
                                                            <?php endforeach;
                                                else: ?>
                                                            <div class="alert alert-warning">Anda tidak mengambil kelas apapun semester ini.</div>
                                                <?php endif; ?>
                                    </div>
                        </div>
            </div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>