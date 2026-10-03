const { pool } = require('./db');

/** Padanan dlm_log_event() di PHP. Kegagalan pencatatan tidak boleh menghentikan request. */
async function logEvent(req, userId, action, metadata = {}, entityType = null, entityId = null) {
  try {
    await pool.query(
      'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, metadata, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
      [
        userId,
        String(action).slice(0, 50),
        entityType,
        entityId === null ? null : String(entityId).slice(0, 50),
        metadata && Object.keys(metadata).length ? JSON.stringify(metadata) : null,
        req && req.ip ? String(req.ip).slice(0, 45) : null,
      ]
    );
  } catch (err) {
    console.error('Gagal mencatat audit log:', err.message);
  }
}

module.exports = { logEvent };
