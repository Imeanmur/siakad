document.addEventListener('DOMContentLoaded', () => {
  const pendingToken = sessionStorage.getItem('siakad_pending_token');
  const maskedEmail = sessionStorage.getItem('siakad_masked_email');

  if (!pendingToken) {
    location.replace('login.html');
    return;
  }

  // --- 1. Sapaan dan Tema Dinamis Berdasarkan Waktu ---
  const wrapper = document.getElementById('otp-wrapper');
  function setDynamicTheme() {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) {
      wrapper.className = 'theme-pagi';
    } else if (hour >= 12 && hour < 15) {
      wrapper.className = 'theme-siang';
    } else if (hour >= 15 && hour < 19) {
      wrapper.className = 'theme-sore';
    } else {
      wrapper.className = 'theme-malam';
    }
  }
  setDynamicTheme();

  if (maskedEmail) {
    document.getElementById('otp-instruction').innerHTML =
      `Kode verifikasi telah dikirim ke <strong>${maskedEmail}</strong>. Masukkan 6 digit kode di bawah:`;
  }

  const messageContainer = document.getElementById('message-container');
  const timerContainer = document.getElementById('timer-container');
  const countdownEl = document.getElementById('countdown-timer');
  const resendContainer = document.getElementById('resend-container');
  const resendBtn = document.getElementById('resend-btn');
  const form = document.getElementById('otpForm');
  const verifyBtn = document.getElementById('verifyButton');
  const btnText = verifyBtn.querySelector('.btn-text');
  const spinner = verifyBtn.querySelector('.spinner-border');
  const inputs = Array.from(document.querySelectorAll('.otp-input'));

  let timerInterval = null;

  function showMessage(text, type = 'danger') {
    messageContainer.innerHTML = `<div class="alert alert-${type} py-2 mb-2 animate-shake">${text}</div>`;
  }

  function startCountdown(seconds = 60) {
    if (timerInterval) clearInterval(timerInterval);
    timerContainer.classList.remove('d-none');
    resendContainer.classList.add('d-none');

    let remaining = seconds;
    const updateDisplay = () => {
      const m = Math.floor(remaining / 60).toString().padStart(2, '0');
      const s = (remaining % 60).toString().padStart(2, '0');
      countdownEl.textContent = `${m}:${s}`;
    };
    updateDisplay();

    timerInterval = setInterval(() => {
      remaining--;
      if (remaining <= 0) {
        clearInterval(timerInterval);
        timerContainer.classList.add('d-none');
        resendContainer.classList.remove('d-none');
      } else {
        updateDisplay();
      }
    }, 1000);
  }

  // Mulai timer 60 detik awal
  startCountdown(60);

  // Focus pada input pertama
  inputs[0].focus();

  // --- 2. Logika Input OTP Cerdas ---
  inputs.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
      // Hanya izinkan angka
      input.value = input.value.replace(/[^0-9]/g, '');

      if (input.value.length === 1 && idx < inputs.length - 1) {
        inputs[idx + 1].focus();
      }

      // Jika semua 6 kotak terisi, otomatis submit
      const code = inputs.map((i) => i.value).join('');
      if (code.length === 6) {
        submitOtp(code);
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !input.value && idx > 0) {
        inputs[idx - 1].focus();
      }
    });
  });

  // Paste support
  inputs[0].addEventListener('paste', (e) => {
    e.preventDefault();
    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, 6);
    if (!pasted) return;

    for (let i = 0; i < pasted.length; i++) {
      if (inputs[i]) inputs[i].value = pasted[i];
    }
    const lastFilled = Math.min(pasted.length, inputs.length) - 1;
    if (inputs[lastFilled]) inputs[lastFilled].focus();

    if (pasted.length === 6) {
      submitOtp(pasted);
    }
  });

  // --- 3. Proses Verifikasi OTP ---
  async function submitOtp(code) {
    if (!code || code.length !== 6) {
      showMessage('Silakan masukkan 6 digit kode OTP secara lengkap.');
      return;
    }

    messageContainer.innerHTML = '';
    btnText.textContent = 'Memverifikasi...';
    spinner.classList.remove('d-none');
    verifyBtn.disabled = true;

    try {
      const res = await Api.post('/auth/otp/verify', {
        pending_token: pendingToken,
        code,
      });

      // Simpan sesi autentikasi lengkap
      Api.setSession(res.token, res.user);
      sessionStorage.removeItem('siakad_pending_token');
      sessionStorage.removeItem('siakad_masked_email');

      // Arahkan ke dashboard sesuai role
      const redirectMap = {
        admin: 'admin/index.html',
        dosen: 'dosen/index.html',
        mahasiswa: 'mahasiswa/index.html',
      };
      location.replace(redirectMap[res.user.role] || 'login.html');
    } catch (err) {
      showMessage(err.message || 'Verifikasi kode OTP gagal.');
      // Bersihkan dan fokus kembali ke kotak pertama
      inputs.forEach((i) => (i.value = ''));
      inputs[0].focus();
    } finally {
      btnText.textContent = 'Verifikasi';
      spinner.classList.add('d-none');
      verifyBtn.disabled = false;
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    const code = inputs.map((i) => i.value).join('');
    submitOtp(code);
  });

  // --- 4. Kirim Ulang OTP ---
  resendBtn.addEventListener('click', async () => {
    resendBtn.disabled = true;
    resendBtn.textContent = 'Mengirim...';
    messageContainer.innerHTML = '';

    try {
      const res = await Api.post('/auth/otp/resend', { pending_token: pendingToken });
      showMessage(res.message || 'Kode OTP baru berhasil dikirim.', 'success');
      startCountdown(60);
      inputs.forEach((i) => (i.value = ''));
      inputs[0].focus();
    } catch (err) {
      showMessage(err.message || 'Gagal mengirim ulang kode OTP.');
    } finally {
      resendBtn.disabled = false;
      resendBtn.textContent = 'Kirim Ulang OTP';
    }
  });
});
