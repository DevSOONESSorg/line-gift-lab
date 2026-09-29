// =====================================================
// 小さな便利関数
// =====================================================
const crypto = require('crypto');

const util = {
  // ランダムな16進数の文字列（例: 'a3f9...'）
  hex(len) {
    return crypto.randomBytes(Math.ceil(len / 2)).toString('hex').slice(0, len);
  },

  // ランダムな数字の文字列（先頭は0にしない）
  digits(len) {
    let s = String(1 + crypto.randomInt(9));
    while (s.length < len) s += crypto.randomInt(10);
    return s;
  },

  // 英小文字＋数字
  alnum(len) {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let s = '';
    for (let i = 0; i < len; i++) s += chars[crypto.randomInt(chars.length)];
    return s;
  },

  // LINEの署名と同じ計算：HMAC-SHA256(チャネルシークレット, 本文) を Base64 にしたもの
  sign(secret, body) {
    return crypto.createHmac('sha256', secret).update(body).digest('base64');
  },

  // 署名を比べる（タイミング攻撃対策で timingSafeEqual を使う）
  signatureMatches(secret, body, signature) {
    if (!secret || !signature) return false;
    const expected = Buffer.from(util.sign(secret, body));
    const actual = Buffer.from(String(signature));
    return expected.length === actual.length && crypto.timingSafeEqual(expected, actual);
  },

  // 画像ファイル（PNG / JPEG）の幅と高さを調べる
  imageSize(buf) {
    if (!buf || buf.length < 24) return null;
    // PNG
    if (buf.readUInt32BE(0) === 0x89504e47) {
      return { type: 'png', width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
    }
    // JPEG
    if (buf[0] === 0xff && buf[1] === 0xd8) {
      let i = 2;
      while (i < buf.length) {
        if (buf[i] !== 0xff) { i++; continue; }
        const marker = buf[i + 1];
        const len = buf.readUInt16BE(i + 2);
        if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) {
          return { type: 'jpeg', height: buf.readUInt16BE(i + 5), width: buf.readUInt16BE(i + 7) };
        }
        i += 2 + len;
      }
    }
    return null;
  },

  yen(n) {
    return '¥' + Number(n || 0).toLocaleString('ja-JP');
  },

  // Cookie を1つ読む（cookie-parser を使わない簡易版）
  cookie(req, name) {
    const raw = req.headers.cookie || '';
    for (const part of raw.split(';')) {
      const [k, ...v] = part.trim().split('=');
      if (k === name) return decodeURIComponent(v.join('='));
    }
    return '';
  },

  now() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  },

  // 文字列を短く切る（ログ表示用）
  mask(s, keep = 4) {
    if (!s) return '(空)';
    s = String(s);
    return s.length <= keep * 2 ? s : `${s.slice(0, keep)}…${s.slice(-keep)}`;
  },
};

module.exports = util;
