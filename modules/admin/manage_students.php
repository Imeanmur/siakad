<?php
$page_title = 'Manajemen Mahasiswa';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

// LOGIKA CREATE MAHASISWA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_student'])) {
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role_id = 3; // Role ID untuk Mahasiswa

            $nim = trim($_POST['nim']);
            $nama_lengkap = trim($_POST['nama_lengkap']);
            $jurusan = trim($_POST['jurusan']);
            $angkatan = trim($_POST['angkatan']);

            if (empty($email) || empty($password) || empty($nim) || empty($nama_lengkap)) {
                        $_SESSION['error_message'] = "Field yang wajib tidak boleh kosong.";
            } else {
                        $stmt_check = $pdo->prepare("SELECT u.id FROM users u WHERE u.email = ? UNION SELECT m.id FROM mahasiswa m WHERE m.nim = ?");
                        $stmt_check->execute([$email, $nim]);
                        if ($stmt_check->fetch()) {
                                    $_SESSION['error_message'] = "Email atau NIM sudah terdaftar.";
                        } else {
                                    try {
                                                $pdo->beginTransaction();

                                                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                                $stmt_user = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
                                                $stmt_user->execute([$nim, $email, $hashed_password, $role_id]);

                                                $user_id = $pdo->lastInsertId();

                                                $stmt_mhs = $pdo->prepare("INSERT INTO mahasiswa (user_id, nim, nama_lengkap, jurusan, angkatan) VALUES (?, ?, ?, ?, ?)");
                                                $stmt_mhs->execute([$user_id, $nim, $nama_lengkap, $jurusan, $angkatan]);

                                                $pdo->commit();
                                                $_SESSION['success_message'] = "Mahasiswa baru berhasil ditambahkan.";
                                    } catch (Exception $e) {
                                                $pdo->rollBack();
                                                $_SESSION['error_message'] = "Gagal menambahkan mahasiswa: " . $e->getMessage();
                                    }
                        }
            }
            header("Location: " . BASE_URL . "modules/admin/manage_students.php");
            exit();
}

// LOGIKA DELETE MAHASISWA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_student'])) {
            $user_id_to_delete = $_POST['user_id_to_delete'];
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id_to_delete]);
            $_SESSION['success_message'] = "Data mahasiswa berhasil dihapus.";
            header("Location: " . BASE_URL . "modules/admin/manage_students.php");
            exit();
}

// LOGIKA READ MAHASISWA
try {
            $students = $pdo->query("
        SELECT m.id, m.nim, m.nama_lengkap, m.jurusan, m.angkatan, u.email, u.id as user_id
        FROM mahasiswa m JOIN users u ON m.user_id = u.id
        ORDER BY m.nama_lengkap ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
            die("Error: " . $e->getMessage());
}

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
            <h3 class="fs-4 mb-0">Data Mahasiswa</h3>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="bi bi-plus-circle me-2"></i>Tambah Mahasiswa</button>
</div>

<div class="card shadow-sm">
            <div class="card-body">
                        <div class="table-responsive">
                                    <table class="table table-hover">
                                                <thead>
                                                            <tr>
                                                                        <th>NO</th>
                                                                        <th>NIM</th>
                                                                        <th>Nama Lengkap</th>
                                                                        <th>Prodi</th>
                                                                        <th>Angkatan</th>
                                                                        <th>Email</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                            <?php if ($students): $i = 1;
                                                                        foreach ($students as $student): ?>
                                                                                    <tr>
                                                                                                <th><?= $i++; ?></th>
                                                                                                <td><?= htmlspecialchars($student['nim']); ?></td>
                                                                                                <td><?= htmlspecialchars($student['nama_lengkap']); ?></td>
                                                                                                <td><?= htmlspecialchars($student['jurusan']); ?></td>
                                                                                                <td><?= htmlspecialchars($student['angkatan']); ?></td>
                                                                                                <td><?= htmlspecialchars($student['email']); ?></td>
                                                                                                <td><button class="btn btn-sm btn-danger delete-btn" data-bs-toggle="modal" data-bs-target="#deleteStudentModal" data-userid="<?= $student['user_id']; ?>" data-nama="<?= htmlspecialchars($student['nama_lengkap']); ?>"><i class="bi bi-trash"></i> Hapus</button></td>
                                                                                    </tr>
                                                                        <?php endforeach;
                                                            else: ?>
                                                                        <tr>
                                                                                    <td colspan="7" class="text-center">Belum ada data mahasiswa.</td>
                                                                        </tr>
                                                            <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<div class="modal fade" id="addStudentModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title">Form Tambah Mahasiswa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <div class="mb-3"><label class="form-label">Nama Lengkap</label><input type="text" class="form-control" name="nama_lengkap" required></div>
                                                            <div class="mb-3"><label class="form-label">NIM</label><input type="text" class="form-control" name="nim" required></div>
                                                            <div class="mb-3"><label class="form-label">Prodi</label><input type="text" class="form-control" name="jurusan"></div>
                                                            <div class="mb-3"><label class="form-label">Angkatan</label><input type="number" class="form-control" name="angkatan" placeholder="Contoh: 2022"></div>
                                                            <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                                                            <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="add_student" class="btn btn-primary">Simpan</button></div>
                                    </div>
                        </form>
            </div>
</div>

<div class="modal fade" id="deleteStudentModal" tabindex="-1">
            <div class="modal-dialog">
                        <form method="POST">
                                    <div class="modal-content">
                                                <div class="modal-header">
                                                            <h5 class="modal-title">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                            <input type="hidden" name="user_id_to_delete" id="delete-user-id">
                                                            <p>Yakin ingin menghapus data mahasiswa <strong id="delete-nama"></strong>?</p>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="delete_student" class="btn btn-danger">Ya, Hapus</button></div>
                                    </div>
                        </form>
            </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script>
            document.addEventListener('DOMContentLoaded', function() {
                        const deleteModal = document.getElementById('deleteStudentModal');
                        deleteModal.addEventListener('show.bs.modal', e => {
                                    const button = e.relatedTarget;
                                    document.getElementById('delete-user-id').value = button.getAttribute('data-userid');
                                    document.getElementById('delete-nama').textContent = button.getAttribute('data-nama');
                        });
            });
</script>