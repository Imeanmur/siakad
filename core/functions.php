<?php
// File ini akan digunakan untuk menyimpan fungsi-fungsi umum
// yang bisa digunakan di seluruh aplikasi.
// Contoh: fungsi untuk validasi, sanitasi input, format tanggal, dll.

// OTP utilities

/**
 * Generate a 6-digit numeric OTP code.
 */
function generate_otp_code() {
	return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Start OTP flow for a pending user. Stores OTP and metadata in session.
 */
function start_otp_for_user($user_id) {
    global $pdo;

    // Ambil email user dari database
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) return false;

    $email = $user['email']; // Email user login

    // Generate kode OTP
    $otp = rand(100000, 999999);
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_time'] = time();

    // Kirim OTP ke email user login
    $subject = 'Kode OTP Login SIAKAD';
    $body = "Kode OTP Anda: $otp\n\nJangan berikan kode ini ke siapapun.";
    $fromEmail = defined('OTP_SENDER_EMAIL') ? OTP_SENDER_EMAIL : 'no-reply@siakad.local';
    $fromName = defined('OTP_SENDER_NAME') ? OTP_SENDER_NAME : 'SIAKAD';
    $headers = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($email, $subject, $body, $headers);

    return true;
}

/**
 * Verify submitted OTP code. Returns [bool success, string message]
 */
function verify_otp_code($submitted) {
	if (!isset($_SESSION['otp'])) {
		return [false, 'OTP tidak ditemukan. Mulai ulang proses login.'];
	}
	$otp = &$_SESSION['otp'];
	if (time() > $otp['expires_at']) {
		return [false, 'OTP telah kedaluwarsa. Silakan kirim ulang.'];
	}
	$otp['attempts']++;
	if ($otp['attempts'] > 5) {
		return [false, 'Terlalu banyak percobaan. Silakan kirim ulang OTP.'];
	}
	if (hash_equals($otp['code'], trim((string)$submitted))) {
		return [true, 'OTP valid'];
	}
	return [false, 'OTP salah.'];
}

/**
 * Check if user can resend OTP now.
 */
function can_resend_otp() {
	if (!isset($_SESSION['otp'])) return true;
	return time() >= ($_SESSION['otp']['next_resend_at'] ?? 0);
}

/**
 * Regenerate and update OTP with cooldown.
 */
function resend_otp() {
	$code = generate_otp_code();
	$_SESSION['otp']['code'] = $code;
	$_SESSION['otp']['expires_at'] = time() + 300; // 5 minutes
	$_SESSION['otp']['attempts'] = 0;
	$_SESSION['otp']['next_resend_at'] = time() + 30;
	$_SESSION['otp']['requested'] = true;
}

/**
 * Send email using PHPMailer (Gmail SMTP) if configured, with fallback to mail().
 * Returns [bool success, string message]
 */
function send_siakad_email($toEmail, $subject, $bodyText) {
	if (defined('SMTP_PASS') && !empty(SMTP_PASS)) {
		try {
			$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
			$mail->isSMTP();
			$mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
			$mail->SMTPAuth   = true;
			$mail->Username   = defined('SMTP_USER') ? SMTP_USER : 'mdrilanang@gmail.com';
			$mail->Password   = SMTP_PASS;
			$mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;

			$fromEmail = defined('SMTP_USER') ? SMTP_USER : 'mdrilanang@gmail.com';
			$fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'SIAKAD';
			$mail->setFrom($fromEmail, $fromName);
			$mail->addAddress($toEmail);

			$mail->isHTML(false);
			$mail->Subject = $subject;
			$mail->Body    = $bodyText;

			$mail->send();
			return [true, 'Kode OTP telah berhasil dikirim ke alamat email Anda.'];
		} catch (\PHPMailer\PHPMailer\Exception $e) {
			return [false, 'Gagal kirim via SMTP Gmail: ' . $mail->ErrorInfo];
		} catch (\Exception $e) {
			return [false, 'Terjadi kesalahan SMTP: ' . $e->getMessage()];
		}
	}

	// Fallback jika belum mengisi SMTP_PASS: menggunakan mail() lokal
	$fromEmail = defined('OTP_SENDER_EMAIL') ? OTP_SENDER_EMAIL : ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'siakad.local'));
	$fromName = defined('OTP_SENDER_NAME') ? OTP_SENDER_NAME : 'SIAKAD';
	$headers = 'From: ' . $fromName . ' <' . $fromEmail . ">\r\n" .
		'Reply-To: ' . $fromEmail . "\r\n" .
		'MIME-Version: 1.0' . "\r\n" .
		'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
	if (!empty($fromEmail)) {
		@ini_set('sendmail_from', $fromEmail);
	}
	$additionalParams = !empty($fromEmail) ? ('-f ' . escapeshellarg($fromEmail)) : '';
	$sent = @mail($toEmail, $subject, $bodyText, $headers, $additionalParams);
	if ($sent) {
		return [true, 'OTP telah dikirim ke email Anda.'];
	}
	return [false, 'Gagal mengirim email OTP.'];
}

/**
 * Send the current OTP code to the user's registered email.
 * Returns [bool success, string message]
 */
function send_otp_to_user($pdo, $userId) {
	try {
		// Ambil email pengguna berdasarkan userId yang sedang login
		$stmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
		$stmt->execute(['id' => $userId]);
		$user = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$user || empty($user['email'])) {
			return [false, 'Email pengguna tidak ditemukan.'];
		}
		$email = (string)$user['email'];
		$subject = 'Kode OTP SIAKAD Anda';
		$code = $_SESSION['otp']['code'] ?? null;
		if (!$code) {
			return [false, 'Kode OTP belum dibuat.'];
		}
		$message = "Halo,\r\n\r\n" .
			"Kode OTP Anda adalah: " . $code . "\r\n" .
			"Berlaku selama 5 menit. Jangan berikan kode ini kepada siapa pun.\r\n\r\n" .
			"- SIAKAD";

		return send_siakad_email($email, $subject, $message);
	} catch (Exception $e) {
		return [false, 'Terjadi kesalahan saat memproses OTP: ' . $e->getMessage()];
	}
}

