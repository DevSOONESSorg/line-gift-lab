// =====================================================
// 初回起動時のデータづくり
//   ・運営の公式LINE「おくりギフト(dev)」（出店登録・店舗管理のリッチメニュー付き）
//   ・見本の店舗「見本フラワー」（共通掲載）と「見本カフェ」（オリジナル公式LINE・全部つながった完成形）
// どちらも「手順書の第1〜10章をやり終えた状態」を、プログラムで作っています。
// =====================================================
const path = require('path');
const fs = require('fs');
const mock = require('./mockline/core');
const db = require('./app/db');
const config = require('./config');
const log = require('./log');
const util = require('./util');

const UPLOAD_DIR = path.join(__dirname, '..', 'data', 'uploads', 'richmenu');
const LOCAL = `http://localhost:${config.HOST_PORT}`;

function richMenu(oa, title, template, sample, actions) {
  fs.mkdirSync(UPLOAD_DIR, { recursive: true });
  const file = `${oa.id}-seed.png`;
  const buf = fs.readFileSync(path.join(__dirname, '..', 'public', 'samples', sample));
  fs.writeFileSync(path.join(UPLOAD_DIR, file), buf);
  const size = util.imageSize(buf);
  mock.db.prepare(`INSERT INTO rich_menus (oa_id, title, template, image_path, width, height, bar_text, actions, is_default)
                   VALUES (?, ?, ?, ?, ?, ?, 'メニュー', ?, 1)`).run(oa.id, title, template, file, size.width, size.height, JSON.stringify(actions));
}

function run() {
  if (!mock.seed()) return; // 2回目以降の起動では何もしない
  const PLATFORM_PROVIDER_ID = 1;

  // ---------- 運営の公式LINE ----------
  const poa = mock.createOfficialAccount({ name: 'おくりギフト(dev)', industry: 'サービス', owner: 'company', isPlatform: 1 });
  mock.db.prepare('UPDATE official_accounts SET auto_reply_on = 0 WHERE id = ?').run(poa.id);
  const pch = mock.enableMessagingApi(poa, { providerId: PLATFORM_PROVIDER_ID, account: 'company' });
  const ptoken = mock.issueToken(pch);
  mock.db.prepare('UPDATE channels SET webhook_url = ?, use_webhook = 1 WHERE id = ?').run(`${LOCAL}/api/webhook/line/platform`, pch.id);
  const plogin = mock.createLoginChannel({ providerId: PLATFORM_PROVIDER_ID, name: 'おくりギフト(dev) 店舗向け', account: 'company' });
  const liffReg = mock.addLiff(plogin, { name: '出店登録', size: 'Full', endpointUrl: `${LOCAL}/liff/platform/register`, scopes: 'profile openid' });
  const liffMng = mock.addLiff(plogin, { name: '店舗管理', size: 'Full', endpointUrl: `${LOCAL}/liff/platform/manage`, scopes: 'profile openid' });
  const liffShops = mock.addLiff(plogin, { name: 'ギフトを贈る（共通掲載）', size: 'Full', endpointUrl: `${LOCAL}/liff/platform/shops`, scopes: 'profile openid' });
  richMenu(poa, '運営メニュー', 'small-3', 'platform-menu.png', [
    { type: 'link', value: `https://liff.line.me/${liffShops.liff_id}` }, // お客さん：共通掲載のお店から贈る
    { type: 'link', value: `https://liff.line.me/${liffReg.liff_id}` },   // オーナー：出店登録
    { type: 'link', value: `https://liff.line.me/${liffMng.liff_id}` },   // オーナー：店舗管理
  ]);
  db.setting('platform_channel_id', pch.channel_id);
  db.setting('platform_secret', pch.secret);
  db.setting('platform_token', ptoken);
  db.setting('platform_basic_id', poa.basic_id);

  // ---------- 見本フラワー（共通掲載のお店。自前の公式LINEは持たない） ----------
  const me = mock.q.me();
  const flowerId = db.prepare(`INSERT INTO stores (name, slug, approved, fee_rate, listed, manager_line_user_id,
                                 owner_name, owner_phone, bank_name, bank_branch, bank_type, bank_number, bank_holder)
                               VALUES ('見本フラワー', 'sample-flower', 1, 10, 1, ?, '見本 花子', '098-000-0001', 'さくら銀行', '那覇支店', '普通', '1234567', 'ミホン ハナコ')`)
    .run(me.user_id).lastInsertRowid;
  db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)').run(flowerId, 'ミニブーケ', 1500);
  db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)').run(flowerId, '季節の花束', 3000);

  // ---------- 見本カフェ（オリジナル：自前の公式LINEから贈るお店。完成形） ----------
  // 本番の流れと同じく「個人アカウントで作成 → 会社アカウントに権限を渡す → 会社側でMessaging API有効化」
  const soa = mock.createOfficialAccount({ name: '見本カフェ', industry: '飲食', owner: 'personal' });
  mock.db.prepare('INSERT INTO oa_members VALUES (?, ?, ?)').run(soa.id, 'company', 'operator');
  mock.db.prepare('UPDATE official_accounts SET auto_reply_on = 0 WHERE id = ?').run(soa.id);
  const sch = mock.enableMessagingApi(soa, { providerId: PLATFORM_PROVIDER_ID, account: 'company' });
  const stoken = mock.issueToken(sch);
  mock.db.prepare('UPDATE channels SET webhook_url = ?, use_webhook = 1 WHERE id = ?').run(`${LOCAL}/api/webhook/line/store/sample-cafe`, sch.id);
  const slogin = mock.createLoginChannel({ providerId: PLATFORM_PROVIDER_ID, name: '見本カフェ LIFF', account: 'company' });
  const sliff = mock.addLiff(slogin, { name: '見本カフェで贈る', size: 'Full', endpointUrl: `${LOCAL}/liff/s/sample-cafe`, scopes: 'profile openid' });
  richMenu(soa, '贈るボタン', 'small-1', 'sample-cafe-menu.png', [{ type: 'link', value: `https://liff.line.me/${sliff.liff_id}` }]);

  // あなたのスマホは、最初から「おくりギフト(dev)」と「見本カフェ」の友だち（お客さんとして）
  for (const oa of [poa, soa]) {
    mock.db.prepare('INSERT INTO friends VALUES (?, ?, 0)').run(oa.id, mock.q.me().user_id);
    mock.addMessage(oa.id, mock.q.me().user_id, 'out', 'greeting', oa.greeting_text);
  }

  const agentId = db.prepare('INSERT INTO agents (name, rate, code) VALUES (?, ?, ?)').run('うるま紹介センター', 20, util.digits(6)).lastInsertRowid;
  const storeId = db.prepare(`INSERT INTO stores (name, slug, approved, fee_rate, listed, wants_original, agent_id, manager_line_user_id,
                                liff_id, channel_id, channel_secret, access_token, basic_id,
                                owner_name, owner_phone, bank_name, bank_branch, bank_type, bank_number, bank_holder)
                              VALUES (?, 'sample-cafe', 1, 10, 0, 1, ?, ?, ?, ?, ?, ?, ?,
                                '見本 太郎', '098-000-0002', 'さくら銀行', '那覇支店', '普通', '7654321', 'ミホン タロウ')`)
    .run('見本カフェ', agentId, me.user_id, sliff.liff_id, sch.channel_id, sch.secret, stoken, soa.basic_id).lastInsertRowid;
  const ins = db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)');
  ins.run(storeId, 'コーヒーチケット', 500);
  ins.run(storeId, 'ケーキセット', 1200);
  ins.run(storeId, 'ランチ', 1500);

  log.clear(); // 初期データづくりの記録は消して、見やすくしておく
  log.info('app', '初期データを作りました（運営LINE「おくりギフト(dev)」、共通掲載の「見本フラワー」、オリジナル公式LINEの「見本カフェ」）');
}

module.exports = { run };
