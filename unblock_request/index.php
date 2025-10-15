<?php
// Form pengajuan unblock setelah klik link email
require_once __DIR__ . '/../core/init.php';

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
$status_message = '';
$show_wizard = false;
$submission_success = false;
$user_email = '';

if ($token === '') {
    $status_message = 'Token tidak valid atau tidak ditemukan.';
} else {
    $stmt = $pdo->prepare('SELECT ut.id, ut.user_id, ut.expires_at, ut.used, u.email FROM unblock_tokens ut JOIN users u ON ut.user_id = u.id WHERE ut.token = :t LIMIT 1');
    $stmt->execute(['t' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) { $status_message = 'Token tidak ditemukan atau sudah digunakan.'; } 
    elseif ((int)$row['used'] === 1) { $status_message = 'Token ini sudah pernah digunakan. Silakan minta tautan baru.'; } 
    elseif (time() > strtotime($row['expires_at'])) { $status_message = 'Token telah kedaluwarsa. Silakan minta tautan baru.'; } 
    else {
        $show_wizard = true;
        $user_email = $row['email'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nama = trim($_POST['nama'] ?? '');
            $nim = trim($_POST['nim'] ?? '');
            $alasan = trim($_POST['alasan'] ?? '');

            if ($nama === '' || $nim === '' || $alasan === '') {
                $status_message = 'Semua field wajib diisi.';
            } else {
                $pdo->prepare('INSERT INTO unblock_requests (user_id, nama, nim, alasan, status, created_at) VALUES (:uid, :nama, :nim, :alasan, "pending", NOW())')
                    ->execute(['uid' => $row['user_id'], 'nama' => $nama, 'nim' => $nim, 'alasan' => $alasan]);
                
                $pdo->prepare('UPDATE unblock_tokens SET used = 1 WHERE id = :id')->execute(['id' => $row['id']]);

                $submission_success = true;
                $show_wizard = false;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permohonan Buka Blokir Akun - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; margin: 0; overflow: hidden; }
        #request-wrapper { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; background-size: 400% 400%; transition: background 1s ease-in-out; animation: gradient-animation 20s ease infinite; }
        .theme-pagi { background: linear-gradient(-45deg, #84fab0, #8fd3f4, #a1c4fd, #c2e9fb); }
        .theme-siang { background: linear-gradient(-45deg, #4facfe, #00f2fe, #43e97b, #38f9d7); }
        .theme-sore { background: linear-gradient(-45deg, #f6d365, #fda085, #ff9a9e, #fad0c4); }
        .theme-malam { background: linear-gradient(-45deg, #232526, #414345, #0f2027, #2c5364); }
        @keyframes gradient-animation { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        
        .request-card {
            width: 100%; max-width: 600px;
            border: none; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            animation: fadeIn 0.8s ease-out forwards;
        }
        .theme-malam .request-card { background: rgba(30, 30, 30, 0.7); color: #f1f1f1; }
        .theme-malam .text-muted { color: #ccc !important; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

        /* Wizard Progress Bar */
        .progress-bar-wizard { display: flex; list-style: none; padding: 0; margin-bottom: 2rem; }
        .progress-bar-wizard li { flex: 1; text-align: center; font-size: 0.8rem; color: #aaa; position: relative; }
        .progress-bar-wizard li::before { content: ''; width: 24px; height: 24px; border-radius: 50%; background: #ddd; display: block; margin: 0 auto 0.5rem; border: 3px solid #fff; transition: all 0.4s ease; }
        .progress-bar-wizard li::after { content: ''; height: 2px; width: 100%; background: #ddd; position: absolute; top: 12px; left: -50%; z-index: -1; }
        .progress-bar-wizard li:first-child::after { content: none; }
        .progress-bar-wizard li.active::before { background: var(--bs-primary); border-color: var(--bs-primary-bg-subtle); transform: scale(1.2); }
        .progress-bar-wizard li.active { color: var(--bs-primary); font-weight: 600; }
        .theme-malam .progress-bar-wizard li { color: #888; }
        .theme-malam .progress-bar-wizard li::before { background: #555; border-color: #333; }
        .theme-malam .progress-bar-wizard li::after { background: #555; }
        .theme-malam .progress-bar-wizard li.active { color: #fff; }

        /* Wizard Steps */
        .wizard-step { display: none; animation: fadeIn 0.5s; }
        .wizard-step.active { display: block; }

        /* Input Styling */
        .input-wrapper { position: relative; margin-bottom: 1.5rem; }
        .input-wrapper .form-control { background-color: transparent !important; border: none !important; border-bottom: 2px solid #ccc !important; border-radius: 0 !important; box-shadow: none !important; padding-left: 2.5rem !important; }
        .input-wrapper i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: #aaa; transition: all 0.3s ease; }
        .input-wrapper::after { content: ''; position: absolute; bottom: 0; left: 50%; width: 100%; height: 2px; background-color: var(--bs-primary); transform: translateX(-50%) scaleX(0); transition: transform 0.4s ease; }
        .input-wrapper:focus-within::after { transform: translateX(-50%) scaleX(1); }
        .input-wrapper:focus-within i { color: var(--bs-primary); }
        .theme-malam .input-wrapper .form-control { color: #fff !important; border-bottom-color: #555 !important; }
        
        /* Confirmation Animation */
        .confirmation-animation { text-align: center; }
        .checkmark-circle { width: 100px; height: 100px; position: relative; display: inline-block; }
        .checkmark-circle .background { width: 100px; height: 100px; border-radius: 50%; background: var(--bs-success); animation: scaleIn 0.4s ease-out; }
        .checkmark-circle .checkmark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(45deg); width: 30px; height: 60px; border-right: 10px solid #fff; border-bottom: 10px solid #fff; animation: drawCheck 0.4s 0.2s ease-out forwards; opacity: 0; }
        @keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
        @keyframes drawCheck { from { width: 0; height: 0; opacity: 1; } to { width: 30px; height: 60px; opacity: 1; } }

        .btn { border-radius: 12px; font-weight: 600; padding: .6rem 1.2rem; }
    </style>
</head>
<body>
    <div id="request-wrapper">
        <div class="request-card p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="fa-solid fa-unlock-keyhole fa-3x text-primary mb-3"></i>
                <h3 class="fw-bold">Permohonan Buka Blokir Akun</h3>
            </div>
            
            <?php if (!empty($status_message) && !$submission_success): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($status_message); ?></div>
                <a href="../login.php" class="btn btn-primary">Kembali ke Login</a>
            <?php endif; ?>

            <?php if ($submission_success): ?>
                <div class="confirmation-animation">
                    <div class="checkmark-circle"><div class="background"></div><div class="checkmark"></div></div>
                    <h5 class="mt-3 fw-bold">Permohonan Terkirim!</h5>
                    <p class="text-muted">Permohonan Anda telah berhasil diajukan. Silakan tunggu persetujuan dari administrator. Anda akan menerima notifikasi lebih lanjut melalui email.</p>
                    <a href="../login.php" class="btn btn-primary mt-2">Selesai</a>
                </div>
            <?php endif; ?>

            <?php if ($show_wizard): ?>
            <form method="POST" id="wizard-form">
                <ul class="progress-bar-wizard">
                    <li class="active" data-step="1">Verifikasi</li>
                    <li data-step="2">Identitas</li>
                    <li data-step="3">Alasan</li>
                </ul>

                <div class="wizard-step active" data-step="1">
                    <h5 class="text-center">Langkah 1: Verifikasi Email</h5>
                    <p class="text-center text-muted">Kami telah memvalidasi token dari email Anda. Mohon konfirmasi bahwa ini adalah alamat email Anda yang benar.</p>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-primary wizard-next">Lanjutkan <i class="fa-solid fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <div class="wizard-step" data-step="2">
                    <h5 class="text-center">Langkah 2: Konfirmasi Identitas</h5>
                    <p class="text-center text-muted">Untuk memastikan keamanan, silakan isi nama lengkap dan NIM Anda.</p>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" class="form-control" name="nama" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-id-card"></i>
                        <input type="text" class="form-control" name="nim" placeholder="Nomor Induk Mahasiswa (NIM)" required>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary wizard-prev"><i class="fa-solid fa-arrow-left me-2"></i> Kembali</button>
                        <button type="button" class="btn btn-primary wizard-next">Lanjutkan <i class="fa-solid fa-arrow-right ms-2"></i></button>
                    </div>
                </div>

                <div class="wizard-step" data-step="3">
                    <h5 class="text-center">Langkah 3: Alasan Permohonan</h5>
                    <p class="text-center text-muted">Jelaskan secara singkat mengapa akun Anda terblokir dan mengapa Anda meminta untuk membukanya kembali.</p>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-comment-dots" style="top: 1.2rem; transform: none;"></i>
                        <textarea class="form-control" name="alasan" rows="4" placeholder="Contoh: Saya lupa password dan salah memasukkannya tiga kali." required style="padding-top: 1rem;"></textarea>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary wizard-prev"><i class="fa-solid fa-arrow-left me-2"></i> Kembali</button>
                        <button type="submit" class="btn btn-success"><i class="fa-solid fa-paper-plane me-2"></i> Ajukan Permohonan</button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- Logika Tema Dinamis ---
            const wrapper = document.getElementById('request-wrapper');
            function setDynamicTheme() {
                const hour = new Date().getHours();
                let theme = 'theme-siang';
                if (hour >= 5 && hour < 12) theme = 'theme-pagi'; 
                else if (hour >= 15 && hour < 19) theme = 'theme-sore'; 
                else if (hour >= 19 || hour < 5) theme = 'theme-malam';
                wrapper.className = theme;
            }
            setDynamicTheme();

            // --- Logika Wizard ---
            const wizardForm = document.getElementById('wizard-form');
            if(wizardForm) {
                const steps = Array.from(wizardForm.querySelectorAll('.wizard-step'));
                const nextButtons = wizardForm.querySelectorAll('.wizard-next');
                const prevButtons = wizardForm.querySelectorAll('.wizard-prev');
                const progressItems = document.querySelectorAll('.progress-bar-wizard li');
                let currentStep = 0;

                function showStep(stepIndex) {
                    steps.forEach((step, index) => {
                        step.classList.toggle('active', index === stepIndex);
                    });
                    progressItems.forEach((item, index) => {
                        item.classList.toggle('active', index <= stepIndex);
                    });
                    currentStep = stepIndex;
                }

                nextButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        const currentStepElement = steps[currentStep];
                        const inputs = currentStepElement.querySelectorAll('input[required], textarea[required]');
                        let allValid = true;
                        inputs.forEach(input => {
                            if (!input.value.trim()) {
                                allValid = false;
                                input.focus();
                                // Anda bisa menambahkan pesan error di sini
                            }
                        });

                        if (allValid && currentStep < steps.length - 1) {
                            showStep(currentStep + 1);
                        }
                    });
                });

                prevButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        if (currentStep > 0) {
                            showStep(currentStep - 1);
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>