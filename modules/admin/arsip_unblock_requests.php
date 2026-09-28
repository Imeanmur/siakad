<?php
// Halaman untuk melihat dan restore arsip permohonan buka blokir
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL);
    exit();
}
require_once '../../includes/sidebar.php';
require_once '../../core/dlm.php';

// Proses restore
$restoreMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_id'])) {
    $id = (int)$_POST['restore_id'];
    if (dlm_restore_unblock_request($pdo, $id)) {
        $restoreMsg = 'Permohonan berhasil dikembalikan ke dashboard.';
    } else {
        $restoreMsg = 'Gagal mengembalikan permohonan.';
    }
}

$stmt = $pdo->query('SELECT * FROM unblock_requests_archive ORDER BY archived_at DESC');
$arsip = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card my-4">
    <div class="card-body">
        <h5 class="card-title">Arsip Permohonan Buka Blokir</h5>
        <?php if ($restoreMsg): ?>
            <div class="alert alert-info py-2"><?php echo htmlspecialchars($restoreMsg); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Alasan</th>
                        <th>Status</th>
                        <th>Diarsipkan Pada</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($arsip as $a): ?>
                    <tr>
                        <td><?php echo (int)$a['id']; ?></td>
                        <td><?php echo htmlspecialchars($a['nama']); ?></td>
                        <td><?php echo htmlspecialchars($a['nim']); ?></td>
                        <td><?php echo htmlspecialchars($a['alasan']); ?></td>
                        <td><?php echo htmlspecialchars($a['status']); ?></td>
                        <td><?php echo htmlspecialchars($a['archived_at']); ?></td>
                        <td>
                            <form method="post" style="display:inline" onsubmit="return confirm('Kembalikan permohonan ini ke dashboard?')">
                                <input type="hidden" name="restore_id" value="<?php echo (int)$a['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <a class="btn btn-secondary mt-2" href="<?php echo BASE_URL; ?>modules/admin/archive_tasks.php">Kembali</a>
    </div>
</div>
<?php require_once '../../includes/footer.php'; ?>
