const nodemailer = require('nodemailer');

let transporter = null;

function getTransporter() {
  const host = process.env.SMTP_HOST || 'smtp.gmail.com';
  const port = Number(process.env.SMTP_PORT || 587);
  const user = process.env.SMTP_USER || '';
  const pass = process.env.SMTP_PASS || '';

  if (!user || !pass) {
    return null;
  }

  if (!transporter) {
    transporter = nodemailer.createTransport({
      host,
      port,
      secure: port === 465,
      auth: { user, pass },
      tls: {
        rejectUnauthorized: false
      }
    });
  }
  return transporter;
}

/**
 * Mengirim email kode OTP.
 * Jika SMTP belum dikonfigurasi (mis. lokal/testing), kode tetap dicatat ke console log server
 * sehingga proses verifikasi tidak terhenti.
 */
async function sendOtpEmail(toEmail, code) {
  const t = getTransporter();
  const subject = 'Kode OTP Login SIAKAD';
  const fromName = process.env.SMTP_FROM_NAME || 'SIAKAD';
  const fromEmail = process.env.SMTP_FROM_EMAIL || process.env.SMTP_USER || 'no-reply@siakad.local';

  const textBody = `Halo,

Kode OTP Anda untuk verifikasi login ke SIAKAD adalah: ${code}

Kode ini berlaku selama 5 menit. Jangan berikan kode ini kepada siapa pun demi menjaga keamanan akun Anda.

Jika Anda tidak merasa melakukan permintaan login ini, abaikan email ini dan segera amankan akun Anda.

Salam,
Tim Akademik SIAKAD`;

  const htmlBody = `
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Login SIAKAD</title>
  </head>
  <body style="margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
        <td style="padding: 40px 15px;" align="center">
          <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 520px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
            <!-- Header -->
            <tr>
              <td style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); padding: 32px 24px; text-align: center;">
                <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: 1px;">SIAKAD</h1>
                <p style="margin: 6px 0 0 0; color: rgba(255,255,255,0.85); font-size: 13px;">Sistem Informasi Akademik</p>
              </td>
            </tr>
            <!-- Content -->
            <tr>
              <td style="padding: 36px 28px 24px 28px;">
                <h2 style="margin: 0 0 14px 0; color: #1e293b; font-size: 19px; font-weight: 600; text-align: center;">Verifikasi Login Anda</h2>
                <p style="margin: 0 0 24px 0; color: #64748b; font-size: 14px; line-height: 1.6; text-align: center;">
                  Gunakan kode OTP berikut untuk menyelesaikan proses masuk ke akun SIAKAD Anda:
                </p>
                <!-- OTP Box -->
                <div style="background-color: #f8fafc; border: 2px dashed #0d6efd; border-radius: 12px; padding: 18px 12px; text-align: center; margin-bottom: 24px;">
                  <span style="font-family: 'Courier New', Courier, monospace; font-size: 34px; font-weight: 700; letter-spacing: 8px; color: #0d6efd; display: inline-block; padding-left: 8px;">${code}</span>
                </div>
                <!-- Warning / Info -->
                <div style="background-color: #eff6ff; border-left: 4px solid #3b82f6; border-radius: 4px; padding: 12px 14px; margin-bottom: 20px;">
                  <p style="margin: 0; font-size: 12.5px; color: #1e40af; line-height: 1.5;">
                    ⏱️ Kode ini hanya berlaku selama <strong>5 menit</strong>. Jangan pernah membagikan kode OTP ini kepada siapa pun, termasuk pihak kampus.
                  </p>
                </div>
                <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.5; text-align: center;">
                  Jika Anda tidak merasa melakukan proses login ini, abaikan pesan ini dan akun Anda tetap aman.
                </p>
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 24px; text-align: center;">
                <p style="margin: 0; color: #94a3b8; font-size: 11.5px;">&copy; ${new Date().getFullYear()} SIAKAD. Hak cipta dilindungi.</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
  </html>
  `;

  if (!t) {
    console.log(`\n======================================================`);
    console.log(`[SIMULASI EMAIL OTP - SMTP BELUM DIKONFIGURASI]`);
    console.log(`Kepada: ${toEmail}`);
    console.log(`Subjek: ${subject}`);
    console.log(`Kode OTP: ${code}`);
    console.log(`Berlaku: 5 Menit`);
    console.log(`======================================================\n`);
    return { success: true, message: 'Kode OTP telah dibuat (cek console server jika SMTP belum diatur).' };
  }

  try {
    await t.sendMail({
      from: `"${fromName}" <${fromEmail}>`,
      to: toEmail,
      subject,
      text: textBody,
      html: htmlBody,
    });
    return { success: true, message: 'Kode OTP telah berhasil dikirim ke alamat email Anda.' };
  } catch (err) {
    console.error('Gagal mengirim email OTP via SMTP:', err.message);
    console.log(`[FALLBACK OTP CODE FOR ${toEmail}]: ${code}`);
    return { success: true, message: 'Kode OTP dikirim (fallback mode).' };
  }
}

module.exports = { sendOtpEmail };
