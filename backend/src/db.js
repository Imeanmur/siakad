const mysql = require('mysql2/promise');

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'siakad',
  waitForConnections: true,
  connectionLimit: 10,
  charset: 'utf8mb4',
  // Kembalikan DATETIME/TIMESTAMP sebagai string "YYYY-MM-DD HH:MM:SS" agar tidak bergeser zona waktu.
  dateStrings: true,
  ssl: process.env.DB_SSL === 'true' ? { rejectUnauthorized: false } : undefined,
});

/** Tabel pendukung yang belum ada di siakad.sql (pengganti config/retention_settings.json & tabel OTP). */
async function ensureSchema() {
  await pool.query(`CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`);

  await pool.query(`CREATE TABLE IF NOT EXISTS otp_codes (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    next_resend_at TIMESTAMP NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_otp (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`);
}

module.exports = { pool, ensureSchema };
