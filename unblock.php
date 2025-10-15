<?php
require_once __DIR__ . '/core/init.php';

// Variabel untuk menyimpan hasil verifikasi yang akan dikirim ke JavaScript
$result = [
    'status' => 'pending', // Status awal
    'message' => '',
    'redirect_url' => ''
];

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';

if (empty($token)) {
    $result['status'] = 'invalid';
    $result['message'] = 'Tautan verifikasi tidak lengkap atau token tidak ditemukan.';
} else {
    $stmt = $pdo->prepare('SELECT ut.id, ut.user_id, ut.expires_at, ut.used FROM unblock_tokens ut WHERE ut.token = :t LIMIT 1');
    $stmt->execute(['t' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $result['status'] = 'not_found';
        $result['message'] = 'Token tidak ditemukan di sistem. Pastikan Anda menyalin tautan lengkap dari email.';
    } elseif ((int)$row['used'] === 1) {
        $result['status'] = 'used';
        $result['message'] = 'Token ini sudah pernah digunakan. Setiap token hanya berlaku untuk satu kali permohonan.';
    } elseif (time() > strtotime($row['expires_at'])) {
        $result['status'] = 'expired';
        $result['message'] = 'Token telah kedaluwarsa. Silakan minta tautan baru untuk melanjutkan.';
    } else {
        // Token valid!
        $result['status'] = 'success';
        $result['message'] = 'Verifikasi token berhasil! Anda akan segera diarahkan ke halaman permohonan.';
        $result['redirect_url'] = 'unblock_request/index.php?token=' . urlencode($token);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Tautan - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; overflow: hidden; }
        #verification-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; background-size: 400% 400%; transition: background 1s ease-in-out; animation: gradient-animation 20s ease infinite; }
        .theme-pagi { background: linear-gradient(-45deg, #84fab0, #8fd3f4, #a1c4fd, #c2e9fb); }
        .theme-siang { background: linear-gradient(-45deg, #4facfe, #00f2fe, #43e97b, #38f9d7); }
        .theme-sore { background: linear-gradient(-45deg, #f6d365, #fda085, #ff9a9e, #fad0c4); }
        .theme-malam { background: linear-gradient(-45deg, #232526, #414345, #0f2027, #2c5364); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .verification-card {
            width: 100%; max-width: 600px;
            border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            animation: fadeIn 0.8s ease-out forwards;
        }
        .theme-malam .verification-card { background: rgba(30, 30, 30, 0.7); color: #f1f1f1; }
        .theme-malam .text-muted { color: #ccc !important; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* Style untuk Proses Verifikasi */
        .verification-steps { list-style: none; padding: 0; }
        .verification-steps li {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateY(10px);
            animation: slideUp 0.5s ease-out forwards;
        }
        .verification-steps li:last-child { border-bottom: none; }
        .theme-malam .verification-steps li { border-color: rgba(255, 255, 255, 0.1); }

        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        .status-icon {
            width: 25px;
            text-align: center;
            margin-right: 1rem;
        }
        .status-icon .fa-check-circle { color: var(--bs-success); }
        .status-icon .fa-times-circle { color: var(--bs-danger); }
        .status-icon .fa-spinner { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* Style untuk Kartu Hasil */
        #result-container { display: none; text-align: center; animation: fadeIn 0.5s ease-out forwards; }
        .result-icon { font-size: 4rem; margin-bottom: 1rem; }
        .result-success { color: var(--bs-success); }
        .result-warning { color: var(--bs-warning); }
        .result-danger { color: var(--bs-danger); }
        .btn { border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; }
    </style>
</head>
<body>
    <div id="verification-wrapper">
        <div class="verification-card p-4 p-md-5">
            <h4 class="fw-bold mb-3">Memverifikasi Tautan Buka Blokir...</h4>
            <ul class="verification-steps">
                </ul>

            <div id="result-container" class="mt-4">
                </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mengambil data hasil dari PHP
        const verificationResult = <?php echo json_encode($result); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            // --- Logika Tema Dinamis ---
            const wrapper = document.getElementById('verification-wrapper');
            function setDynamicTheme() {
                const hour = new Date().getHours();
                let theme = 'theme-siang';
                if (hour >= 5 && hour < 12) { theme = 'theme-pagi'; } 
                else if (hour >= 15 && hour < 19) { theme = 'theme-sore'; } 
                else if (hour >= 19 || hour < 5) { theme = 'theme-malam'; }
                wrapper.className = theme;
            }
            setDynamicTheme();

            const stepsContainer = document.querySelector('.verification-steps');
            const resultContainer = document.getElementById('result-container');
            const steps = [
                { id: 'validate', text: 'Memvalidasi format token...' },
                { id: 'find', text: 'Mencari token di sistem...' },
                { id: 'check_used', text: 'Memeriksa status penggunaan...' },
                { id: 'check_expiry', text: 'Memeriksa masa berlaku...' }
            ];

            function createStepElement(step) {
                const li = document.createElement('li');
                li.innerHTML = `
                    <span class="status-icon">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                    <span>${step.text}</span>
                `;
                li.id = `step-${step.id}`;
                return li;
            }
            
            function updateStepStatus(stepId, isSuccess) {
                const stepElement = document.getElementById(`step-${stepId}`);
                const iconElement = stepElement.querySelector('.status-icon i');
                iconElement.classList.remove('fa-spinner', 'fa-spin');
                iconElement.classList.add(isSuccess ? 'fa-check-circle' : 'fa-times-circle');
            }

            function showResult() {
                let iconClass = '', title = '', button = '';

                switch(verificationResult.status) {
                    case 'success':
                        iconClass = 'fa-solid fa-shield-check result-success';
                        title = 'Token Valid!';
                        break;
                    case 'expired':
                        iconClass = 'fa-solid fa-calendar-times result-warning';
                        title = 'Token Kedaluwarsa!';
                        button = `<a href="support.php" class="btn btn-warning mt-3">Minta Tautan Baru</a>`;
                        break;
                    case 'used':
                        iconClass = 'fa-solid fa-ban result-danger';
                        title = 'Token Telah Digunakan!';
                        button = `<a href="support.php" class="btn btn-danger mt-3">Minta Tautan Baru</a>`;
                        break;
                    default: // invalid, not_found
                        iconClass = 'fa-solid fa-triangle-exclamation result-danger';
                        title = 'Token Tidak Valid!';
                        button = `<a href="support.php" class="btn btn-danger mt-3">Minta Tautan Baru</a>`;
                }

                resultContainer.innerHTML = `
                    <i class="${iconClass} result-icon"></i>
                    <h5 class="fw-bold">${title}</h5>
                    <p class="text-muted">${verificationResult.message}</p>
                    ${button}
                    <a href="login.php" class="btn btn-link mt-3">Kembali ke Login</a>
                `;
                resultContainer.style.display = 'block';

                if (verificationResult.status === 'success') {
                    setTimeout(() => {
                        window.location.href = verificationResult.redirect_url;
                    }, 2500); // Tunggu 2.5 detik sebelum redirect
                }
            }

            async function runVerificationAnimation() {
                for (let i = 0; i < steps.length; i++) {
                    await new Promise(resolve => setTimeout(resolve, 600)); // Jeda antar langkah
                    
                    const step = steps[i];
                    stepsContainer.appendChild(createStepElement(step));

                    let isSuccess = true;
                    if ((step.id === 'find' && verificationResult.status === 'not_found') ||
                        (step.id === 'check_used' && verificationResult.status === 'used') ||
                        (step.id === 'check_expiry' && verificationResult.status === 'expired')) {
                        isSuccess = false;
                    }
                    
                    await new Promise(resolve => setTimeout(resolve, 400)); // Jeda untuk animasi spin
                    updateStepStatus(step.id, isSuccess);

                    if (!isSuccess) break; // Hentikan animasi jika ada langkah yang gagal
                }
                
                await new Promise(resolve => setTimeout(resolve, 500));
                showResult();
            }

            runVerificationAnimation();
        });
    </script>
</body>
</html>