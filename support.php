<?php
require_once __DIR__ . '/core/init.php';

// --- LOGIKA PHP DIMODIFIKASI UNTUK MENANGANI AJAX ---
$message = '';
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'unblock_request') {
        $email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
        $response = ['success' => false, 'message' => 'Terjadi kesalahan.'];

        if (empty($email)) {
            $response['message'] = 'Alamat email tidak boleh kosong.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Format email tidak valid.';
        } else {
            $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response['message'] = 'Alamat email ini tidak terdaftar di sistem kami.';
            } else {
                // Buat token dan kirim email
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 jam
                $pdo->prepare('INSERT INTO unblock_tokens (user_id, token, expires_at) VALUES (:uid, :token, :exp)')
                    ->execute(['uid' => $user['id'], 'token' => $token, 'exp' => $expiresAt]);

                $unblockLink = BASE_URL . 'unblock.php?token=' . urlencode($token);
                $subject = 'Permintaan Buka Blokir Akun SIAKAD';
                $body = "Halo,\r\n\r\nAnda meminta untuk membuka blokir akun. Klik tautan berikut untuk memulai proses permohonan:\r\n" . $unblockLink . "\r\n\r\nTautan ini berlaku selama 1 jam. Abaikan email ini jika Anda tidak merasa memintanya.";

                list($sentMail, $mailMsg) = send_siakad_email($user['email'], $subject, $body);
                if ($sentMail) {
                    $response['success'] = true;
                    $response['message'] = 'Berhasil! Tautan pemulihan telah meluncur ke alamat email Anda.';
                } else {
                    $response['message'] = 'Gagal mengirim email: ' . $mailMsg;
                }
            }
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            $message = $response['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Bantuan - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; overflow: hidden; }
        #support-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; background-size: 400% 400%; transition: background 1s ease-in-out; animation: gradient-animation 20s ease infinite; }
        .theme-pagi { background: linear-gradient(-45deg, #84fab0, #8fd3f4, #a1c4fd, #c2e9fb); }
        .theme-siang { background: linear-gradient(-45deg, #4facfe, #00f2fe, #43e97b, #38f9d7); }
        .theme-sore { background: linear-gradient(-45deg, #f6d365, #fda085, #ff9a9e, #fad0c4); }
        .theme-malam { background: linear-gradient(-45deg, #232526, #414345, #0f2027, #2c5364); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .support-card {
            width: 100%; max-width: 650px;
            border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            animation: fadeIn 0.8s ease-out forwards;
        }
        .theme-malam .support-card { background: rgba(30, 30, 30, 0.7); color: #f1f1f1; }
        .theme-malam .text-muted { color: #ccc !important; }
        .theme-malam .nav-pills .nav-link { color: #ccc; }
        .theme-malam .nav-pills .nav-link.active { background-color: rgba(var(--bs-primary-rgb), 0.3); color: #fff; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* Style untuk Tab Interface */
        .nav-pills .nav-link { border-radius: 12px; font-weight: 500; }
        .tab-content { min-height: 250px; }
        .tab-pane { animation: fadeIn 0.5s; }

        /* Style untuk Input Email (konsisten dengan login) */
        .input-wrapper { position: relative; margin-bottom: 1.5rem; transition: all 0.3s ease; }
        .input-wrapper .form-control { background-color: transparent !important; border: none !important; border-bottom: 2px solid #ccc !important; border-radius: 0 !important; box-shadow: none !important; padding-left: 2.5rem !important; }
        .input-wrapper i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #aaa; transition: all 0.3s ease; }
        .input-wrapper::after { content: ''; position: absolute; bottom: 0; left: 50%; width: 100%; height: 2px; background-color: var(--bs-primary); transform: translateX(-50%) scaleX(0); transition: transform 0.4s ease; }
        .input-wrapper:focus-within::after { transform: translateX(-50%) scaleX(1); }
        .input-wrapper:focus-within i { color: var(--bs-primary); transform: translateY(-50%) scale(1.1); }
        .theme-malam .input-wrapper .form-control { color: #fff !important; border-bottom-color: #555 !important; }
        
        .btn { border-radius: 12px; padding: 0.75rem; font-weight: 600; }
        
        /* Animasi konfirmasi */
        #success-animation { display: none; text-align: center; }
        #success-animation .fa-envelope-circle-check { font-size: 4rem; color: var(--bs-success); animation: popIn 0.5s ease-out; }
        @keyframes popIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>
    <div id="support-wrapper">
        <div class="support-card p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fa-solid fa-life-ring fa-3x text-primary mb-3"></i>
                <h3 class="fw-bold">Pusat Bantuan Akun</h3>
                <p class="text-muted">Akun Anda bermasalah? Kami siap membantu.</p>
            </div>

            <ul class="nav nav-pills nav-fill mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-unblock-tab" data-bs-toggle="pill" data-bs-target="#pills-unblock" type="button" role="tab">
                        <i class="fa-solid fa-key me-2"></i>Bantuan Mandiri
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-contact-tab" data-bs-toggle="pill" data-bs-target="#pills-contact" type="button" role="tab">
                        <i class="fa-solid fa-headset me-2"></i>Kontak Admin
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-unblock" role="tabpanel">
                    <div id="form-container">
                        <p class="text-center mb-4">Masukkan alamat email yang terdaftar pada akun Anda untuk menerima tautan pemulihan.</p>
                        <form id="supportForm">
                            <input type="hidden" name="action" value="unblock_request">
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" class="form-control" id="email" name="email" placeholder="contoh@email.com" required>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span class="btn-text">Kirim Tautan Pemulihan</span>
                                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div id="message-container" class="mt-3"></div>
                    <div id="success-animation" class="mt-3">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                        <p class="mt-2 fw-bold">Email Terkirim!</p>
                    </div>
                </div>

                <div class="tab-pane fade text-center" id="pills-contact" role="tabpanel">
                    <i class="fa-solid fa-phone-volume fa-3x text-secondary my-4"></i>
                    <p>Jika Anda memerlukan bantuan lebih lanjut atau tidak memiliki akses ke email, silakan hubungi administrator kami melalui telepon atau WhatsApp.</p>
                    <h4 class="fw-bold my-3"><a href="tel:+6281234567890" class="text-decoration-none">+62 813 97079456</a></h4>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-link">Kembali ke Halaman Login</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Logika Tema Dinamis ---
            const wrapper = document.getElementById('support-wrapper');
            function setDynamicTheme() {
                const hour = new Date().getHours();
                let theme = 'theme-siang';
                if (hour >= 5 && hour < 12) theme = 'theme-pagi'; 
                else if (hour >= 15 && hour < 19) theme = 'theme-sore'; 
                else if (hour >= 19 || hour < 5) theme = 'theme-malam';
                wrapper.className = theme;
            }
            setDynamicTheme();

            // --- Logika Form AJAX ---
            const form = document.getElementById('supportForm');
            const submitBtn = document.getElementById('submitBtn');
            const messageContainer = document.getElementById('message-container');
            const successAnimation = document.getElementById('success-animation');
            const formContainer = document.getElementById('form-container');

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const btnText = submitBtn.querySelector('.btn-text');
                const spinner = submitBtn.querySelector('.spinner-border');
                
                // Reset state
                messageContainer.innerHTML = '';
                successAnimation.style.display = 'none';
                submitBtn.disabled = true;
                btnText.textContent = 'Memproses...';
                spinner.classList.remove('d-none');

                try {
                    const formData = new FormData(form);
                    const response = await fetch('support.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        formContainer.style.display = 'none'; // Sembunyikan form
                        successAnimation.style.display = 'block'; // Tampilkan animasi
                        messageContainer.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    } else {
                        messageContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    }

                } catch (error) {
                    messageContainer.innerHTML = `<div class="alert alert-danger">Terjadi kesalahan. Silakan coba lagi.</div>`;
                } finally {
                    submitBtn.disabled = false;
                    btnText.textContent = 'Kirim Tautan Pemulihan';
                    spinner.classList.add('d-none');
                }
            });
        });
    </script>
</body>
</html>