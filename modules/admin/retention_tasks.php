<?php
$page_title = 'Pembersihan Data (Retention)';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit();
}
require_once '../../includes/sidebar.php';
require_once '../../core/retention_config.php';

$retention = get_retention_settings();
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_retention'])) {
    $audit_days = max(1, (int)$_POST['audit_days']);
    $unblock_token_hours = max(1, (int)$_POST['unblock_token_hours']);
    set_retention_settings($audit_days, $unblock_token_hours);
    $retention = get_retention_settings();
    $msg = 'Pengaturan retensi berhasil diperbarui!';
}

// Jalankan kebijakan retensi dengan setting terbaru
if (!defined('RETENTION_AUDIT_DAYS')) {
    define('RETENTION_AUDIT_DAYS', $retention['audit_days']);
}
if (!defined('RETENTION_UNBLOCK_TOKEN_HOURS')) {
    define('RETENTION_UNBLOCK_TOKEN_HOURS', $retention['unblock_token_hours']);
}
dlm_run_retention($pdo);
?>
<div class="card my-4">
            <div class="card-body">
                        <h5 class="card-title">Pembersihan Selesai</h5>
                        <?php if ($msg): ?>
                            <div class="alert alert-success py-2"><?php echo htmlspecialchars($msg); ?></div>
                        <?php endif; ?>
                        <form method="post" class="mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Audit Log Disimpan (hari)</label>
                                    <input type="number" min="1" max="3650" class="form-control" name="audit_days" value="<?php echo (int)$retention['audit_days']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Unblock Token Disimpan (jam)</label>
                                    <input type="number" min="1" max="525600" class="form-control" name="unblock_token_hours" value="<?php echo (int)$retention['unblock_token_hours']; ?>">
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="set_retention" class="btn btn-warning">Atur Berapa Lama Data Disimpan</button>
                                </div>
                            </div>
                        </form>
                        <ul class="mb-0">
                            <li>Menghapus unblock token yang kedaluwarsa/terpakai (> <?php echo (int)$retention['unblock_token_hours']; ?> jam)</li>
                            <li>Menghapus audit logs lebih lama dari <?php echo (int)$retention['audit_days']; ?> hari</li>
                        </ul>
                        <div class="mt-3">
                            <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/admin/manage_unblock_requests.php">Kembali</a>
                        </div>
            </div>
</div>
<?php require_once '../../includes/footer.php'; ?>


