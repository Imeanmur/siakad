<?php
require_once __DIR__ . '/core/init.php';

// Cek login & OTP
if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['otp_verified'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

use RobThree\Auth\TwoFactorAuth;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = implode('', $_POST['mfa_code'] ?? []);

    if (empty($code)) {
        $error = "Kode MFA tidak boleh kosong.";
    } else {
        $stmt = $pdo->prepare("SELECT mfa_secret FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['pending_user_id']]);
        $user = $stmt->fetch();

        if ($user && $user['mfa_secret']) {
            $tfa = new TwoFactorAuth('SIAKAD UNIMED');
            if ($tfa->verifyCode($user['mfa_secret'], $code)) {
                $_SESSION['user_id'] = $_SESSION['pending_user_id'];
                $_SESSION['role'] = $_SESSION['pending_role'];
                $_SESSION['mfa_verified_at'] = time();
                
                unset($_SESSION['pending_user_id'], $_SESSION['pending_role'], $_SESSION['otp'], $_SESSION['otp_verified']);

                $redirect_url = ($_SESSION['role'] === 'admin') ? 'modules/admin/' : (($_SESSION['role'] === 'dosen') ? 'modules/dosen/' : 'modules/mahasiswa/');
                header('Location: ' . BASE_URL . $redirect_url);
                exit();
            } else {
                $error = 'Kode MFA yang Anda masukkan salah. Coba lagi.';
            }
        } else {
            $error = 'Pengaturan MFA tidak ditemukan untuk akun ini. Silakan hubungi admin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi MFA - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            overflow: hidden;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* --- Background animasi neon --- */
        .animated-bg {
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, #00f2fe, #4facfe, #38f9d7, #43e97b);
            background-size: 300% 300%;
            animation: gradientShift 12s ease infinite;
            z-index: -1;
            filter: blur(60px);
            opacity: 0.8;
        }
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* --- Kartu MFA --- */
        .mfa-card {
            position: relative;
            width: 100%;
            max-width: 460px;
            border-radius: 25px;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2.5rem;
            animation: fadeInUp 1s ease both;
        }

        @keyframes fadeInUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .mfa-card h3 {
            font-weight: 700;
            color: #fff;
        }

        .mfa-card p {
            color: #e0e0e0;
            font-size: 0.95rem;
        }

        /* --- Input MFA --- */
        .mfa-input-container {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin: 2rem 0;
        }
        .mfa-input {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 1.6rem;
            font-weight: 600;
            border-radius: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.2);
            color: #fff;
            transition: all 0.3s ease;
            outline: none;
        }
        .mfa-input:focus {
            border-color: #00f2fe;
            box-shadow: 0 0 12px #00f2fe;
            transform: scale(1.1);
        }

        /* --- Tombol --- */
        .btn-primary {
            background: linear-gradient(90deg, #43e97b, #38f9d7);
            border: none;
            border-radius: 12px;
            font-weight: 600;
            padding: 0.8rem;
            transition: all 0.4s ease;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #00f2fe, #4facfe);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 242, 254, 0.3);
        }

        .alert {
            border-radius: 12px;
            background: rgba(255, 0, 0, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .back-link {
            color: #00f2fe;
            text-decoration: none;
            transition: color 0.3s;
        }
        .back-link:hover { color: #38f9d7; }

        /* --- Animasi ikon --- */
        .shield-icon {
            color: #00f2fe;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>

    <div class="mfa-card text-center">
        <i class="fa-solid fa-shield-halved fa-3x mb-3 shield-icon"></i>
        <h3>Verifikasi Langkah Kedua</h3>
        <p>Masukkan 6 digit kode dari aplikasi authenticator Anda untuk melanjutkan.</p>

        <?php if ($error): ?>
            <div class="alert mt-3"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="verify_mfa.php" method="POST" id="mfaForm">
            <div class="mfa-input-container">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="text" name="mfa_code[]" maxlength="1" class="mfa-input" required>
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <span class="btn-text">Verifikasi & Masuk</span>
                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>
        </form>

        <div class="mt-4">
            <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali ke Login</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('.mfa-input');
            const form = document.getElementById('mfaForm');

            inputs.forEach((input, i) => {
                input.addEventListener('input', () => {
                    if (input.value.length === 1 && i < inputs.length - 1) {
                        inputs[i + 1].focus();
                    }
                    if (i === inputs.length - 1 && input.value.length === 1) {
                        form.submit();
                    }
                });
                input.addEventListener('keydown', e => {
                    if (e.key === 'Backspace' && input.value.length === 0 && i > 0) {
                        inputs[i - 1].focus();
                    }
                });
            });

            inputs[0].addEventListener('paste', e => {
                e.preventDefault();
                const data = (e.clipboardData || window.clipboardData).getData('text').slice(0, 6);
                [...data].forEach((char, idx) => inputs[idx].value = char);
                if (data.length === 6) form.submit();
            });

            form.addEventListener('submit', () => {
                const button = form.querySelector('button[type="submit"]');
                const btnText = button.querySelector('.btn-text');
                const spinner = button.querySelector('.spinner-border');
                btnText.textContent = 'Memverifikasi...';
                spinner.classList.remove('d-none');
                button.disabled = true;
            });
        });
    </script>
</body>
</html>
