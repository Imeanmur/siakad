<?php
$page_title = 'Arsip Permohonan Buka Blokir';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit();
}
require_once '../../includes/sidebar.php';

$archiveCount = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['arsipkan'])) {
    require_once '../../core/dlm.php';
    $archiveCount = dlm_archive_unblock_requests($pdo);
}
?>
<div class="card my-4">
            <div class="card-body">
                        <h5 class="card-title">Proses Arsip</h5>
                        <form method="post" onsubmit="return confirm('Arsipkan permohonan buka blokir yang sudah selesai (approved/denied) dan sudah lama?')">
                            <button type="submit" name="arsipkan" class="btn btn-warning mb-2">Arsipkan Permohonan Lama</button>
                        </form>
                        <?php if ($archiveCount !== null): ?>
                            <div class="alert alert-info py-2">Dipindahkan ke arsip: <strong><?php echo (int)$archiveCount; ?></strong> permohonan.</div>
                        <?php endif; ?>
                        <div class="mt-3">
                            <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/admin/manage_unblock_requests.php">Kembali</a>
                            <a class="btn btn-info ms-2" href="<?php echo BASE_URL; ?>modules/admin/arsip_unblock_requests.php">Lihat Arsip & Restore</a>
                            <button id="secureEraseBtn" class="btn btn-danger ms-2">Secure Erase Arsip</button>
                        </div>
                        <script>
                        document.getElementById('secureEraseBtn').onclick = function() {
                            if (confirm('Yakin ingin menghancurkan seluruh data arsip secara permanen? Tindakan ini tidak dapat dibatalkan!')) {
                                fetch('<?php echo BASE_URL; ?>modules/admin/secure_erase_archive.php', {
                                    method: 'POST',
                                    credentials: 'same-origin'
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        alert(data.message);
                                        location.reload();
                                    } else {
                                        alert(data.error || 'Gagal menghapus data arsip.');
                                    }
                                })
                                .catch(() => alert('Terjadi kesalahan koneksi.'));
                            }
                        };
                        </script>
            </div>
</div>
<?php require_once '../../includes/footer.php'; ?>


