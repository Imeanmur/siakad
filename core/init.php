<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../vendor/autoload.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$project_root_folder = 'siakad';

define('BASE_URL', $protocol . $host . '/' . $project_root_folder . '/');

// Konfigurasi email penerima OTP (gunakan Gmail yang dikonfigurasi)
if (!defined('OTP_DELIVERY_EMAIL')) {
            define('OTP_DELIVERY_EMAIL', 'mdrilanang@gmail.com');
}

// Opsional: setel pengirim email agar sesuai dengan akun Gmail SMTP Anda
if (!defined('OTP_SENDER_EMAIL')) {
            define('OTP_SENDER_EMAIL', 'mdrilanang@gmail.com');
}
if (!defined('OTP_SENDER_NAME')) {
            define('OTP_SENDER_NAME', 'SIAKAD');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/dlm.php';

// Pastikan kolom untuk fitur blokir login tersedia
try {
	// MySQL 8 mendukung IF NOT EXISTS
	$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_attempts INT NOT NULL DEFAULT 0");
	$pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS blocked_at TIMESTAMP NULL DEFAULT NULL");
    // Tabel token untuk request unblock
    $pdo->exec("CREATE TABLE IF NOT EXISTS unblock_tokens (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_token (user_id, token),
        CONSTRAINT fk_unblock_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Tabel permohonan unblock
    $pdo->exec("CREATE TABLE IF NOT EXISTS unblock_requests (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nama VARCHAR(100) NOT NULL,
        nim VARCHAR(50) NOT NULL,
        alasan TEXT NOT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        CONSTRAINT fk_unblockreq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Kolom status pemrosesan permohonan (jika belum ada)
    $pdo->exec("ALTER TABLE unblock_requests ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE unblock_requests ADD COLUMN IF NOT EXISTS processed_at TIMESTAMP NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE unblock_requests ADD COLUMN IF NOT EXISTS processed_by INT NULL");

    // Tabel arsip permohonan unblock
    $pdo->exec("CREATE TABLE IF NOT EXISTS unblock_requests_archive (
        id INT NOT NULL,
        user_id INT NOT NULL,
        nama VARCHAR(100) NOT NULL,
        nim VARCHAR(50) NOT NULL,
        alasan TEXT NOT NULL,
        status VARCHAR(20) NOT NULL,
        created_at TIMESTAMP NULL,
        processed_at TIMESTAMP NULL,
        processed_by INT NULL,
        archived_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_archive_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Tabel audit log
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        metadata TEXT NULL,
        ip VARCHAR(45) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_action_created (action, created_at),
        INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (Exception $e) {
	// Diamkan: jika tidak didukung/kolom sudah ada, lanjutkan tanpa menghentikan aplikasi
}