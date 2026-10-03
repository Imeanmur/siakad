class HttpError extends Error {
  constructor(status, message, extra = {}) {
    super(message);
    this.status = status;
    this.extra = extra;
  }
}

/** Bungkus handler async agar error diteruskan ke error handler Express. */
const wrap = (fn) => (req, res, next) => Promise.resolve(fn(req, res, next)).catch(next);

/** Ambil string ter-trim; selain string menjadi ''. */
const clean = (v) => (typeof v === 'string' ? v.trim() : '');

const isEmail = (v) => v.length <= 100 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

const toId = (v) => {
  const n = Number(v);
  if (!Number.isInteger(n) || n <= 0) throw new HttpError(400, 'ID tidak valid.');
  return n;
};

const MIN_PASSWORD = 8;
const checkPassword = (pw) => {
  if (typeof pw !== 'string' || pw.length < MIN_PASSWORD) {
    throw new HttpError(400, `Password minimal ${MIN_PASSWORD} karakter.`);
  }
  if (pw.length > 72) throw new HttpError(400, 'Password maksimal 72 karakter.');
  return pw;
};

/** Normalisasi "HH:MM" atau "HH:MM:SS" menjadi "HH:MM"; null jika tidak valid. */
const toHHMM = (v) => {
  const m = /^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/.exec(clean(v));
  return m ? `${m[1]}:${m[2]}` : null;
};

module.exports = { HttpError, wrap, clean, isEmail, toId, checkPassword, toHHMM };
