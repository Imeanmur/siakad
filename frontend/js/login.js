document.addEventListener('DOMContentLoaded', () => {
  const wrapper = document.getElementById('login-wrapper');
  const greetingText = document.getElementById('greeting-text');
  const greetingDesc = document.getElementById('greeting-desc');
  const greetingIcon = document.getElementById('greeting-icon');

  // --- Sapaan, ikon, dan tema dinamis berdasarkan waktu ---
  function setDynamicTheme() {
    const hour = new Date().getHours();
    let theme;
    if (hour >= 5 && hour < 12) {
      theme = ['theme-pagi', 'fa-solid fa-sun', 'Selamat Pagi!', 'Awali harimu dengan semangat untuk meraih ilmu.'];
    } else if (hour >= 12 && hour < 15) {
      theme = ['theme-siang', 'fa-solid fa-cloud-sun', 'Selamat Siang!', 'Tetap fokus dan kejar semua target akademismu.'];
    } else if (hour >= 15 && hour < 19) {
      theme = ['theme-sore', 'fa-solid fa-wind', 'Selamat Sore!', 'Selesaikan tugasmu dan nikmati sisa harimu.'];
    } else {
      theme = ['theme-malam', 'fa-solid fa-moon', 'Selamat Malam!', 'Waktunya istirahat dan siapkan energi untuk esok.'];
    }
    wrapper.className = theme[0];
    greetingIcon.className = `${theme[1]} fa-4x mb-4`;
    greetingText.textContent = theme[2];
    greetingDesc.textContent = theme[3];
  }
  setDynamicTheme();

  // --- Pesan status dari halaman lain ---
  const info = document.getElementById('login-info');
  const errorBox = document.getElementById('login-error');
  const errorText = document.getElementById('login-error-text');
  const STATUS = {
    timeout: 'Sesi Anda telah berakhir. Silakan login kembali.',
    logout: 'Anda telah keluar dari sistem.',
    forbidden: 'Anda tidak memiliki hak akses ke halaman tersebut.',
  };
  const status = new URLSearchParams(location.search).get('status');
  if (STATUS[status]) {
    info.textContent = STATUS[status];
    info.classList.remove('d-none');
  }

  function showError(message) {
    errorText.textContent = message;
    errorBox.classList.remove('d-none');
    errorBox.classList.remove('animate-shake');
    void errorBox.offsetWidth;
    errorBox.classList.add('animate-shake');
  }

  // Jika sudah login, langsung ke dashboard sesuai role
  const existing = Api.getUser();
  if (Api.getToken() && existing) {
    const redirectMap = {
      admin: 'admin/index.html',
      dosen: 'dosen/index.html',
      mahasiswa: 'mahasiswa/index.html',
    };
    if (redirectMap[existing.role]) {
      location.replace(redirectMap[existing.role]);
      return;
    }
  }

  // --- Form ---
  const form = document.getElementById('loginForm');
  const button = document.getElementById('loginButton');
  const btnText = button.querySelector('.btn-text');
  const spinner = button.querySelector('.spinner-border');
  const passwordInput = document.getElementById('password');
  const toggleIcon = document.getElementById('toggleIcon');

  document.getElementById('togglePassword').addEventListener('click', () => {
    passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
    toggleIcon.classList.toggle('fa-eye');
    toggleIcon.classList.toggle('fa-eye-slash');
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.classList.add('d-none');

    const username = form.username.value.trim();
    const password = form.password.value;
    if (!username || !password) {
      showError('Email/NIM dan password tidak boleh kosong!');
      return;
    }

    btnText.textContent = 'Memproses...';
    spinner.classList.remove('d-none');
    button.disabled = true;

    try {
      const data = await Api.post('/auth/login', { username, password });

      // Jika alur verifikasi OTP aktif
      if (data.otp_required) {
        sessionStorage.setItem('siakad_pending_token', data.pending_token);
        sessionStorage.setItem('siakad_masked_email', data.email_masked || '');
        location.replace('verify-otp.html');
        return;
      }

      // Jika langsung terautentikasi (mis. token sudah dikembalikan)
      Api.setSession(data.token, data.user);
      const redirectMap = {
        admin: 'admin/index.html',
        dosen: 'dosen/index.html',
        mahasiswa: 'mahasiswa/index.html',
      };
      location.replace(redirectMap[data.user.role] || 'login.html');
    } catch (err) {
      showError(err.message);
    } finally {
      btnText.textContent = 'Login';
      spinner.classList.add('d-none');
      button.disabled = false;
    }
  });
});
