<?php
// --- BAGIAN PHP KAMU (TIDAK DIUBAH) ---
require_once 'core/init.php';
require_once 'core/dlm.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: ' . BASE_URL . 'modules/admin/');
    } elseif ($_SESSION['role'] == 'dosen') {
        header('Location: ' . BASE_URL . 'modules/dosen/');
    } else {
        header('Location: ' . BASE_URL . 'modules/mahasiswa/');
    }
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Email/NIM dan password tidak boleh kosong!';
    } else {
        $sql = "SELECT users.id, users.password, users.is_active, users.failed_attempts, users.blocked_at, roles.role_name 
                FROM users 
                JOIN roles ON users.role_id = roles.id 
                WHERE users.username = :username OR users.email = :username";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Cek status blokir
        if ((int)$user['is_active'] === 0 || !empty($user['blocked_at'])) {
            $error = 'Akun Anda telah diblokir. Silakan hubungi admin atau gunakan fitur bantuan.';
        } else {
            $storedPassword = (string)$user['password'];
            $hashInfo = password_get_info($storedPassword);
            $isPasswordValid = false;
            if (!empty($hashInfo['algo'])) {
                $isPasswordValid = password_verify($password, $storedPassword);
            } else {
                $isPasswordValid = hash_equals($storedPassword, $password);
            }
            if ($isPasswordValid) {
                dlm_log_event($pdo, $user['id'], 'login', ['username' => $username, 'role' => $user['role_name']], $user['role_name'], $user['id']);
                $pdo->prepare("UPDATE users SET failed_attempts = 0, blocked_at = NULL, is_active = 1 WHERE id = :id")
                    ->execute(['id' => $user['id']]);

                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_role'] = $user['role_name'];
                $_SESSION['last_activity'] = time();

                header('Location: ' . BASE_URL . 'verify_otp.php');
                exit();
            } else {
                $newAttempts = ((int)$user['failed_attempts']) + 1;
                if ($newAttempts >= 3) {
                    $pdo->prepare("UPDATE users SET failed_attempts = :fa, blocked_at = NOW(), is_active = 0 WHERE id = :id")
                        ->execute(['fa' => $newAttempts, 'id' => $user['id']]);
                    $error = 'Akun Anda telah diblokir. Silakan hubungi admin atau gunakan fitur bantuan.';
                } elseif ($newAttempts === 2) {
                    $pdo->prepare("UPDATE users SET failed_attempts = :fa WHERE id = :id")
                        ->execute(['fa' => $newAttempts, 'id' => $user['id']]);
                    $error = 'Password salah! Kesempatan terakhir sebelum akun Anda diblokir.';
                } else {
                    $pdo->prepare("UPDATE users SET failed_attempts = :fa WHERE id = :id")
                        ->execute(['fa' => $newAttempts, 'id' => $user['id']]);
                    $error = 'Email/NIM atau password salah! Silakan coba lagi.';
                }
            }
        }
    } else {
        $error = 'Email/NIM atau password salah!';
    }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIAKAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* Import Font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        /* Reset dan Body Styling */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            overflow-x: hidden; /* Mencegah scroll horizontal */
        }

        /* --- BACKGROUND GRADIENT ANIMASI --- */
        #login-wrapper {
            min-height: 100vh;
            padding: 1rem;
            background-size: 400% 400%;
            transition: background 1s ease-in-out;
            animation: gradient-animation 20s ease infinite;
        }

        /* Tema Warna Dinamis */
        .theme-pagi { background: linear-gradient(-45deg, #84fab0, #8fd3f4, #a1c4fd, #c2e9fb); }
        .theme-siang { background: linear-gradient(-45deg, #4facfe, #00f2fe, #43e97b, #38f9d7); }
        .theme-sore { background: linear-gradient(-45deg, #f6d365, #fda085, #ff9a9e, #fad0c4); }
        .theme-malam { background: linear-gradient(-45deg, #232526, #414345, #0f2027, #2c5364); }

        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* --- KARTU LOGIN (GLASSMORPHISM) --- */
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }
        .theme-malam .login-card {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* --- KOLOM KIRI (GREETING) --- */
        .login-card-left {
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            padding: 3rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        .theme-malam .login-card-left { color: #f0f0f0; }

        /* --- KOLOM KANAN (FORM) --- */
        .login-card-right {
            background: rgba(255, 255, 255, 0.7);
            transition: background-color 0.5s ease;
        }
        .theme-malam .login-card-right {
            background: rgba(30, 30, 30, 0.7);
            color: #f1f1f1;
        }
        .theme-malam .text-muted { color: #ccc !important; }
        .theme-malam .form-control, .theme-malam .form-floating>label, .theme-malam .btn-outline-secondary {
            background-color: #333 !important;
            color: #fff !important;
            border-color: #555 !important;
        }

        /* --- STYLING FORM & MICRO-INTERACTIONS --- */
        .form-control, .btn { border-radius: 12px; }
        .form-floating > label { padding: 1rem 1.25rem; }
        .btn-login {
            padding: 0.9rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(var(--bs-primary-rgb), 0.3);
        }
        .input-group .form-floating { flex: 1 1 auto; }
        #togglePassword { border-left: 0; }
        #togglePassword:focus { box-shadow: none; }

        /* --- ANIMASI --- */
        .animate-fade-in { animation: fadeIn 0.8s ease-out forwards; }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .animate-shake { animation: shake 0.5s; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body>
    <div id="login-wrapper">
        <div class="container">
            <div class="row min-vh-100 justify-content-center align-items-center">
                <div class="col-lg-10">
                    <div class="card login-card animate-fade-in">
                        <div class="row g-0">
                            <div class="col-md-6 d-none d-md-flex login-card-left">
                                <div class="greeting-box text-center">
                                    <i id="greeting-icon" class="fa-solid fa-sun fa-4x mb-4"></i>
                                    <h2 id="greeting-text" class="fw-bold">Selamat Datang</h2>
                                    <p id="greeting-desc" class="lead mt-2">Masuk untuk mengakses dunia akademik Anda.</p>
                                </div>
                            </div>
                            <div class="col-md-6 login-card-right">
                                <div class="login-form p-4 p-md-5">
                                    <div class="text-center mb-4">
                                        <h3 class="fw-bold">Login Akun</h3>
                                        <p class="text-muted">Gunakan akun terdaftar Anda.</p>
                                    </div>

                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger animate-shake" role="alert">
                                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                                        </div>
                                    <?php endif; ?>

                                    <form action="login.php" method="POST" id="loginForm">
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="username" name="username" placeholder="Email atau NIM" required>
                                            <label for="username"><i class="fas fa-user me-2"></i>Email atau NIM</label>
                                        </div>
                                        <div class="input-group mb-4">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                            </div>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye" id="toggleIcon"></i>
                                            </button>
                                        </div>
                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-login" id="loginButton">
                                                <span class="btn-text">Login</span>
                                                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                            </button>
                                        </div>
                                    </form>
                                    <?php if (!empty($error) && (strpos($error, 'diblokir') !== false)): ?>
                                        <div class="mt-3 text-center">
                                            <a href="support.php?reason=blocked" class="text-decoration-none">Butuh Bantuan?</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // --- 1. Sapaan, Ikon, dan Tema Dinamis Berdasarkan Waktu ---
            const wrapper = document.getElementById('login-wrapper');
            const greetingText = document.getElementById('greeting-text');
            const greetingDesc = document.getElementById('greeting-desc');
            const greetingIcon = document.getElementById('greeting-icon');

            function setDynamicTheme() {
                const hour = new Date().getHours();

                if (!wrapper || !greetingText || !greetingDesc || !greetingIcon) {
                    console.error("Elemen UI dinamis tidak ditemukan!");
                    return;
                }

                if (hour >= 5 && hour < 12) { // Pagi
                    wrapper.className = 'theme-pagi';
                    greetingIcon.className = 'fa-solid fa-sun fa-4x mb-4';
                    greetingText.textContent = 'Selamat Pagi!';
                    greetingDesc.textContent = 'Awali harimu dengan semangat untuk meraih ilmu.';
                } else if (hour >= 12 && hour < 15) { // Siang
                    wrapper.className = 'theme-siang';
                    greetingIcon.className = 'fa-solid fa-cloud-sun fa-4x mb-4';
                    greetingText.textContent = 'Selamat Siang!';
                    greetingDesc.textContent = 'Tetap fokus dan kejar semua target akademismu.';
                } else if (hour >= 15 && hour < 19) { // Sore
                    wrapper.className = 'theme-sore';
                    greetingIcon.className = 'fa-solid fa-wind fa-4x mb-4';
                    greetingText.textContent = 'Selamat Sore!';
                    greetingDesc.textContent = 'Selesaikan tugasmu dan nikmati sisa harimu.';
                } else { // Malam
                    wrapper.className = 'theme-malam';
                    greetingIcon.className = 'fa-solid fa-moon fa-4x mb-4';
                    greetingText.textContent = 'Selamat Malam!';
                    greetingDesc.textContent = 'Waktunya istirahat dan siapkan energi untuk esok.';
                }
            }

            setDynamicTheme();

            // --- 2. Logika Interaksi Form ---
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const btnText = loginButton.querySelector('.btn-text');
            const spinner = loginButton.querySelector('.spinner-border');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');

            // Tampilkan/Sembunyikan Password
            if (togglePasswordBtn) {
                togglePasswordBtn.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    toggleIcon.classList.toggle('fa-eye');
                    toggleIcon.classList.toggle('fa-eye-slash');
                });
            }

            // Animasi tombol saat submit
            if (loginForm) {
                loginForm.addEventListener('submit', function () {
                    // Cek validitas form bawaan HTML5 sebelum menampilkan spinner
                    if (loginForm.checkValidity()) {
                        btnText.textContent = 'Memproses...';
                        spinner.classList.remove('d-none');
                        loginButton.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>