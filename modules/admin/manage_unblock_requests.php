<?php
$page_title = 'Permohonan Buka Blokir';
require_once '../../includes/header.php';
if ($_SESSION['role'] !== 'admin') {
            header('Location: ' . BASE_URL);
            exit();
}

// Proses aksi approve/deny
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
            $action = $_POST['action'];
            $requestId = (int)$_POST['request_id'];
            if (in_array($action, ['approve', 'deny'], true)) {
                        if ($action === 'approve') {
                                    // Set user aktif kembali dan reset counter
                                    $stmt = $pdo->prepare('SELECT user_id FROM unblock_requests WHERE id = :id LIMIT 1');
                                    $stmt->execute(['id' => $requestId]);
                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                    if ($row) {
                                                $pdo->prepare('UPDATE users SET is_active = 1, failed_attempts = 0, blocked_at = NULL WHERE id = :uid')
                                                    ->execute(['uid' => $row['user_id']]);
                                    }
                                    $pdo->prepare('UPDATE unblock_requests SET status = "approved", processed_at = NOW(), processed_by = :admin WHERE id = :id')
                                        ->execute(['admin' => $_SESSION['user_id'], 'id' => $requestId]);
                        } else {
                                    $pdo->prepare('UPDATE unblock_requests SET status = "denied", processed_at = NOW(), processed_by = :admin WHERE id = :id')
                                        ->execute(['admin' => $_SESSION['user_id'], 'id' => $requestId]);
                        }
            }
}

require_once '../../includes/sidebar.php';

// Ambil daftar permohonan
$stmt = $pdo->query('SELECT ur.id, ur.user_id, ur.nama, ur.nim, ur.alasan, ur.status, ur.created_at, u.email FROM unblock_requests ur JOIN users u ON ur.user_id = u.id ORDER BY ur.created_at DESC');
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-end gap-2 my-3">
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo BASE_URL; ?>modules/admin/export_unblock_requests.php">Export CSV</a>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo BASE_URL; ?>modules/admin/archive_tasks.php">Arsipkan Data Lama</a>
            <a class="btn btn-sm btn-outline-danger" href="<?php echo BASE_URL; ?>modules/admin/retention_tasks.php">Jalankan Retention</a>
</div>

<div class="card my-4">
            <div class="card-body">
                        <h5 class="card-title mb-3">Daftar Permohonan</h5>
                        <div class="table-responsive">
                                    <table class="table table-striped table-bordered align-middle">
                                                <thead>
                                                            <tr>
                                                                        <th>#</th>
                                                                        <th>Tanggal</th>
                                                                        <th>Nama</th>
                                                                        <th>NIM</th>
                                                                        <th>Email</th>
                                                                        <th>Alasan</th>
                                                                        <th>Status</th>
                                                                        <th>Aksi</th>
                                                            </tr>
                                                </thead>
                                                <tbody>
                                                <?php if (empty($requests)): ?>
                                                            <tr><td colspan="8" class="text-center">Belum ada permohonan.</td></tr>
                                                <?php else: ?>
                                                            <?php foreach ($requests as $i => $r): ?>
                                                                        <tr>
                                                                                    <td><?php echo $i + 1; ?></td>
                                                                                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($r['nama']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($r['nim']); ?></td>
                                                                                    <td><?php echo htmlspecialchars($r['email']); ?></td>
                                                                                    <td style="max-width: 360px; white-space: pre-wrap;"><?php echo htmlspecialchars($r['alasan']); ?></td>
                                                                                    <td>
                                                                                                <?php if ($r['status'] === 'approved'): ?>
                                                                                                            <span class="badge bg-success">Disetujui</span>
                                                                                                <?php elseif ($r['status'] === 'denied'): ?>
                                                                                                            <span class="badge bg-danger">Ditolak</span>
                                                                                                <?php else: ?>
                                                                                                            <span class="badge bg-warning text-dark">Menunggu</span>
                                                                                                <?php endif; ?>
                                                                                    </td>
                                                                                    <td>
                                                                                                <?php if ($r['status'] === 'pending'): ?>
                                                                                                            <form method="POST" class="d-inline">
                                                                                                                        <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                                                                                        <input type="hidden" name="action" value="approve">
                                                                                                                        <button type="submit" class="btn btn-sm btn-success">Setujui & Buka Blokir</button>
                                                                                                            </form>
                                                                                                            <form method="POST" class="d-inline ms-1">
                                                                                                                        <input type="hidden" name="request_id" value="<?php echo (int)$r['id']; ?>">
                                                                                                                        <input type="hidden" name="action" value="deny">
                                                                                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Tolak</button>
                                                                                                            </form>
                                                                                                <?php else: ?>
                                                                                                            <em>-</em>
                                                                                                <?php endif; ?>
                                                                                    </td>
                                                                        </tr>
                                                            <?php endforeach; ?>
                                                <?php endif; ?>
                                                </tbody>
                                    </table>
                        </div>
            </div>
</div>

<?php
require_once '../../includes/footer.php';
?>


