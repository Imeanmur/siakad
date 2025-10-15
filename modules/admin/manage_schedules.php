<?php
$page_title = 'Manajemen Jadwal Kuliah';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_schedule'])) {
    $matkul_id = $_POST['matkul_id'];
    $dosen_id = $_POST['dosen_id'];
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    if (empty($matkul_id) || empty($dosen_id) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
        $_SESSION['error_message'] = "Semua field wajib diisi.";
    } elseif ($jam_mulai >= $jam_selesai) {
        $_SESSION['error_message'] = "Jam mulai harus lebih awal dari jam selesai.";
    } else {
        $sql = "INSERT INTO jadwal_kuliah (matkul_id, dosen_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$matkul_id, $dosen_id, $hari, $jam_mulai, $jam_selesai]);
        $_SESSION['success_message'] = "Jadwal baru berhasil ditambahkan.";
    }
    header("Location: manage_schedules.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_schedule'])) {
    $id = $_POST['id'];
    $matkul_id = $_POST['matkul_id'];
    $dosen_id = $_POST['dosen_id'];
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    if (empty($matkul_id) || empty($dosen_id) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
        $_SESSION['error_message'] = "Semua field wajib diisi.";
    } elseif ($jam_mulai >= $jam_selesai) {
        $_SESSION['error_message'] = "Jam mulai harus lebih awal dari jam selesai.";
    } else {
        $sql = "UPDATE jadwal_kuliah SET matkul_id = ?, dosen_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$matkul_id, $dosen_id, $hari, $jam_mulai, $jam_selesai, $id]);
        $_SESSION['success_message'] = "Jadwal berhasil diperbarui.";
    }
    header("Location: manage_schedules.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_schedule'])) {
    $id = $_POST['id_to_delete'];
    $pdo->prepare("DELETE FROM jadwal_kuliah WHERE id = ?")->execute([$id]);
    $_SESSION['success_message'] = "Jadwal berhasil dihapus.";
    header("Location: manage_schedules.php");
    exit();
}

$schedules = $pdo->query("
    SELECT 
        j.id, j.hari, j.jam_mulai, j.jam_selesai, j.matkul_id, j.dosen_id,
        mk.kode_matkul, mk.nama_matkul,
        d.nama_lengkap as nama_dosen
    FROM jadwal_kuliah j
    JOIN mata_kuliah mk ON j.matkul_id = mk.id
    JOIN dosen d ON j.dosen_id = d.id
    ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai
")->fetchAll(PDO::FETCH_ASSOC);

$courses = $pdo->query("SELECT id, kode_matkul, nama_matkul FROM mata_kuliah ORDER BY nama_matkul ASC")->fetchAll(PDO::FETCH_ASSOC);
$lecturers = $pdo->query("SELECT id, nama_lengkap FROM dosen ORDER BY nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

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
    <h3 class="fs-4 mb-0">Jadwal Perkuliahan</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal"><i class="bi bi-plus-circle me-2"></i>Buat Jadwal</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Mata Kuliah</th>
                        <th>Dosen</th>
                        <th>Hari</th>
                        <th>Waktu</th>
                        <th>Aksi</th>
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
                                <td class="d-flex gap-2">
                                    <button class="btn btn-sm btn-warning edit-btn"
                                        data-bs-toggle="modal" data-bs-target="#editScheduleModal"
                                        data-id="<?= $schedule['id']; ?>"
                                        data-matkulid="<?= $schedule['matkul_id']; ?>"
                                        data-dosenid="<?= $schedule['dosen_id']; ?>"
                                        data-hari="<?= $schedule['hari']; ?>"
                                        data-jammulai="<?= $schedule['jam_mulai']; ?>"
                                        data-jamselesai="<?= $schedule['jam_selesai']; ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteScheduleModal" data-id="<?= $schedule['id']; ?>" data-info="<?= htmlspecialchars($schedule['nama_matkul']); ?>"><i class="bi bi-trash"></i> Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="6" class="text-center">Belum ada jadwal yang dibuat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addScheduleModal" tabindex="-1">
</div>

<div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form Edit Jadwal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3"><label class="form-label">Mata Kuliah</label><select class="form-select" name="matkul_id" id="edit-matkul-id" required><?php foreach ($courses as $course): ?><option value="<?= $course['id']; ?>"><?= htmlspecialchars($course['kode_matkul']) . ' - ' . htmlspecialchars($course['nama_matkul']); ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Dosen Pengampu</label><select class="form-select" name="dosen_id" id="edit-dosen-id" required><?php foreach ($lecturers as $lecturer): ?><option value="<?= $lecturer['id']; ?>"><?= htmlspecialchars($lecturer['nama_lengkap']); ?></option><?php endforeach; ?></select></div>
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Hari</label><select class="form-select" name="hari" id="edit-hari" required><?php foreach ($days as $day): ?><option value="<?= $day; ?>"><?= $day; ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Jam Mulai</label><input type="time" class="form-control" name="jam_mulai" id="edit-jam-mulai" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Jam Selesai</label><input type="time" class="form-control" name="jam_selesai" id="edit-jam-selesai" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_schedule" class="btn btn-primary">Update</button></div>
            </div>
        </form>
    </div>
</div>


<div class="modal fade" id="deleteScheduleModal" tabindex="-1">
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteScheduleModal');
        deleteModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            document.getElementById('delete-id').value = button.getAttribute('data-id');
            document.getElementById('delete-info').textContent = button.getAttribute('data-info');
        });


        const editModal = document.getElementById('editScheduleModal');
        editModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            document.getElementById('edit-id').value = button.dataset.id;
            document.getElementById('edit-matkul-id').value = button.dataset.matkulid;
            document.getElementById('edit-dosen-id').value = button.dataset.dosenid;
            document.getElementById('edit-hari').value = button.dataset.hari;
            document.getElementById('edit-jam-mulai').value = button.dataset.jammulai;
            document.getElementById('edit-jam-selesai').value = button.dataset.jamselesai;
        });
    });
</script>