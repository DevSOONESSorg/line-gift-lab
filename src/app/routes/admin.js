// =====================================================
// 管理画面（運営スタッフが使う画面）
// 店舗管理・注文管理・ユーザー管理・売上集計・代理店管理
// =====================================================
const express = require('express');
const QRCode = require('qrcode');
const db = require('../db');
const log = require('../../log');
const util = require('../../util');
const line = require('../lineClient');
const platform = require('../platform');
const publicUrl = require('../publicUrl');

const router = express.Router();
router.use(express.urlencoded({ extended: false }));

// どの画面でも使う値
router.use((req, res, next) => {
  res.locals.yen = util.yen;
  res.locals.platformBasicId = platform.basicId();
  res.locals.path = req.path;
  next();
});

router.get('/', (req, res) => res.redirect('/admin/stores'));

// =====================================================
// 店舗管理
// =====================================================
router.get('/stores', (req, res) => {
  const stores = db.prepare(`SELECT s.*, a.name AS agent_name,
      (SELECT COUNT(*) FROM menus m WHERE m.store_id = s.id) AS menu_count
    FROM stores s LEFT JOIN agents a ON a.id = s.agent_id ORDER BY s.id DESC`).all();
  res.render('admin/stores', { title: '店舗管理', stores });
});

// コースB用：管理画面から直接店舗を作る
router.post('/stores', (req, res) => {
  const name = String(req.body.name || '').trim();
  const slug = String(req.body.slug || '').trim();
  if (!name || !/^[a-z0-9][a-z0-9-]{1,40}$/.test(slug) || db.prepare('SELECT 1 FROM stores WHERE slug = ?').get(slug)) {
    return res.redirect('/admin/stores?msg=' + encodeURIComponent('店名と、まだ使われていない slug（半角英小文字・数字・ハイフン）を入れてください'));
  }
  const id = db.prepare('INSERT INTO stores (name, slug, approved, listed, wants_original) VALUES (?, ?, 1, 0, 1)').run(name, slug).lastInsertRowid;
  log.info('admin', `管理画面から店舗「${name}」（${slug}）を作成しました`);
  res.redirect(`/admin/stores/${id}`);
});

function loadStore(req, res, next) {
  const store = db.prepare('SELECT * FROM stores WHERE id = ?').get(req.params.id);
  if (!store) return res.status(404).render('error', { title: '見つかりません', message: 'その店舗はありません。' });
  req.store = store;
  next();
}

router.get('/stores/:id', loadStore, async (req, res) => {
  const store = req.store;
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
  const agent = store.agent_id ? db.prepare('SELECT * FROM agents WHERE id = ?').get(store.agent_id) : null;
  const liffUrl = store.liff_id ? `https://liff.line.me/${store.liff_id}` : '';
  const qr = liffUrl ? await QRCode.toDataURL(liffUrl, { margin: 1, width: 180 }) : '';
  res.render('admin/store-show', { title: store.name, store, menus, agent, liffUrl, qr });
});

router.post('/stores/:id/menus', loadStore, (req, res) => {
  const name = String(req.body.name || '').trim();
  const price = Math.floor(Number(req.body.price));
  if (name && price > 0) db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)').run(req.store.id, name, price);
  res.redirect(`/admin/stores/${req.store.id}?msg=` + encodeURIComponent(name && price > 0 ? 'メニューを追加しました' : '商品名と価格を入れてください'));
});
router.post('/stores/:id/menus/:menuId/delete', loadStore, (req, res) => {
  db.prepare('DELETE FROM menus WHERE id = ? AND store_id = ?').run(req.params.menuId, req.store.id);
  res.redirect(`/admin/stores/${req.store.id}`);
});

// ---- 編集（基本情報・管理設定・LINE連携設定） ----
async function editView(req, res, store, extra = {}) {
  const agents = db.prepare(`SELECT a.*, p.name AS parent_name FROM agents a LEFT JOIN agents p ON p.id = a.parent_id
                             ORDER BY COALESCE(a.parent_id, a.id), a.parent_id IS NOT NULL, a.id`).all();
  const local = `${publicUrl.localBase()}/api/webhook/line/store/${store.slug}`;
  const tunnel = await publicUrl.tunnelUrl();
  res.render('admin/store-edit', {
    title: `${store.name} を編集`, store, agents,
    webhookLocal: local,
    webhookPublic: tunnel ? `${tunnel}/api/webhook/line/store/${store.slug}` : '',
    liffEndpointLocal: `${publicUrl.localBase()}/liff/s/${store.slug}`,
    liffEndpointPublic: tunnel ? `${tunnel}/liff/s/${store.slug}` : '',
    checks: null, testResult: null, error: '', ...extra,
  });
}

router.get('/stores/:id/edit', loadStore, (req, res) => editView(req, res, req.store));

router.post('/stores/:id/edit', loadStore, (req, res) => {
  const b = req.body;
  const store = req.store;
  const slug = String(b.slug || '').trim();
  if (!/^[a-z0-9][a-z0-9-]{1,40}$/.test(slug)) {
    return editView(req, res, { ...store, ...b }, { error: 'スラッグは半角の英小文字・数字・ハイフンで入れてください（例 club-azure）。' });
  }
  if (slug !== store.slug && db.prepare('SELECT 1 FROM stores WHERE slug = ? AND id <> ?').get(slug, store.id)) {
    return editView(req, res, { ...store, ...b }, { error: `スラッグ「${slug}」はほかの店舗が使っています。` });
  }
  // シークレットとトークンは「空欄なら変えない」
  const secret = String(b.channel_secret || '').trim() || store.channel_secret;
  const token = String(b.access_token || '').trim() || store.access_token;

  db.prepare(`UPDATE stores SET name = ?, slug = ?, approved = ?, fee_rate = ?, listed = ?, id_required = ?, agent_id = ?,
              liff_id = ?, channel_id = ?, channel_secret = ?, access_token = ?, basic_id = ? WHERE id = ?`)
    .run(String(b.name || store.name).trim(), slug, b.approved === '1' ? 1 : 0, Number(b.fee_rate) || 0,
      b.listed === '1' ? 1 : 0, b.id_required === '1' ? 1 : 0, b.agent_id ? Number(b.agent_id) : null,
      String(b.liff_id || '').trim(), String(b.channel_id || '').trim(), secret, token, String(b.basic_id || '').trim(), store.id);

  const changes = [];
  if (slug !== store.slug) changes.push(`slug: ${store.slug} → ${slug}（LIFFのエンドポイントURLとWebhook URLも変える必要があります！）`);
  if ((b.approved === '1') !== !!store.approved) changes.push(`承認: ${b.approved === '1' ? '承認済み' : '未承認'}`);
  if (secret !== store.channel_secret) changes.push('チャネルシークレットを変更');
  if (token !== store.access_token) changes.push('アクセストークンを変更');
  log.info('admin', `店舗「${store.name}」を更新しました`, changes.join('\n') || '（大きな変更なし）');
  res.redirect(`/admin/stores/${store.id}/edit?msg=` + encodeURIComponent('更新しました') + '#line');
});

// ---- 設定整合性チェック ----
router.post('/stores/:id/check', loadStore, async (req, res) => {
  const s = req.store;
  const checks = [];
  const add = (label, ok, hint) => checks.push({ label, ok, hint });

  add('LIFF ID の形', /^\d{10}-[A-Za-z0-9]{8}$/.test(s.liff_id), '「10桁の数字-8文字」の形です（例 2009896760-NLWo39Yw）。Developers の LINEログインチャネル → LIFF タブで確認');
  add('Messaging API チャネルID の形', /^\d{10}$/.test(s.channel_id), '10桁の数字です。Messaging APIチャネルの Basic settings で確認（LINEログインチャネルのIDと間違えやすい）');
  add('チャネルシークレット', /^[0-9a-f]{32}$/.test(s.channel_secret), '32文字の英数字です。Messaging APIチャネルの Basic settings で確認');
  add('アクセストークン（長期）', !!s.access_token, 'Messaging API タブで「発行」して貼り付けます');
  add('公式アカウントID（@xxx）の形', /^@[0-9a-z]{3,}/i.test(s.basic_id), '@ から始まるベーシックIDです。Manager の 設定 → アカウント設定 で確認');

  if (s.access_token && s.channel_id) {
    const r = await line.botInfo(line.storeConn(s));
    if (!r.ok) {
      add('トークンで LINE に接続できる', false, `LINEの答え: ${r.status} ${r.data.message || ''} → トークンを再発行して、管理画面に入れ直しましょう`);
    } else {
      add('トークンで LINE に接続できる', true, `このトークンは「${r.data.displayName}」${r.data.basicId} のものです`);
      const v = await line.verifyToken(line.storeConn(s));
      if (v.ok) {
        add('トークンとチャネルIDが同じチャネル', String(v.data.client_id) === s.channel_id,
          `トークンはチャネル ${v.data.client_id} のもの、入力されたチャネルIDは ${s.channel_id} です。LINEログインチャネルのIDを入れていませんか？`);
      }
      add('トークンと公式アカウントIDが同じアカウント', r.data.basicId.toLowerCase() === s.basic_id.toLowerCase(),
        `トークンは ${r.data.basicId} のもの、入力された公式アカウントIDは ${s.basic_id || '(空)'} です。どちらかが別のお店の値です`);
    }
  }
  const allOk = checks.every((c) => c.ok);
  (allOk ? log.ok : log.ng)('admin', `「${s.name}」の設定整合性チェック → ${allOk ? 'すべてOK' : 'NGあり'}`,
    checks.map((c) => `${c.ok ? 'OK' : 'NG'}  ${c.label}`).join('\n'));
  editView(req, res, s, { checks, allOk });
});

// ---- テスト送信（店舗管理者にメッセージを送ってみる） ----
router.post('/stores/:id/test-send', loadStore, async (req, res) => {
  const s = req.store;
  let testResult;
  if (!s.manager_line_user_id) testResult = { ok: false, message: '店舗管理者（出店登録した人）がいないので送り先がありません' };
  else {
    const r = await line.push(line.storeConn(s), s.manager_line_user_id, `【テスト送信】${s.name} の公式LINEからのテストです。`);
    testResult = { ok: r.ok, message: `${r.status} ${r.ok ? '送信しました（届いたかはスマホで確認）' : r.data.message || ''}` };
  }
  editView(req, res, s, { testResult });
});

// =====================================================
// 注文管理
// =====================================================
router.get('/orders', (req, res) => {
  const orders = db.prepare(`SELECT o.*, s.name AS store_name FROM orders o JOIN stores s ON s.id = o.store_id ORDER BY o.id DESC`).all();
  res.render('admin/orders', { title: '注文管理', orders });
});
router.get('/orders/:id', (req, res) => {
  const o = db.prepare(`SELECT o.*, s.name AS store_name, s.fee_rate FROM orders o JOIN stores s ON s.id = o.store_id WHERE o.id = ?`).get(req.params.id);
  if (!o) return res.status(404).render('error', { title: '見つかりません', message: 'その注文はありません。' });
  res.render('admin/order-show', { title: `注文 #${o.id}`, o });
});

// =====================================================
// ユーザー管理（贈り物を買ったLINEユーザー）
// =====================================================
router.get('/users', (req, res) => {
  const users = db.prepare(`SELECT buyer_line_user_id AS id, buyer_name AS name, COUNT(*) AS count, SUM(amount) AS total, MAX(created_at) AS last
                            FROM orders GROUP BY buyer_line_user_id ORDER BY last DESC`).all();
  const managers = db.prepare(`SELECT manager_line_user_id AS id, MAX(owner_name) AS name, GROUP_CONCAT(name, '、') AS stores
                               FROM stores WHERE manager_line_user_id IS NOT NULL GROUP BY manager_line_user_id`).all();
  const cards = Object.fromEntries(db.prepare('SELECT * FROM cards').all().map((c) => [c.line_user_id, c]));
  res.render('admin/users', { title: 'ユーザー管理', users, managers, cards });
});

// =====================================================
// 売上集計
// =====================================================
router.get('/sales', (req, res) => {
  const rows = db.prepare(`SELECT s.id, s.name, a.name AS agent_name, a.rate AS agent_rate,
      SUM(CASE WHEN o.status = 'received' THEN o.amount ELSE 0 END) AS sales,
      SUM(CASE WHEN o.status = 'received' THEN o.fee ELSE 0 END) AS fee,
      SUM(CASE WHEN o.status = 'requested' THEN o.amount ELSE 0 END) AS pending,
      COUNT(o.id) AS count
    FROM stores s LEFT JOIN orders o ON o.store_id = s.id
    LEFT JOIN agents a ON a.id = COALESCE((SELECT parent_id FROM agents WHERE id = s.agent_id), s.agent_id)
    GROUP BY s.id ORDER BY sales DESC`).all()
    .map((r) => ({ ...r, commission: Math.round((r.fee || 0) * (r.agent_rate || 0) / 100) }));
  res.render('admin/sales', { title: '売上集計', rows });
});

// =====================================================
// 代理店管理
// =====================================================
router.get('/agents', (req, res) => {
  const agents = db.prepare(`SELECT a.*, p.name AS parent_name,
      (SELECT COUNT(*) FROM stores s WHERE s.agent_id = a.id) AS store_count
    FROM agents a LEFT JOIN agents p ON p.id = a.parent_id
    ORDER BY COALESCE(a.parent_id, a.id), a.parent_id IS NOT NULL, a.id`).all();
  res.render('admin/agents', { title: '代理店管理', agents, parents: agents.filter((a) => !a.parent_id), creating: req.query.new === '1', error: '' });
});

router.post('/agents', (req, res) => {
  const name = String(req.body.name || '').trim();
  const parentId = req.body.parent_id ? Number(req.body.parent_id) : null;
  if (!name) return res.redirect('/admin/agents?new=1');
  // 2次代理店の報酬率は 0%（取り分は1次代理店から受け取るため）
  const rate = parentId ? 0 : Math.max(0, Number(req.body.rate) || 0);
  let code;
  do { code = util.digits(6); } while (db.prepare('SELECT 1 FROM agents WHERE code = ?').get(code));
  db.prepare('INSERT INTO agents (name, parent_id, rate, code) VALUES (?, ?, ?, ?)').run(name, parentId, rate, code);
  log.info('admin', `代理店「${name}」を登録しました（${parentId ? '2次' : '1次'}・紹介コード ${code}）`);
  res.redirect('/admin/agents?msg=' + encodeURIComponent(`登録しました。紹介コード ${code}`));
});

module.exports = router;
