<?php
$page_title = 'Pembersihan Data (Retention)';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}
require_once '../../includes/sidebar.php';

// Jalankan kebijakan retensi
dlm_run_retention($pdo);
?>
<div class="card my-4">
            <div class="card-body">
                        <h5 class="card-title">Pembersihan Selesai</h5>
                        <ul class="mb-0">
                                    <li>Menghapus unblock token yang kedaluwarsa/terpakai (> <?php echo (int)RETENTION_UNBLOCK_TOKEN_HOURS; ?> jam)</li>
                                    <li>Menghapus audit logs lebih lama dari <?php echo (int)RETENTION_AUDIT_DAYS; ?> hari</li>
                        </ul>
                        <div class="mt-3">
                                    <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/admin/manage_unblock_requests.php">Kembali</a>
                        </div>
            </div>
</div>
<?php require_once '../../includes/footer.php'; ?>


