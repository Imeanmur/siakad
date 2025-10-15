<?php
$page_title = 'Manajemen Pengumuman';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

$admin_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['add_announcement']) || isset($_POST['update_announcement']))) {
            $judul = trim($_POST['judul']);
            $isi = trim($_POST['isi']);
            $target_role = $_POST['target_role'];
            $id = $_POST['id'] ?? null;

            if (empty($judul) || empty($isi) || empty($target_role)) {
                        $_SESSION['error_message'] = "Semua field wajib diisi.";
            } else {
                        if (isset($_POST['update_announcement'])) {
                                    $sql = "UPDATE pengumuman SET judul = ?, isi = ?, target_role = ? WHERE id = ?";
                                    $pdo->prepare($sql)->execute([$judul, $isi, $target_role, $id]);
                                    $_SESSION['success_message'] = "Pengumuman berhasil diperbarui.";
                        } else {
                                    $sql = "INSERT INTO pengumuman (judul, isi, target_role, penulis_id) VALUES (?, ?, ?, ?)";
                                    $pdo->prepare($sql)->execute([$judul, $isi, $target_role, $admin_user_id]);
                                    $_SESSION['success_message'] = "Pengumuman berhasil dipublikasikan.";
                        }
            }
            header("Location: manage_announcements.php");
            exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_announcement'])) {
            $id = $_POST['id_to_delete'];
            $pdo->prepare("DELETE FROM pengumuman WHERE id = ?")->execute([$id]);
            $_SESSION['success_message'] = "Pengumuman berhasil dihapus.";
            header("Location: manage_announcements.php");
            exit();
}

$announcements = $pdo->query("SELECT * FROM pengumuman ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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
            <h3 class="fs-4 mb-0">Kelola Pengumuman</h3>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal" id="add-btn"><i class="bi bi-plus-circle me-2"></i>Buat Pengumuman</button>
</div>

<div class="card shadow-sm">
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-hover">
                                                <thead>
                                                            <tr>
                                                                        <th>No</th>
                                                                        <th>Judul</th>
                                                                        <th>Target</th>
                                                                        <th>Tanggal Publikasi</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($announcements): $i = 1;
                                                                        foreach ($announcements as $ann): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($ann['judul']); ?></td>
                                                                                                <td><span class="badge bg-secondary"><?= ucfirst($ann['target_role']); ?></span></td>
                                                                                                <td><?= date('d M Y, H:i', strtotime($ann['created_at'])); ?></td>
                                                                                                <td>
                                                                                                            <button class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal" data-bs-target="#formModal" data-id="<?= $ann['id']; ?>" data-judul="<?= htmlspecialchars($ann['judul']); ?>" data-isi="<?= htmlspecialchars($ann['isi']); ?>" data-target="<?= $ann['target_role']; ?>"><i class="bi bi-pencil-square"></i> Edit</button>
                                                                                                            <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $ann['id']; ?>" data-judul="<?= htmlspecialchars($ann['judul']); ?>"><i class="bi bi-trash"></i> Hapus</button>
                                                                                                </td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="5" class="text-center">Belum ada pengumuman.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<div class="modal fade" id="formModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title" id="modal-title">Form Pengumuman</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <input type="hidden" name="id" id="edit-id">
                                                            <div class="mb-3"><label class="form-label">Judul</label><input type="text" class="form-control" name="judul" id="edit-judul" required></div>
                                                            <div class="mb-3"><label class="form-label">Isi Pengumuman</label><textarea class="form-control" name="isi" id="edit-isi" rows="5" required></textarea></div>
                                                            <div class="mb-3"><label class="form-label">Tujukan Untuk</label><select class="form-select" name="target_role" id="edit-target" required>
                                                                                    <option value="semua">Semua</option>
                                                                                    <option value="dosen">Dosen</option>
                                                                                    <option value="mahasiswa">Mahasiswa</option>
                                                                        </select></div>
                                                </div>
                                                <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" name="add_announcement" id="add-submit-btn" class="btn btn-primary">Publikasikan</button>
                                                            <button type="submit" name="update_announcement" id="update-submit-btn" class="btn btn-primary d-none">Update</button>
                                                </div>
                                    </div>
                        </form>
            </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <input type="hidden" name="id_to_delete" id="delete-id">
                                                            <p>Yakin ingin menghapus pengumuman <strong id="delete-judul"></strong>?</p>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="delete_announcement" class="btn btn-danger">Ya, Hapus</button></div>
                                    </div>
                        </form>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
            document.addEventListener('DOMContentLoaded', function() {
                        const modal = document.getElementById('formModal');
                        modal.addEventListener('show.bs.modal', e => {
                                    const button = e.relatedTarget;
                                    const isEdit = button.classList.contains('edit-btn');

                                    document.getElementById('edit-id').value = isEdit ? button.dataset.id : '';
                                    document.getElementById('edit-judul').value = isEdit ? button.dataset.judul : '';
                                    document.getElementById('edit-isi').value = isEdit ? button.dataset.isi : '';
                                    document.getElementById('edit-target').value = isEdit ? button.dataset.target : 'semua';

                                    document.getElementById('modal-title').textContent = isEdit ? 'Edit Pengumuman' : 'Buat Pengumuman Baru';
                                    document.getElementById('add-submit-btn').classList.toggle('d-none', isEdit);
                                    document.getElementById('update-submit-btn').classList.toggle('d-none', !isEdit);
                        });

                        const deleteModal = document.getElementById('deleteModal');
                        deleteModal.addEventListener('show.bs.modal', e => {
                                    document.getElementById('delete-id').value = e.relatedTarget.dataset.id;
                                    document.getElementById('delete-judul').textContent = e.relatedTarget.dataset.judul;
                        });
            });
</script>