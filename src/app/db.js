// =====================================================
// 自社サービス「おくりギフト」のデータベース（data/app.db）
// LINE社（疑似LINE）のデータとは別のファイルです。
// 自社で持っているのは「LINEの設定値のコピー」だけ、という点に注目してください。
// =====================================================
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');

const DB_PATH = path.join(__dirname, '..', '..', 'data', 'app.db');
fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
const db = new Database(DB_PATH);
db.pragma('foreign_keys = ON');

db.exec(`
  -- 代理店（1次・2次）
  CREATE TABLE IF NOT EXISTS agents (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    parent_id  INTEGER REFERENCES agents(id),   -- 空なら1次代理店
    rate       REAL NOT NULL DEFAULT 0,          -- 報酬率（%）
    code       TEXT NOT NULL UNIQUE,             -- 紹介コード（6桁）
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );

  -- 店舗
  CREATE TABLE IF NOT EXISTS stores (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    slug        TEXT NOT NULL UNIQUE,     -- URLに入る店舗の住所（例 club-azure）
    approved    INTEGER NOT NULL DEFAULT 0,
    fee_rate    REAL NOT NULL DEFAULT 10, -- デフォルト手数料率（%）
    listed      INTEGER NOT NULL DEFAULT 1, -- 共通掲載（運営LINEの「ギフトを贈る」の店舗一覧に出す）
    id_required INTEGER NOT NULL DEFAULT 0, -- 身分証提示を必須
    agent_id    INTEGER REFERENCES agents(id),
    manager_line_user_id TEXT,            -- 出店登録した人（店舗オーナー）のLINEユーザーID
    -- オーナーのユーザー登録・口座登録（体験用。本物の口座番号は入れない）
    owner_name   TEXT NOT NULL DEFAULT '',
    owner_phone  TEXT NOT NULL DEFAULT '',
    bank_name    TEXT NOT NULL DEFAULT '',
    bank_branch  TEXT NOT NULL DEFAULT '',
    bank_type    TEXT NOT NULL DEFAULT '',
    bank_number  TEXT NOT NULL DEFAULT '',
    bank_holder  TEXT NOT NULL DEFAULT '',
    wants_original INTEGER NOT NULL DEFAULT 0, -- 自前の公式LINE（オリジナル）でもやりたい
    -- LINE 連携設定（店舗専用チャネル）＝ 5つの値
    liff_id        TEXT NOT NULL DEFAULT '',
    channel_id     TEXT NOT NULL DEFAULT '',
    channel_secret TEXT NOT NULL DEFAULT '',
    access_token   TEXT NOT NULL DEFAULT '',
    basic_id       TEXT NOT NULL DEFAULT '',
    created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );

  -- メニュー（贈れる商品）
  CREATE TABLE IF NOT EXISTS menus (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    name     TEXT NOT NULL,
    price    INTEGER NOT NULL
  );

  -- 注文（贈り物）
  CREATE TABLE IF NOT EXISTS orders (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id     INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
    menu_id      INTEGER REFERENCES menus(id) ON DELETE SET NULL,
    menu_name    TEXT NOT NULL,
    amount       INTEGER NOT NULL,
    fee          INTEGER NOT NULL,
    buyer_line_user_id TEXT NOT NULL,
    buyer_name   TEXT NOT NULL,
    route        TEXT NOT NULL DEFAULT 'common', -- common（運営LINEの共通掲載から） / original（店の公式LINEから）
    recipient    TEXT NOT NULL,
    message      TEXT NOT NULL DEFAULT '',
    id_image     TEXT NOT NULL DEFAULT '',
    card_last4   TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'requested', -- requested（リクエスト中） / received（受取済み）
    payment      TEXT NOT NULL DEFAULT 'authorized', -- authorized（仮押さえ） / captured（確定）
    thanks_sent  INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    received_at  TEXT
  );

  -- お客さんが登録したカード（初回だけ入力。2回目からはこれを使う）
  -- 本物のサービスでは、カード番号は決済会社が保管し、自社は「どのカードか」の目印だけを持つ
  CREATE TABLE IF NOT EXISTS cards (
    line_user_id TEXT PRIMARY KEY,
    last4        TEXT NOT NULL,
    exp          TEXT NOT NULL,
    created_at   TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );

  -- サービス全体の設定（運営の公式LINEの値など）
  CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
  );
`);

db.setting = (key, value) => {
  if (value === undefined) return (db.prepare('SELECT value FROM settings WHERE key = ?').get(key) || {}).value || '';
  db.prepare('INSERT OR REPLACE INTO settings VALUES (?, ?)').run(key, String(value));
};

module.exports = db;
