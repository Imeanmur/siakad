<?php
$page_title = 'Dashboard Mahasiswa';
require_once '../../includes/header.php';

if ($_SESSION['role'] !== 'mahasiswa') {
            header('Location: ' . BASE_URL);
            exit();
}

$announcements = $pdo->query("
    SELECT judul, isi, created_at FROM pengumuman 
    WHERE target_role = 'mahasiswa' OR target_role = 'semua'
    ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../includes/sidebar.php';
?>

<div class="alert alert-info">Selamat Datang di Dasbor Mahasiswa!</div>

<h4 class="mt-4">Keamanan Akun</h4>
<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-1">Autentikasi Multi-Faktor (MFA)</h5>
            <p class="card-text text-muted mb-0">Tingkatkan keamanan akun Anda dengan lapisan verifikasi tambahan dari aplikasi authenticator.</p>
        </div>
        <a href="setup_mfa.php" class="btn btn-primary ms-3">Atur MFA</a>
    </div>
</div>
<h4 class="mt-4">Pengumuman Terbaru</h4>
<?php if ($announcements): foreach ($announcements as $ann): ?>
                        <div class="card mb-3">
                                    <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($ann['judul']); ?></h5>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($ann['isi'])); ?></p>
                                                <p class="card-text"><small class="text-muted">Dipublikasikan pada: <?= date('d M Y, H:i', strtotime($ann['created_at'])); ?></small></p>
                                    </div>
                        </div>
            <?php endforeach;
else: ?>
            <p>Belum ada pengumuman untuk Anda.</p>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>