<?php
$page_title = 'Manajemen User';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'admin') {
      header('Location: ' . BASE_URL);
      exit();
}

$errors = [];

// =================================================================
// == LOGIKA CREATE (TAMBAH USER)                                 ==
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
      $username = trim($_POST['username']);
      $email = trim($_POST['email']);
      $password = $_POST['password'];
      $role_id = $_POST['role_id'];

      if (empty($username) || empty($email) || empty($password) || empty($role_id)) {
            $errors[] = "Semua field harus diisi.";
      } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                  $errors[] = "Nama atau Email sudah terdaftar.";
            } else {
                  $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                  $sql = "INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)";
                  $pdo->prepare($sql)->execute([$username, $email, $hashed_password, $role_id]);
                  $_SESSION['success_message'] = "User baru berhasil ditambahkan!";
                  header("Location: " . BASE_URL . "modules/admin/manage_users.php");
                  exit();
            }
      }
}

// =================================================================
// == LOGIKA UPDATE (EDIT USER)                                   ==
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
      $user_id = $_POST['user_id'];
      $username = trim($_POST['username']);
      $email = trim($_POST['email']);
      $password = $_POST['password']; // Bisa kosong
      $role_id = $_POST['role_id'];

      if (empty($username) || empty($email) || empty($role_id)) {
            $errors[] = "Username, email, dan role tidak boleh kosong.";
      } else {
            // Cek duplikasi, kecuali untuk user itu sendiri
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $user_id]);
            if ($stmt->fetch()) {
                  $errors[] = "Nama atau Email sudah digunakan oleh user lain.";
            } else {
                  // Jika password diisi, update password. Jika tidak, biarkan password lama.
                  if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET username = ?, email = ?, password = ?, role_id = ? WHERE id = ?";
                        $pdo->prepare($sql)->execute([$username, $email, $hashed_password, $role_id, $user_id]);
                  } else {
                        $sql = "UPDATE users SET username = ?, email = ?, role_id = ? WHERE id = ?";
                        $pdo->prepare($sql)->execute([$username, $email, $role_id, $user_id]);
                  }
                  $_SESSION['success_message'] = "Data user berhasil diperbarui!";
                  header("Location: " . BASE_URL . "modules/admin/manage_users.php");
                  exit();
            }
      }
}

// =================================================================
// == LOGIKA DELETE (HAPUS USER)                                  ==
// =================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
      $user_id = $_POST['user_id_to_delete'];

      // Untuk keamanan, jangan hapus user dengan ID 1 (biasanya super admin)
      if ($user_id == 1) {
            $_SESSION['error_message'] = "Error: Super Admin tidak dapat dihapus.";
      } else {
            $sql = "DELETE FROM users WHERE id = ?";
            $pdo->prepare($sql)->execute([$user_id]);
            $_SESSION['success_message'] = "User berhasil dihapus.";
      }

      header("Location: " . BASE_URL . "modules/admin/manage_users.php");
      exit();
}

// Mengambil semua data yang dibutuhkan untuk ditampilkan
try {
      $sql_users = "SELECT users.id, users.username, users.email, users.role_id, roles.role_name 
                  FROM users JOIN roles ON users.role_id = roles.id ORDER BY users.id DESC";
      $stmt_users = $pdo->query($sql_users);
      $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

      $sql_roles = "SELECT * FROM roles";
      $stmt_roles = $pdo->query($sql_roles);
      $roles = $stmt_roles->fetchAll(PDO::FETCH_ASSOC);
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
<?php if (isset($_SESSION['error_message'])): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error_message'];
            unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
            <ul><?php foreach ($errors as $error): ?><li><?= $error; ?></li><?php endforeach; ?></ul>
      </div>
<?php endif; ?>

<div class="row">
      <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                  <h3 class="fs-4 mb-0">Data Pengguna Sistem</h3>
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-circle me-2"></i>Tambah User
                  </button>
            </div>
      </div>
</div>

<div class="card shadow-sm">
      <div class="card-body">
            <div class="table-responsive">
                  <table class="table table-hover table-striped">
                        <thead>
                              <tr>
                                    <th>No</th>
                                    <th>Nama Lengkap</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Aksi</th>
                              </tr>
                        </thead>
                        <tbody>
                              <?php if (count($users) > 0): $i = 1;
                                    foreach ($users as $user): ?>
                                          <tr>
                                                <th><?= $i++; ?></th>
                                                <td><?= htmlspecialchars($user['username']); ?></td>
                                                <td><?= htmlspecialchars($user['email']); ?></td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars(ucfirst($user['role_name'])); ?></span></td>
                                                <td>
                                                      <button type="button" class="btn btn-sm btn-warning edit-btn"
                                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                            data-id="<?= $user['id']; ?>"
                                                            data-username="<?= htmlspecialchars($user['username']); ?>"
                                                            data-email="<?= htmlspecialchars($user['email']); ?>"
                                                            data-roleid="<?= $user['role_id']; ?>">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                      </button>
                                                      <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                            data-id="<?= $user['id']; ?>"
                                                            data-username="<?= htmlspecialchars($user['username']); ?>">
                                                            <i class="bi bi-trash"></i> Hapus
                                                      </button>
                                                </td>
                                          </tr>
                                    <?php endforeach;
                              else: ?>
                                    <tr>
                                          <td colspan="5" class="text-center">Belum ada data user.</td>
                                    </tr>
                              <?php endif; ?>
                        </tbody>
                  </table>
            </div>
      </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
            <form action="manage_users.php" method="POST">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="addUserModalLabel">Form Tambah User Baru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <div class="mb-3"><label for="username" class="form-label">Nama Lengkap</label><input type="text" class="form-control" name="username" required></div>
                              <div class="mb-3"><label for="email" class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                              <div class="mb-3"><label for="password" class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
                              <div class="mb-3"><label for="role_id" class="form-label">Role</label><select class="form-select" name="role_id" required>
                                          <option value="" disabled selected>-- Pilih Role --</option><?php foreach ($roles as $role): ?><option value="<?= $role['id']; ?>"><?= htmlspecialchars(ucfirst($role['role_name'])); ?></option><?php endforeach; ?>
                                    </select></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="add_user" class="btn btn-primary">Simpan</button></div>
                  </div>
            </form>
      </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
            <form action="manage_users.php" method="POST">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="editUserModalLabel">Form Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <input type="hidden" name="user_id" id="edit-user-id">
                              <div class="mb-3"><label for="edit-username" class="form-label">Nama Lengkap</label><input type="text" class="form-control" id="edit-username" name="username" required></div>
                              <div class="mb-3"><label for="edit-email" class="form-label">Email</label><input type="email" class="form-control" id="edit-email" name="email" required></div>
                              <div class="mb-3"><label for="edit-password" class="form-label">Password</label><input type="password" class="form-control" id="edit-password" name="password"><small class="form-text text-muted">Kosongkan jika tidak ingin mengubah password.</small></div>
                              <div class="mb-3"><label for="edit-role-id" class="form-label">Role</label><select class="form-select" id="edit-role-id" name="role_id" required><?php foreach ($roles as $role): ?><option value="<?= $role['id']; ?>"><?= htmlspecialchars(ucfirst($role['role_name'])); ?></option><?php endforeach; ?></select></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="update_user" class="btn btn-primary">Update</button></div>
                  </div>
            </form>
      </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
            <form action="manage_users.php" method="POST">
                  <div class="modal-content">
                        <div class="modal-header">
                              <h5 class="modal-title" id="deleteUserModalLabel">Konfirmasi Hapus</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                              <input type="hidden" name="user_id_to_delete" id="delete-user-id">
                              <p>Apakah Anda yakin ingin menghapus user <strong id="delete-username"></strong>?</p>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" name="delete_user" class="btn btn-danger">Ya, Hapus</button></div>
                  </div>
            </form>
      </div>
</div>

<?php
require_once '../../includes/footer.php';
?>

<script>
      document.addEventListener('DOMContentLoaded', function() {
            // Script untuk mengisi data ke Modal Edit
            const editUserModal = document.getElementById('editUserModal');
            editUserModal.addEventListener('show.bs.modal', function(event) {
                  const button = event.relatedTarget;
                  const id = button.getAttribute('data-id');
                  const username = button.getAttribute('data-username');
                  const email = button.getAttribute('data-email');
                  const roleId = button.getAttribute('data-roleid');

                  const modalTitle = editUserModal.querySelector('.modal-title');
                  const userIdInput = editUserModal.querySelector('#edit-user-id');
                  const usernameInput = editUserModal.querySelector('#edit-username');
                  const emailInput = editUserModal.querySelector('#edit-email');
                  const roleIdSelect = editUserModal.querySelector('#edit-role-id');

                  modalTitle.textContent = 'Edit User: ' + username;
                  userIdInput.value = id;
                  usernameInput.value = username;
                  emailInput.value = email;
                  roleIdSelect.value = roleId;
            });

            // Script untuk mengisi data ke Modal Hapus
            const deleteUserModal = document.getElementById('deleteUserModal');
            deleteUserModal.addEventListener('show.bs.modal', function(event) {
                  const button = event.relatedTarget;
                  const id = button.getAttribute('data-id');
                  const username = button.getAttribute('data-username');

                  const userIdInput = deleteUserModal.querySelector('#delete-user-id');
                  const usernameText = deleteUserModal.querySelector('#delete-username');

                  userIdInput.value = id;
                  usernameText.textContent = username;
            });
      });
</script>