// =====================================================
// 裏側ビュー用の記録係
// 「スマホ」「LINE社」「自社サーバー」の間で何が起きたかを記録します。
// どちらのシステムにも属さない「観察カメラ」なので、DBも別ファイル（data/inside.db）です。
// =====================================================
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');
const util = require('./util');

const DB_PATH = path.join(__dirname, '..', 'data', 'inside.db');
fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
const db = new Database(DB_PATH);

db.exec(`
  CREATE TABLE IF NOT EXISTS logs (
    id     INTEGER PRIMARY KEY AUTOINCREMENT,
    at     TEXT NOT NULL,
    side   TEXT NOT NULL,     -- phone / line / app / admin
    level  TEXT NOT NULL,     -- info / ok / ng
    title  TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT ''
  );
`);

const SIDES = {
  phone: 'スマホ（お客さん）',
  line: 'LINE社（疑似）',
  app: '自社サーバー',
  admin: '管理画面',
};

function add(side, level, title, detail) {
  let text = '';
  if (detail !== undefined && detail !== null) {
    text = typeof detail === 'string' ? detail : JSON.stringify(detail, null, 2);
  }
  db.prepare('INSERT INTO logs (at, side, level, title, detail) VALUES (?, ?, ?, ?, ?)')
    .run(util.now(), side, level, title, text.slice(0, 5000));
}

module.exports = {
  SIDES,
  info: (side, title, detail) => add(side, 'info', title, detail),
  ok: (side, title, detail) => add(side, 'ok', title, detail),
  ng: (side, title, detail) => add(side, 'ng', title, detail),
  list(afterId = 0, limit = 200) {
    return db.prepare('SELECT * FROM logs WHERE id > ? ORDER BY id DESC LIMIT ?').all(afterId, limit);
  },
  clear() { db.exec('DELETE FROM logs'); },
  db,
};
