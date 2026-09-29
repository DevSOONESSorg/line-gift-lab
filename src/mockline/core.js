// =====================================================
// 疑似LINE（LINE社の役）の中身
//
// 本物のLINEでは、この部分は LINE社のサーバーの中にあって、私たちからは見えません。
// 教材では「LINE社の中で何が起きているか」を見えるようにするため、自分で作っています。
// DB も自社サービスとは別のファイル（data/mock-line.db）です。
// =====================================================
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');
const util = require('../util');
const log = require('../log');
const config = require('../config');

const DB_PATH = path.join(__dirname, '..', '..', 'data', 'mock-line.db');
fs.mkdirSync(path.dirname(DB_PATH), { recursive: true });
const db = new Database(DB_PATH);
db.pragma('foreign_keys = ON');

db.exec(`
  -- ログインする人（ビジネスアカウント）
  CREATE TABLE IF NOT EXISTS accounts (
    id    TEXT PRIMARY KEY,          -- personal / company
    name  TEXT NOT NULL,
    email TEXT NOT NULL
  );

  -- プロバイダー（会社の「箱」。チャネルはどれかのプロバイダーに入る）
  CREATE TABLE IF NOT EXISTS providers (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS provider_members (
    provider_id INTEGER NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    account_id  TEXT NOT NULL,
    role        TEXT NOT NULL DEFAULT 'admin',
    PRIMARY KEY (provider_id, account_id)
  );

  -- LINE公式アカウント
  CREATE TABLE IF NOT EXISTS official_accounts (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT NOT NULL,
    basic_id        TEXT NOT NULL UNIQUE,   -- @123abcde
    bot_user_id     TEXT NOT NULL,          -- Webhook の destination に入るID
    industry        TEXT NOT NULL DEFAULT '',
    response_mode   TEXT NOT NULL DEFAULT 'bot',  -- bot / chat
    auto_reply_on   INTEGER NOT NULL DEFAULT 1,   -- 応答メッセージ
    auto_reply_text TEXT NOT NULL DEFAULT '',
    greeting_on     INTEGER NOT NULL DEFAULT 1,   -- あいさつメッセージ
    greeting_text   TEXT NOT NULL DEFAULT '',
    is_platform     INTEGER NOT NULL DEFAULT 0,   -- サービス運営側の公式アカウントか
    created_at      TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );
  CREATE TABLE IF NOT EXISTS oa_members (
    oa_id      INTEGER NOT NULL REFERENCES official_accounts(id) ON DELETE CASCADE,
    account_id TEXT NOT NULL,
    role       TEXT NOT NULL,              -- admin（管理者） / operator（運用担当者）
    PRIMARY KEY (oa_id, account_id)
  );
  CREATE TABLE IF NOT EXISTS invites (
    token      TEXT PRIMARY KEY,
    oa_id      INTEGER NOT NULL REFERENCES official_accounts(id) ON DELETE CASCADE,
    role       TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    used_by    TEXT
  );

  -- チャネル（Messaging API / LINEログイン）
  CREATE TABLE IF NOT EXISTS channels (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    channel_id  TEXT NOT NULL UNIQUE,      -- 10桁の数字
    type        TEXT NOT NULL,             -- messaging / login
    name        TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    provider_id INTEGER NOT NULL REFERENCES providers(id),
    oa_id       INTEGER REFERENCES official_accounts(id) ON DELETE CASCADE,
    secret      TEXT NOT NULL,
    access_token TEXT,                     -- 長期チャネルアクセストークン（発行するまで空）
    webhook_url TEXT NOT NULL DEFAULT '',
    use_webhook INTEGER NOT NULL DEFAULT 0,
    privacy_url TEXT NOT NULL DEFAULT '',
    created_by  TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );
  CREATE TABLE IF NOT EXISTS channel_roles (
    channel_id INTEGER NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    account_id TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'admin',
    PRIMARY KEY (channel_id, account_id)
  );

  -- LIFFアプリ（LINEログインチャネルの中に作る）
  CREATE TABLE IF NOT EXISTS liff_apps (
    liff_id      TEXT PRIMARY KEY,
    channel_id   INTEGER NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    name         TEXT NOT NULL,
    size         TEXT NOT NULL,
    endpoint_url TEXT NOT NULL,
    scopes       TEXT NOT NULL
  );

  -- リッチメニュー
  CREATE TABLE IF NOT EXISTS rich_menus (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    oa_id      INTEGER NOT NULL REFERENCES official_accounts(id) ON DELETE CASCADE,
    title      TEXT NOT NULL,
    template   TEXT NOT NULL,
    image_path TEXT NOT NULL,
    width      INTEGER NOT NULL,
    height     INTEGER NOT NULL,
    bar_text   TEXT NOT NULL DEFAULT 'メニュー',
    actions    TEXT NOT NULL,              -- JSON: [{type:'link'|'text'|'none', value:''}]
    is_default INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
  );

  -- スマホ側：LINEユーザー（体験ではあなた1人）
  CREATE TABLE IF NOT EXISTS line_users (
    user_id      TEXT PRIMARY KEY,         -- U + 32桁
    display_name TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS friends (
    oa_id    INTEGER NOT NULL REFERENCES official_accounts(id) ON DELETE CASCADE,
    user_id  TEXT NOT NULL,
    blocked  INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (oa_id, user_id)
  );
  CREATE TABLE IF NOT EXISTS messages (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    oa_id     INTEGER NOT NULL REFERENCES official_accounts(id) ON DELETE CASCADE,
    user_id   TEXT NOT NULL,
    direction TEXT NOT NULL,               -- in（ユーザー→公式） / out（公式→ユーザー）
    via       TEXT NOT NULL DEFAULT '',    -- user / bot / auto / greeting
    text      TEXT NOT NULL,
    at        TEXT NOT NULL
  );
  CREATE TABLE IF NOT EXISTS reply_tokens (
    token      TEXT PRIMARY KEY,
    channel_id INTEGER NOT NULL,
    user_id    TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    used       INTEGER NOT NULL DEFAULT 0
  );
`);

// =====================================================
// 最初に入れておくデータ
// =====================================================
const PLATFORM_PROVIDER = 'おくりギフト運営プロバイダー（株式会社サンプル）';

function seed() {
  if (db.prepare('SELECT COUNT(*) c FROM accounts').get().c > 0) return false;

  db.prepare('INSERT INTO accounts VALUES (?, ?, ?)').run('personal', 'あなたの個人アカウント（オーナー役）', 'you@example.com');
  db.prepare('INSERT INTO accounts VALUES (?, ?, ?)').run('company', '会社の作業アカウント（support役）', 'support@okuri-gift.example');

  const pid = db.prepare('INSERT INTO providers (name) VALUES (?)').run(PLATFORM_PROVIDER).lastInsertRowid;
  db.prepare('INSERT INTO provider_members VALUES (?, ?, ?)').run(pid, 'company', 'admin');

  db.prepare('INSERT INTO line_users VALUES (?, ?)').run('U' + util.hex(32), 'あなた');
  return true;
}

// =====================================================
// よく使う読み出し
// =====================================================
const q = {
  account: (id) => db.prepare('SELECT * FROM accounts WHERE id = ?').get(id),
  accounts: () => db.prepare('SELECT * FROM accounts ORDER BY id DESC').all(),
  me: () => db.prepare('SELECT * FROM line_users LIMIT 1').get(),
  oa: (id) => db.prepare('SELECT * FROM official_accounts WHERE id = ?').get(id),
  oaByBasicId: (b) => db.prepare('SELECT * FROM official_accounts WHERE lower(basic_id) = lower(?)').get(String(b || '').trim()),
  channel: (id) => db.prepare('SELECT * FROM channels WHERE id = ?').get(id),
  channelByChannelId: (cid) => db.prepare('SELECT * FROM channels WHERE channel_id = ?').get(String(cid || '').trim()),
  channelByToken: (t) => db.prepare('SELECT * FROM channels WHERE access_token = ? AND access_token IS NOT NULL').get(t),
  messagingChannelOfOa: (oaId) => db.prepare("SELECT * FROM channels WHERE oa_id = ? AND type = 'messaging'").get(oaId),
  provider: (id) => db.prepare('SELECT * FROM providers WHERE id = ?').get(id),
  providersOf: (acc) => db.prepare(`SELECT p.* FROM providers p JOIN provider_members m ON m.provider_id = p.id
                                    WHERE m.account_id = ? ORDER BY p.id`).all(acc),
  oasOf: (acc) => db.prepare(`SELECT o.*, m.role FROM official_accounts o JOIN oa_members m ON m.oa_id = o.id
                              WHERE m.account_id = ? ORDER BY o.id`).all(acc),
  oaRole: (oaId, acc) => (db.prepare('SELECT role FROM oa_members WHERE oa_id = ? AND account_id = ?').get(oaId, acc) || {}).role,
  channelRole: (chId, acc) => (db.prepare('SELECT role FROM channel_roles WHERE channel_id = ? AND account_id = ?').get(chId, acc) || {}).role,
  liff: (liffId) => db.prepare('SELECT * FROM liff_apps WHERE liff_id = ?').get(String(liffId || '').trim()),
  defaultRichMenu: (oaId) => db.prepare('SELECT * FROM rich_menus WHERE oa_id = ? AND is_default = 1 ORDER BY id DESC LIMIT 1').get(oaId),
  isFriend: (oaId, userId) => !!db.prepare('SELECT 1 FROM friends WHERE oa_id = ? AND user_id = ? AND blocked = 0').get(oaId, userId),
};

// =====================================================
// 公式アカウント・チャネルを作る
// =====================================================
function createOfficialAccount({ name, industry = '', owner, isPlatform = 0 }) {
  let basicId;
  do { basicId = '@' + util.digits(3) + util.alnum(5).replace(/[0-9]/g, 'x'); } while (q.oaByBasicId(basicId));
  const id = db.prepare(`INSERT INTO official_accounts
      (name, basic_id, bot_user_id, industry, auto_reply_text, greeting_text, is_platform)
      VALUES (?, ?, ?, ?, ?, ?, ?)`)
    .run(name, basicId, 'U' + util.hex(32), industry, config.DEFAULT_AUTO_REPLY,
      `${name}です。${config.DEFAULT_GREETING}`, isPlatform).lastInsertRowid;
  db.prepare('INSERT INTO oa_members VALUES (?, ?, ?)').run(id, owner, 'admin');
  log.ok('line', `公式アカウント「${name}」を作成しました（${basicId}）`, `作成したアカウント: ${q.account(owner).name}\n→ この人が「管理者」になります`);
  return q.oa(id);
}

function newChannelId() {
  let cid;
  do { cid = '2' + util.digits(9); } while (q.channelByChannelId(cid));
  return cid;
}

// Manager の「Messaging APIを利用する」
function enableMessagingApi(oa, { providerId, newProviderName, account, privacyUrl = '' }) {
  if (q.messagingChannelOfOa(oa.id)) return q.messagingChannelOfOa(oa.id);
  if (!providerId && newProviderName) {
    providerId = db.prepare('INSERT INTO providers (name) VALUES (?)').run(newProviderName).lastInsertRowid;
    db.prepare('INSERT INTO provider_members VALUES (?, ?, ?)').run(providerId, account, 'admin');
  }
  const id = db.prepare(`INSERT INTO channels (channel_id, type, name, provider_id, oa_id, secret, privacy_url, created_by)
                         VALUES (?, 'messaging', ?, ?, ?, ?, ?, ?)`)
    .run(newChannelId(), oa.name, providerId, oa.id, util.hex(32), privacyUrl, account).lastInsertRowid;
  // チャネルの権限は「有効化した人」だけに付く
  db.prepare('INSERT INTO channel_roles VALUES (?, ?, ?)').run(id, account, 'admin');
  const ch = q.channel(id);
  log.ok('line', `Messaging APIチャネルを作成しました（${ch.channel_id}）`,
    `プロバイダー: ${q.provider(providerId).name}\n作成者（チャネルの権限を持つ人）: ${q.account(account).name}`);
  return ch;
}

// Developers の「新規チャネル作成 → LINEログイン」
function createLoginChannel({ providerId, name, description, account }) {
  const id = db.prepare(`INSERT INTO channels (channel_id, type, name, description, provider_id, secret, created_by)
                         VALUES (?, 'login', ?, ?, ?, ?, ?)`)
    .run(newChannelId(), name, description || '', providerId, util.hex(32), account).lastInsertRowid;
  db.prepare('INSERT INTO channel_roles VALUES (?, ?, ?)').run(id, account, 'admin');
  const ch = q.channel(id);
  log.ok('line', `LINEログインチャネルを作成しました（${ch.channel_id}）`, `チャネル名: ${name}`);
  return ch;
}

function addLiff(channel, { name, size, endpointUrl, scopes }) {
  const liffId = `${channel.channel_id}-${util.alnum(8).replace(/^./, (c) => c.toUpperCase())}`;
  db.prepare('INSERT INTO liff_apps VALUES (?, ?, ?, ?, ?, ?)')
    .run(liffId, channel.id, name || 'LIFF', size || 'Full', endpointUrl, scopes || 'profile openid');
  log.ok('line', `LIFFアプリを追加しました（LIFF ID: ${liffId}）`, `エンドポイントURL: ${endpointUrl}\n→ https://liff.line.me/${liffId} を開くと、このURLのページがLINEの中で開きます`);
  return q.liff(liffId);
}

function issueToken(channel) {
  const token = util.hex(8) + '/' + Buffer.from(util.hex(48)).toString('base64').replace(/=+$/, '') + '=';
  db.prepare('UPDATE channels SET access_token = ? WHERE id = ?').run(token, channel.id);
  log.ok('line', `長期チャネルアクセストークンを発行しました（${channel.channel_id}）`,
    '以前のトークンがあれば、それはもう使えません（再発行＝古い鍵は無効）');
  return token;
}

function reissueSecret(channel) {
  const secret = util.hex(32);
  db.prepare('UPDATE channels SET secret = ? WHERE id = ?').run(secret, channel.id);
  log.info('line', `チャネルシークレットを再発行しました（${channel.channel_id}）`, '古いシークレットで署名を確かめているサーバーは、Webhookを受け取れなくなります');
  return secret;
}

// =====================================================
// メッセージのやりとり
// =====================================================
function addMessage(oaId, userId, direction, via, text) {
  db.prepare('INSERT INTO messages (oa_id, user_id, direction, via, text, at) VALUES (?, ?, ?, ?, ?, ?)')
    .run(oaId, userId, direction, via, text, util.now());
}

function newReplyToken(channel, userId) {
  const token = util.hex(32);
  db.prepare('INSERT INTO reply_tokens VALUES (?, ?, ?, ?, 0)').run(token, channel.id, userId, Date.now() + 60 * 1000);
  return token;
}

// Webhook の送信先URLを決める
// 「localhost」宛てなら、コンテナの中から自分自身（127.0.0.1:3000）に送る
function resolveWebhookTarget(url) {
  let u;
  try { u = new URL(url); } catch { return null; }
  if (['localhost', '127.0.0.1'].includes(u.hostname)) {
    return `http://127.0.0.1:3000${u.pathname}${u.search}`;
  }
  return u.toString();
}

// LINE社 → 自社サーバー へ Webhook を送る
async function sendWebhook(channel, events, { verify = false } = {}) {
  const oa = channel.oa_id ? q.oa(channel.oa_id) : null;
  const body = JSON.stringify({ destination: oa ? oa.bot_user_id : '', events });
  const signature = util.sign(channel.secret, body);
  const target = resolveWebhookTarget(channel.webhook_url);
  const what = verify ? '検証（Verify）' : events.map((e) => e.type).join(', ');

  if (!target) {
    log.ng('line', `Webhookを送れません（URLが正しくありません）: ${channel.webhook_url || '(空)'}`);
    return { ok: false, status: 0, message: 'Webhook URL が正しくありません' };
  }
  try {
    const res = await fetch(target, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Line-Signature': signature, 'User-Agent': 'LineBotWebhook/2.0 (mock)' },
      body,
      signal: AbortSignal.timeout(8000),
    });
    const text = await res.text().catch(() => '');
    const detail = `送信先: ${channel.webhook_url}\nX-Line-Signature: ${signature}\n（チャネルシークレット ${util.mask(channel.secret)} で計算）\n\n本文:\n${JSON.stringify(JSON.parse(body), null, 2)}\n\n返ってきた答え: ${res.status} ${text.slice(0, 200)}`;
    if (res.status === 200) log.ok('line', `Webhook送信 [${what}] → ${res.status} OK`, detail);
    else log.ng('line', `Webhook送信 [${what}] → ${res.status}`, detail);
    return { ok: res.status === 200, status: res.status, message: text.slice(0, 200) };
  } catch (e) {
    log.ng('line', `Webhook送信 [${what}] → つながりませんでした`, `送信先: ${channel.webhook_url}\nエラー: ${e.message}`);
    return { ok: false, status: 0, message: e.message };
  }
}

function baseEvent(type, userId, extra = {}) {
  return {
    type,
    mode: 'active',
    timestamp: Date.now(),
    source: { type: 'user', userId },
    webhookEventId: util.hex(26).toUpperCase(),
    deliveryContext: { isRedelivery: false },
    ...extra,
  };
}

// Webhook を送る条件がそろっているか
function webhookReady(oa) {
  const ch = q.messagingChannelOfOa(oa.id);
  if (!ch) return { ch: null, why: 'Messaging API が有効になっていない' };
  if (oa.response_mode !== 'bot') return { ch, why: '応答モードが「チャット」になっている' };
  if (!ch.use_webhook) return { ch, why: 'Webhook（Use webhook）がOFF' };
  if (!ch.webhook_url) return { ch, why: 'Webhook URL が空' };
  return { ch, why: null };
}

// 友だち追加
async function follow(oa, user) {
  const existed = db.prepare('SELECT * FROM friends WHERE oa_id = ? AND user_id = ?').get(oa.id, user.user_id);
  if (existed && !existed.blocked) return;
  db.prepare('INSERT OR REPLACE INTO friends VALUES (?, ?, 0)').run(oa.id, user.user_id);
  log.info('phone', `「${oa.name}」を友だち追加しました`, `あなたのユーザーID: ${user.user_id}`);
  if (oa.greeting_on && !existed) addMessage(oa.id, user.user_id, 'out', 'greeting', oa.greeting_text);

  const { ch, why } = webhookReady(oa);
  if (why) {
    log.info('line', `followイベントは自社サーバーに送りません（${why}）`);
    return;
  }
  await sendWebhook(ch, [baseEvent('follow', user.user_id, { replyToken: newReplyToken(ch, user.user_id) })]);
}

function block(oa, user, blocked) {
  db.prepare('UPDATE friends SET blocked = ? WHERE oa_id = ? AND user_id = ?').run(blocked ? 1 : 0, oa.id, user.user_id);
  log.info('phone', `「${oa.name}」を${blocked ? 'ブロック' : 'ブロック解除'}しました`);
  const { ch, why } = webhookReady(oa);
  if (!why) sendWebhook(ch, [baseEvent(blocked ? 'unfollow' : 'follow', user.user_id, blocked ? {} : { replyToken: newReplyToken(ch, user.user_id) })]);
}

// ユーザーがトークでメッセージを送った
async function userSendsText(oa, user, text) {
  addMessage(oa.id, user.user_id, 'in', 'user', text);
  log.info('phone', `「${oa.name}」に「${text}」と送信しました`);

  // 応答メッセージ（LINE社が自動で返す定型文）
  if (oa.auto_reply_on && oa.response_mode === 'bot') {
    addMessage(oa.id, user.user_id, 'out', 'auto', oa.auto_reply_text);
    log.info('line', '応答メッセージ（定型文）を自動で返しました', '応答メッセージがONなので、botの返事とは別にLINE社が返しています');
  }
  const { ch, why } = webhookReady(oa);
  if (why) {
    log.info('line', `メッセージを自社サーバーに送りません（${why}）`);
    return;
  }
  await sendWebhook(ch, [baseEvent('message', user.user_id, {
    replyToken: newReplyToken(ch, user.user_id),
    message: { id: util.digits(18), type: 'text', quoteToken: util.hex(20), text },
  })]);
}

module.exports = {
  db, q, seed, PLATFORM_PROVIDER,
  createOfficialAccount, enableMessagingApi, createLoginChannel, addLiff, issueToken, reissueSecret,
  addMessage, sendWebhook, follow, block, userSendsText, webhookReady, resolveWebhookTarget,
};
