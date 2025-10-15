<?php
// [START] BAGIAN PHP UTAMA
require_once __DIR__ . '/../../core/init.php'; // Path ini penting untuk koneksi & session

// Cek jika user belum login atau bukan mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'mahasiswa') {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Ambil info mahasiswa dari session/DB
$user_id = $_SESSION['user_id'];
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    $stmt_mhs = $pdo->prepare("SELECT id, nim, nama_lengkap FROM mahasiswa WHERE user_id = :user_id");
    $stmt_mhs->execute(['user_id' => $user_id]);
    $mahasiswa_info = $stmt_mhs->fetch(PDO::FETCH_ASSOC);
    if (!$mahasiswa_info) die("Data mahasiswa tidak ditemukan.");
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Logika MFA
use RobThree\Auth\TwoFactorAuth;

$tfa = new TwoFactorAuth('SIAKAD UNIMED');
$message = '';
$error = '';
$user_mfa_enabled = false;

try {
    $stmt_check = $pdo->prepare("SELECT mfa_enabled FROM users WHERE id = :user_id");
    $stmt_check->execute(['user_id' => $user_id]);
    $user_mfa_enabled = $stmt_check->fetchColumn();
} catch (PDOException $e) {
    $error = "Gagal memeriksa status MFA.";
}

if (!isset($_SESSION['mfa_setup_secret']) && !$user_mfa_enabled) {
    $_SESSION['mfa_setup_secret'] = $tfa->createSecret();
}
$secret = $_SESSION['mfa_setup_secret'] ?? null;

$qrCodeUri = $secret ? $tfa->getQRCodeImageAsDataUri($mahasiswa_info['nama_lengkap'], $secret) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['activate_mfa'])) {
        $code = $_POST['code'] ?? '';
        if ($secret && $tfa->verifyCode($secret, $code)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET mfa_secret = :secret, mfa_enabled = 1 WHERE id = :user_id");
                $stmt->execute(['secret' => $secret, 'user_id' => $user_id]);
                unset($_SESSION['mfa_setup_secret']);
                $message = "Sukses! MFA telah diaktifkan di akun Anda.";
                $user_mfa_enabled = true; // Langsung update status di halaman
            } catch (PDOException $e) {
                $error = "Gagal menyimpan pengaturan MFA: " . $e->getMessage();
            }
        } else {
            $error = "Kode verifikasi salah. Silakan pindai ulang dan coba lagi.";
        }
    }
    if (isset($_POST['deactivate_mfa'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = :user_id");
            $stmt->execute(['user_id' => $user_id]);
            $message = "MFA telah dinonaktifkan dari akun Anda.";
            $user_mfa_enabled = false; // Langsung update status di halaman
        } catch (PDOException $e) {
            $error = "Gagal menonaktifkan MFA: " . $e->getMessage();
        }
    }
}
// [END] BAGIAN PHP UTAMA
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan MFA - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root { --border-radius: 15px; --transition-speed: 0.3s; }
        body { font-family: 'Poppins', sans-serif; margin: 0; color: #333; overflow-x: hidden; }
        #mfa-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; background-size: 400% 400%; transition: background 1s ease-in-out; animation: gradient-animation 20s ease infinite; }
        @keyframes gradient-animation { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        .theme-pagi { background: linear-gradient(-45deg, #e0f2f1, #b2dfdb, #80cbc4, #4db6ac); }
        .theme-siang { background: linear-gradient(-45deg, #e3f2fd, #bbdefb, #90caf9, #64b5f6); }
        .theme-sore { background: linear-gradient(-45deg, #fff3e0, #ffe0b2, #ffcc80, #ffb74d); }
        .theme-malam { background: linear-gradient(-45deg, #424242, #303030, #212121, #000000); color: #e0e0e0; }
        .card { width: 100%; max-width: 600px; background: rgba(255, 255, 255, 0.7); border: none; border-radius: var(--border-radius); box-shadow: 0 8px 32px 0 rgba(0,0,0,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); }
        .theme-malam .card { background: rgba(50,50,50,0.7); }
        .theme-malam .text-muted { color: #ccc !important; }
        .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .theme-malam .card-header { border-bottom-color: rgba(255,255,255,0.1); }
        .btn { border-radius: 12px; font-weight: 600; padding: .5rem 1rem; }
    </style>
    </head>
<body>
    <div id="mfa-wrapper">
        <div class="card p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fa-solid fa-shield-halved fa-3x text-primary mb-3"></i>
                <h3 class="fw-bold">Pengaturan Autentikasi Multi-Faktor (MFA)</h3>
            </div>
            
            <?php if ($message): ?> <div class="alert alert-success"><?php echo $message; ?></div> <?php endif; ?>
            <?php if ($error): ?> <div class="alert alert-danger"><?php echo $error; ?></div> <?php endif; ?>

            <?php if ($user_mfa_enabled): // Tampilan jika MFA sudah aktif ?>
                <div class="alert alert-success">
                    <h5 class="alert-heading"><i class="fa-solid fa-check-circle"></i> MFA Sudah Aktif</h5>
                    <p>Setiap kali login, Anda akan diminta kode dari aplikasi authenticator setelah verifikasi OTP.</p>
                </div>
                <p>Jika Anda ingin menonaktifkan fitur ini (tidak disarankan), klik tombol di bawah.</p>
                <form method="POST" onsubmit="return confirm('Anda yakin ingin menonaktifkan MFA? Ini akan mengurangi tingkat keamanan akun Anda.');">
                    <button type="submit" name="deactivate_mfa" class="btn btn-danger">Nonaktifkan MFA</button>
                    <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                </form>

            <?php else: // Tampilan untuk mengaktifkan MFA ?>
                <p>Tingkatkan keamanan akun Anda dengan memindai kode QR di bawah ini menggunakan aplikasi seperti Google Authenticator, Authy, atau lainnya.</p>
                <div class="text-center my-4">
                    <?php if ($qrCodeUri): ?> <img src="<?php echo $qrCodeUri; ?>" alt="QR Code MFA"> <?php else: ?> <div class="alert alert-danger">Gagal membuat QR Code.</div> <?php endif; ?>
                </div>
                <div class="alert alert-warning">
                    <h6 class="alert-heading">Tidak bisa memindai?</h6>
                    <p>Masukkan kode rahasia ini secara manual ke aplikasi Anda: <code class="user-select-all fs-5"><?php echo $secret; ?></code></p>
                </div>
                <form method="POST">
                    <p class="mt-4">Setelah memindai, masukkan 6 digit kode dari aplikasi Anda untuk menyelesaikan aktivasi.</p>
                    <div class="input-group mb-3 mx-auto" style="max-width: 350px;">
                        <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                        <input type="text" name="code" class="form-control form-control-lg text-center" placeholder="_ _ _ _ _ _" maxlength="6" pattern="\d{6}" required>
                        <button type="submit" name="activate_mfa" class="btn btn-primary">Aktifkan & Verifikasi</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                     <a href="index.php" class="btn btn-link">Nanti Saja</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const wrapper = document.getElementById('mfa-wrapper');
            function setDynamicTheme() {
                const hour = new Date().getHours();
                if(!wrapper) return;
                if (hour >= 5 && hour < 12) { wrapper.className = 'theme-pagi'; } 
                else if (hour >= 12 && hour < 15) { wrapper.className = 'theme-siang'; } 
                else if (hour >= 15 && hour < 19) { wrapper.className = 'theme-sore'; } 
                else { wrapper.className = 'theme-malam'; }
            }
            setDynamicTheme();
        });
    </script>
    </body>
</html>