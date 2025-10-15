<?php
$page_title = 'Manajemen Mata Kuliah';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

$errors = [];

// LOGIKA CREATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
            $kode_matkul = trim(strtoupper($_POST['kode_matkul']));
            $nama_matkul = trim($_POST['nama_matkul']);
            $sks = trim($_POST['sks']);

            if (empty($kode_matkul) || empty($nama_matkul) || empty($sks)) {
                        $errors[] = "Semua field harus diisi.";
            } else {
                        $stmt = $pdo->prepare("SELECT id FROM mata_kuliah WHERE kode_matkul = ?");
                        $stmt->execute([$kode_matkul]);
                        if ($stmt->fetch()) {
                                    $errors[] = "Kode Mata Kuliah sudah ada.";
                        } else {
                                    $sql = "INSERT INTO mata_kuliah (kode_matkul, nama_matkul, sks) VALUES (?, ?, ?)";
                                    $pdo->prepare($sql)->execute([$kode_matkul, $nama_matkul, $sks]);
                                    $_SESSION['success_message'] = "Mata kuliah baru berhasil ditambahkan!";
                                    header("Location: " . BASE_URL . "modules/admin/manage_courses.php");
                                    exit();
                        }
            }
}

// LOGIKA UPDATE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_course'])) {
            $id = $_POST['id'];
            $kode_matkul = trim(strtoupper($_POST['kode_matkul']));
            $nama_matkul = trim($_POST['nama_matkul']);
            $sks = trim($_POST['sks']);

            if (empty($kode_matkul) || empty($nama_matkul) || empty($sks)) {
                        $errors[] = "Semua field tidak boleh kosong.";
            } else {
                        $stmt = $pdo->prepare("SELECT id FROM mata_kuliah WHERE kode_matkul = ? AND id != ?");
                        $stmt->execute([$kode_matkul, $id]);
                        if ($stmt->fetch()) {
                                    $errors[] = "Kode Mata Kuliah sudah digunakan oleh mata kuliah lain.";
                        } else {
                                    $sql = "UPDATE mata_kuliah SET kode_matkul = ?, nama_matkul = ?, sks = ? WHERE id = ?";
                                    $pdo->prepare($sql)->execute([$kode_matkul, $nama_matkul, $sks, $id]);
                                    $_SESSION['success_message'] = "Data mata kuliah berhasil diperbarui!";
                                    header("Location: " . BASE_URL . "modules/admin/manage_courses.php");
                                    exit();
                        }
            }
}

// LOGIKA DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_course'])) {
            $id = $_POST['id_to_delete'];
            $sql = "DELETE FROM mata_kuliah WHERE id = ?";
            $pdo->prepare($sql)->execute([$id]);
            $_SESSION['success_message'] = "Mata kuliah berhasil dihapus.";
            header("Location: " . BASE_URL . "modules/admin/manage_courses.php");
            exit();
}

// LOGIKA READ
try {
            $courses = $pdo->query("SELECT * FROM mata_kuliah ORDER BY kode_matkul ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
            die("Error: Tidak bisa mengambil data. " . $e->getMessage());
}

require_once '../../includes/sidebar.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $_SESSION['success_message'];
                        unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                        <ul><?php foreach ($errors as $error): ?><li><?= $error; ?></li><?php endforeach; ?></ul>
            </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="fs-4 mb-0">Data Mata Kuliah</h3>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal"><i class="bi bi-plus-circle me-2"></i>Tambah Mata Kuliah</button>
</div>

<div class="card shadow-sm">
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                                <thead>
                                                            <tr>
                                                                        <th>No</th>
                                                                        <th>Kode MK</th>
                                                                        <th>Nama Mata Kuliah</th>
                                                                        <th>SKS</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($courses): $i = 1;
                                                                        foreach ($courses as $course): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($course['kode_matkul']); ?></td>
                                                                                                <td><?= htmlspecialchars($course['nama_matkul']); ?></td>
                                                                                                <td><?= htmlspecialchars($course['sks']); ?></td>
                                                                                                <td>
                                                                                                            <button class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal" data-bs-target="#editCourseModal" data-id="<?= $course['id']; ?>" data-kode="<?= htmlspecialchars($course['kode_matkul']); ?>" data-nama="<?= htmlspecialchars($course['nama_matkul']); ?>" data-sks="<?= $course['sks']; ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                                                                                            <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteCourseModal" data-id="<?= $course['id']; ?>" data-nama="<?= htmlspecialchars($course['nama_matkul']); ?>"><i class="bi bi-trash"></i> Hapus</button>
                                                                                                </td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="5" class="text-center">Belum ada data mata kuliah.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<div class="modal fade" id="addCourseModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title">Form Tambah Mata Kuliah</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <div class="mb-3"><label class="form-label">Kode Mata Kuliah</label><input type="text" class="form-control" name="kode_matkul" required></div>
                                                            <div class="mb-3"><label class="form-label">Nama Mata Kuliah</label><input type="text" class="form-control" name="nama_matkul" required></div>
                                                            <div class="mb-3"><label class="form-label">Jumlah SKS</label><input type="number" class="form-control" name="sks" required></div>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="add_course" class="btn btn-primary">Simpan</button></div>
                                    </div>
                        </form>
            </div>
</div>

<div class="modal fade" id="editCourseModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title" id="edit-modal-title">Edit Mata Kuliah</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <input type="hidden" name="id" id="edit-id">
                                                            <div class="mb-3"><label class="form-label">Kode Mata Kuliah</label><input type="text" class="form-control" name="kode_matkul" id="edit-kode" required></div>
                                                            <div class="mb-3"><label class="form-label">Nama Mata Kuliah</label><input type="text" class="form-control" name="nama_matkul" id="edit-nama" required></div>
                                                            <div class="mb-3"><label class="form-label">Jumlah SKS</label><input type="number" class="form-control" name="sks" id="edit-sks" required></div>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_course" class="btn btn-primary">Update</button></div>
                                    </div>
                        </form>
            </div>
</div>

<div class="modal fade" id="deleteCourseModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <input type="hidden" name="id_to_delete" id="delete-id">
                                                            <p>Apakah Anda yakin ingin menghapus mata kuliah <strong id="delete-nama"></strong>?</p>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="delete_course" class="btn btn-danger">Ya, Hapus</button></div>
                                    </div>
                        </form>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
            // JavaScript untuk mengisi data ke modal edit dan hapus
            document.addEventListener('DOMContentLoaded', function() {
                        const editModal = document.getElementById('editCourseModal');
                        editModal.addEventListener('show.bs.modal', e => {
                                    const button = e.relatedTarget;
                                    document.getElementById('edit-id').value = button.getAttribute('data-id');
                                    document.getElementById('edit-kode').value = button.getAttribute('data-kode');
                                    document.getElementById('edit-nama').value = button.getAttribute('data-nama');
                                    document.getElementById('edit-sks').value = button.getAttribute('data-sks');
                        });

                        const deleteModal = document.getElementById('deleteCourseModal');
                        deleteModal.addEventListener('show.bs.modal', e => {
                                    const button = e.relatedTarget;
                                    document.getElementById('delete-id').value = button.getAttribute('data-id');
                                    document.getElementById('delete-nama').textContent = button.getAttribute('data-nama');
                        });
            });
</script>