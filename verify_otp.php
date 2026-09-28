<?php
require_once __DIR__ . '/core/init.php';

// Pastikan datang dari proses login dan punya sesi pending
if (!isset($_SESSION['pending_user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// --- LOGIKA PHP (TIDAK DIUBAH) ---
$message = '';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (($action === 'request' || $action === 'resend') && $is_ajax) {
        header('Content-Type: application/json');
        if (can_resend_otp()) {
            resend_otp();
            list($sent, $msg) = send_otp_to_user($pdo, $_SESSION['pending_user_id']);
            $response = ['success' => $sent, 'message' => $sent ? 'Kode OTP telah berhasil dikirim ulang.' : $msg];
        } else {        
            $response = ['success' => false, 'message' => 'Tunggu sebentar sebelum meminta OTP lagi.'];
        }
        echo json_encode($response);
        exit();
    }
    if ($action === 'verify') {
        $code = implode('', $_POST['otp'] ?? []);
        list($ok, $msg) = verify_otp_code($code);
        if ($ok) {
            $stmt = $pdo->prepare("SELECT mfa_enabled FROM users WHERE id = :user_id");
            $stmt->execute(['user_id' => $_SESSION['pending_user_id']]);
            $user_mfa_status = $stmt->fetch();
            if ($user_mfa_status && $user_mfa_status['mfa_enabled'] == 1) {
                $_SESSION['otp_verified'] = true;
                header('Location: ' . BASE_URL . 'verify_mfa.php');
                exit();
            } else {
                $_SESSION['user_id'] = $_SESSION['pending_user_id'];
                $_SESSION['role'] = $_SESSION['pending_role'];
                $_SESSION['otp_verified_at'] = time();
                unset($_SESSION['pending_user_id'], $_SESSION['pending_role'], $_SESSION['otp']);
                $redirect_url = ($_SESSION['role'] === 'admin') ? 'modules/admin/' : (($_SESSION['role'] === 'dosen') ? 'modules/dosen/' : 'modules/mahasiswa/');
                header('Location: ' . BASE_URL . $redirect_url);
                exit();
            }
        } else {
            $message = $msg;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; overflow: hidden; }
        #otp-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; background-size: 400% 400%; transition: background 1s ease-in-out; animation: gradient-animation 20s ease infinite; }
        .theme-pagi { background: linear-gradient(-45deg, #84fab0, #8fd3f4, #a1c4fd, #c2e9fb); }
        .theme-siang { background: linear-gradient(-45deg, #4facfe, #00f2fe, #43e97b, #38f9d7); }
        .theme-sore { background: linear-gradient(-45deg, #f6d365, #fda085, #ff9a9e, #fad0c4); }
        .theme-malam { background: linear-gradient(-45deg, #232526, #414345, #0f2027, #2c5364); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .otp-card { width: 100%; max-width: 500px; border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255, 255, 255, 0.2); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); animation: fadeIn 0.8s ease-out forwards; }
        .theme-malam .otp-card { background: rgba(30, 30, 30, 0.7); color: #f1f1f1; }
        .theme-malam .text-muted { color: #ccc !important; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .otp-input-container { display: flex; justify-content: space-between; gap: 10px; }
        .otp-input { width: 50px; height: 60px; text-align: center; font-size: 1.5rem; font-weight: 600; border: 2px solid #ddd; border-radius: 12px; transition: all 0.3s ease; background: #fff; }
        .otp-input:focus { border-color: var(--bs-primary); box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25); transform: scale(1.1); }
        .theme-malam .otp-input { background: #333; border-color: #555; color: #fff; }
        .btn { border-radius: 12px; padding: 0.75rem; font-weight: 600; }
        #countdown-timer { font-weight: 600; color: var(--bs-primary); }
    </style>
</head>
<body>
    <div id="otp-wrapper">
        <div class="otp-card p-4 p-md-5">
            <div class="text-center mb-3">
                <i class="fa-solid fa-shield-halved fa-3x text-primary mb-3"></i>
                <h3 class="fw-bold">VERIFIKASI AKUN ANDA</h3>
            </div>

            <div id="message-container" class="text-center">
                </div>

            <div class="text-center my-3 text-muted" id="timer-container">
                <span id="countdown-timer">01:00</span>
            </div>
            
            <form action="verify_otp.php" method="POST" id="otpForm">
                <input type="hidden" name="action" value="verify">
                <div class="otp-input-container mb-4">
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                    <input type="text" name="otp[]" class="form-control otp-input" maxlength="1" required>
                </div>
                <div class="d-grid d-none"> <button type="submit" class="btn btn-primary">Verifikasi</button>
                </div>
            </form>

            <div class="text-center mt-3 d-none" id="resend-container">
                Tidak menerima kode? 
                <button class="btn btn-link p-0" id="resend-btn">Kirim Ulang</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Logika Tema Dinamis (Tidak Berubah) ---
            const wrapper = document.getElementById('otp-wrapper');
            function setDynamicTheme() { /* ... kode tema dinamis ... */ }
            setDynamicTheme();

            // --- Logika Alur Baru Sesuai Keinginanmu ---
            const messageContainer = document.getElementById('message-container');
            const timerContainer = document.getElementById('timer-container');
            const countdownEl = document.getElementById('countdown-timer');
            const resendContainer = document.getElementById('resend-container');
            const resendBtn = document.getElementById('resend-btn');

            let timerInterval;

            function showMessage(text, type = 'info') {
                messageContainer.innerHTML = `<p class="text-muted">${text}</p>`;
                if(type === 'danger') {
                    messageContainer.innerHTML = `<div class="alert alert-danger">${text}</div>`;
                }
            }

            async function requestOtp(action) {
                try {
                    const response = await fetch('verify_otp.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: `action=${action}`
                    });
                    const data = await response.json();
                    showMessage(data.message, data.success ? 'success' : 'danger');
                } catch (error) {
                    showMessage('Terjadi kesalahan jaringan.', 'danger');
                }
            }
            
            function startCountdown() {
                clearInterval(timerInterval); // Hentikan timer sebelumnya jika ada
                timerContainer.classList.remove('d-none');
                resendContainer.classList.add('d-none');
                
                let timeLeft = 59;
                countdownEl.textContent = `00:${timeLeft.toString().padStart(2, '0')}`;

                timerInterval = setInterval(() => {
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        timerContainer.classList.add('d-none');
                        resendContainer.classList.remove('d-none');
                    } else {
                        timeLeft--;
                        countdownEl.textContent = `00:${timeLeft.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            }

            // Panggil fungsi untuk kirim OTP pertama kali dan mulai timer
            requestOtp('request'); 
            startCountdown();

            resendBtn.addEventListener('click', () => {
                requestOtp('resend');
                startCountdown();
            });

            // --- Logika Input OTP Cerdas (Tidak Berubah) ---
            const otpInputs = document.querySelectorAll('.otp-input');
            const otpForm = document.getElementById('otpForm');
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', () => {
                    if (input.value.length === 1 && index < otpInputs.length - 1) { otpInputs[index + 1].focus(); }
                    if (index === otpInputs.length - 1 && input.value.length === 1) { otpForm.submit(); }
                });
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Backspace' && input.value.length === 0 && index > 0) { otpInputs[index - 1].focus(); }
                });
            });
            otpInputs[0].addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = (e.clipboardData || window.clipboardData).getData('text').slice(0, 6);
                for (let i = 0; i < pasteData.length; i++) { otpInputs[i].value = pasteData[i]; }
                otpInputs[pasteData.length - 1]?.focus();
                if (pasteData.length === 6) { otpForm.submit(); }
            });
        });
    </script>
</body>
</html>