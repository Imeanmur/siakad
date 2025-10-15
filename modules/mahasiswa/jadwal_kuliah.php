<?php
$page_title = 'Jadwal Kuliah';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'mahasiswa') {
    header('Location: ' . BASE_URL);
    exit();
}

try {
    $schedules = $pdo->query("
        SELECT mk.kode_matkul, mk.nama_matkul, d.nama_lengkap as nama_dosen, j.hari, j.jam_mulai, j.jam_selesai
        FROM jadwal_kuliah j
        JOIN mata_kuliah mk ON j.matkul_id = mk.id
        JOIN dosen d ON j.dosen_id = d.id
        ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/sidebar.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h4 class="card-title">Jadwal Perkuliahan Semester Ini</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Mata Kuliah</th>
                        <th>Dosen</th>
                        <th>Hari</th>
                        <th>Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($schedules): $i = 1;
                        foreach ($schedules as $schedule): ?>
                            <tr>
                                <th><?= $i++; ?></th>
                                <td><?= htmlspecialchars($schedule['kode_matkul']) . ' - ' . htmlspecialchars($schedule['nama_matkul']); ?></td>
                                <td><?= htmlspecialchars($schedule['nama_dosen']); ?></td>
                                <td><?= htmlspecialchars($schedule['hari']); ?></td>
                                <td><?= date('H:i', strtotime($schedule['jam_mulai'])) . ' - ' . date('H:i', strtotime($schedule['jam_selesai'])); ?></td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Jadwal belum tersedia.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>