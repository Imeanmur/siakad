<?php
$page_title = 'Arsip Permohonan Buka Blokir';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}
require_once '../../includes/sidebar.php';

// Jalankan arsip
$count = dlm_archive_unblock_requests($pdo);
?>
<div class="card my-4">
            <div class="card-body">
                        <h5 class="card-title">Proses Arsip</h5>
                        <p class="mb-0">Dipindahkan ke arsip: <strong><?php echo (int)$count; ?></strong> permohonan.</p>
                        <div class="mt-3">
                                    <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/admin/manage_unblock_requests.php">Kembali</a>
                        </div>
            </div>
</div>
<?php require_once '../../includes/footer.php'; ?>


