<?php
$page_title = 'Manajemen Dosen';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit();
}

// ... (LOGIKA CREATE & DELETE TETAP SAMA SEPERTI SEBELUMNYA) ...
// LOGIKA CREATE: Menambahkan data ke 2 tabel (users & dosen)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_lecturer'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = 2; // Role ID untuk Dosen
    $nidn = trim($_POST['nidn']);
    $nama_lengkap = trim($_POST['nama_lengkap']);

    if (empty($email) || empty($password) || empty($nidn) || empty($nama_lengkap)) {
        $_SESSION['error_message'] = "Semua field harus diisi.";
    } else {
        $stmt_check = $pdo->prepare("SELECT u.id FROM users u WHERE u.email = ? UNION SELECT d.id FROM dosen d WHERE d.nidn = ?");
        $stmt_check->execute([$email, $nidn]);
        if ($stmt_check->fetch()) {
            $_SESSION['error_message'] = "Email atau NIDN sudah terdaftar.";
        } else {
            try {
                $pdo->beginTransaction();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt_user = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
                $stmt_user->execute([$nidn, $email, $hashed_password, $role_id]);
                $user_id = $pdo->lastInsertId();
                $stmt_dosen = $pdo->prepare("INSERT INTO dosen (user_id, nidn, nama_lengkap) VALUES (?, ?, ?)");
                $stmt_dosen->execute([$user_id, $nidn, $nama_lengkap]);
                $pdo->commit();
                $_SESSION['success_message'] = "Dosen baru berhasil ditambahkan.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Gagal menambahkan dosen: " . $e->getMessage();
            }
        }
    }
    header("Location: manage_lecturers.php");
    exit();
}

// =================================================================
// == LOGIKA BARU: UPDATE (EDIT DOSEN)                            ==
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_lecturer'])) {
    $user_id = $_POST['user_id'];
    $nidn = trim($_POST['nidn']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Opsional

    if (empty($nidn) || empty($nama_lengkap) || empty($email)) {
        $_SESSION['error_message'] = "NIDN, Nama Lengkap, dan Email tidak boleh kosong.";
    } else {
        // Cek duplikasi NIDN atau Email, kecuali untuk user itu sendiri
        $stmt_check = $pdo->prepare("
            (SELECT id FROM dosen WHERE nidn = :nidn AND user_id != :user_id)
            UNION
            (SELECT user_id FROM users WHERE email = :email AND id != :user_id)
        ");
        $stmt_check->execute([':nidn' => $nidn, ':email' => $email, ':user_id' => $user_id]);
        if ($stmt_check->fetch()) {
            $_SESSION['error_message'] = "NIDN atau Email sudah digunakan oleh dosen lain.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Update tabel dosen
                $stmt_dosen = $pdo->prepare("UPDATE dosen SET nidn = ?, nama_lengkap = ? WHERE user_id = ?");
                $stmt_dosen->execute([$nidn, $nama_lengkap, $user_id]);

                // 2. Update tabel users
                if (!empty($password)) { // Jika password diisi, update semua
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_user = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
                    $stmt_user->execute([$nidn, $email, $hashed_password, $user_id]);
                } else { // Jika password kosong, update email & username saja
                    $stmt_user = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                    $stmt_user->execute([$nidn, $email, $user_id]);
                }

                $pdo->commit();
                $_SESSION['success_message'] = "Data dosen berhasil diperbarui.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error_message'] = "Gagal memperbarui data: " . $e->getMessage();
            }
        }
    }
    header("Location: manage_lecturers.php");
    exit();
}


// LOGIKA DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_lecturer'])) {
    $user_id_to_delete = $_POST['user_id_to_delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id_to_delete]);
    $_SESSION['success_message'] = "Data dosen berhasil dihapus.";
    header("Location: manage_lecturers.php");
    exit();
}

// LOGIKA READ
$lecturers = $pdo->query("SELECT d.nidn, d.nama_lengkap, u.email, u.id as user_id FROM dosen d JOIN users u ON d.user_id = u.id ORDER BY d.nama_lengkap ASC")->fetchAll(PDO::FETCH_ASSOC);

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
    <h3 class="fs-4 mb-0">Data Dosen</h3>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerModal"><i class="bi bi-plus-circle me-2"></i>Tambah Dosen</button>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIP</th>
                        <th>Nama Lengkap</th>
                        <th>Email</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lecturers): $i = 1;
                        foreach ($lecturers as $lecturer): ?>
                            <tr>
                                <th><?= $i++; ?></th>
                                <td><?= htmlspecialchars($lecturer['nidn']); ?></td>
                                <td><?= htmlspecialchars($lecturer['nama_lengkap']); ?></td>
                                <td><?= htmlspecialchars($lecturer['email']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning edit-btn"
                                        data-bs-toggle="modal" data-bs-target="#editLecturerModal"
                                        data-userid="<?= $lecturer['user_id']; ?>"
                                        data-nidn="<?= htmlspecialchars($lecturer['nidn']); ?>"
                                        data-nama="<?= htmlspecialchars($lecturer['nama_lengkap']); ?>"
                                        data-email="<?= htmlspecialchars($lecturer['email']); ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteLecturerModal" data-userid="<?= $lecturer['user_id']; ?>" data-nama="<?= htmlspecialchars($lecturer['nama_lengkap']); ?>"><i class="bi bi-trash"></i> Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Belum ada data dosen.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form Tambah Dosen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" class="form-control" name="nama_lengkap" required></div>
                    <div class="mb-3"><label class="form-label">NIP</label><input type="text" class="form-control" name="nidn" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="add_lecturer" class="btn btn-primary">Simpan</button></div>
            </div>
        </form>
    </div>
</div>


<div class="modal fade" id="editLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Form Edit Dosen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit-user-id">
                    <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" class="form-control" name="nama_lengkap" id="edit-nama" required></div>
                    <div class="mb-3"><label class="form-label">NIP</label><input type="text" class="form-control" name="nidn" id="edit-nidn" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit-email" required></div>
                    <div class="mb-3"><label class="form-label">Password Baru</label><input type="password" class="form-control" name="password"><small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_lecturer" class="btn btn-primary">Update</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id_to_delete" id="delete-user-id">
                    <p>Yakin ingin menghapus data dosen <strong id="delete-nama"></strong>?</p>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="delete_lecturer" class="btn btn-danger">Ya, Hapus</button></div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = document.getElementById('deleteLecturerModal');
        deleteModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            document.getElementById('delete-user-id').value = button.dataset.userid;
            document.getElementById('delete-nama').textContent = button.dataset.nama;
        });

        const editModal = document.getElementById('editLecturerModal');
        editModal.addEventListener('show.bs.modal', e => {
            const button = e.relatedTarget;
            const userId = button.dataset.userid;
            const nidn = button.dataset.nidn;
            const nama = button.dataset.nama;
            const email = button.dataset.email;

            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-nidn').value = nidn;
            document.getElementById('edit-nama').value = nama;
            document.getElementById('edit-email').value = email;
        });
    });
</script>