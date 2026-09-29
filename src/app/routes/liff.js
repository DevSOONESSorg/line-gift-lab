// =====================================================
// LIFF の画面（LINEの中で開くWebページ）
//
//   /liff/s/{slug}          … お客さんが「贈る」画面（店舗ごと）
//   /liff/platform/register … お店の人の「出店登録」（運営LINEのリッチメニューから）
//   /liff/platform/manage   … お店の人の「店舗管理」＝贈り物一覧・受け取る
//
// 「誰が開いたか」の分かり方:
//   ・疑似スマホから開いたとき … URL に mock_uid が付いてくる
//   ・本物のLINEで開いたとき   … 画面の中で LIFF SDK が LINE のプロフィールを取り出す
// =====================================================
const express = require('express');
const multer = require('multer');
const db = require('../db');
const log = require('../../log');
const util = require('../../util');
const config = require('../../config');
const line = require('../lineClient');
const platform = require('../platform');
const mock = require('../../mockline/core');

const router = express.Router();
router.use(express.urlencoded({ extended: false }));
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 3 * 1024 * 1024 } });

// 疑似スマホから来た人の情報
function mockUser(uid) {
  if (!uid) return null;
  return mock.db.prepare('SELECT * FROM line_users WHERE user_id = ?').get(uid) || null;
}

function friendLink(store, uid) {
  if (!store.basic_id) return '';
  return uid ? `/mock/phone?add=${encodeURIComponent(store.basic_id)}` : `https://line.me/R/ti/p/${encodeURIComponent(store.basic_id)}`;
}

// =====================================================
// お客さんの「贈る」画面
// =====================================================
router.get('/s/:slug', (req, res) => {
  const store = db.prepare('SELECT * FROM stores WHERE slug = ?').get(req.params.slug);
  const uid = req.query.mock_uid || '';
  if (!store) {
    log.ng('app', `LIFF画面: slug「${req.params.slug}」の店舗はありません`, 'LIFF のエンドポイントURL に入れた slug が正しいか確認しましょう');
    return res.status(404).render('liff/message', { title: '店舗が見つかりません', message: `「${req.params.slug}」というお店はありません。` });
  }
  if (!store.approved) {
    log.info('app', `LIFF画面:「${store.name}」はまだ承認されていないので表示しません`);
    return res.render('liff/message', { title: '準備中', message: `${store.name} は現在準備中です。` });
  }
  if (req.query.mock_liff && req.query.mock_liff !== store.liff_id) {
    log.ng('app', `LIFF ${req.query.mock_liff} から「${store.name}」の画面が開かれました`,
      `この店舗に登録されている LIFF ID は ${store.liff_id || '(未設定)'} です。\n別のお店の LIFF のエンドポイントURL が、この店の slug になっていませんか？`);
  } else {
    log.info('app', `LIFF画面「${store.name}」の贈る画面を表示しました`, uid ? `開いた人: ${uid}` : '本物のLINE（LIFF SDK）で開かれました');
  }
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
  const user = mockUser(uid);
  res.render('liff/store', {
    title: `${store.name}で贈る`, store, menus, uid, user, error: '', form: {},
    isFriend: user && store.basic_id ? (() => { const oa = mock.q.oaByBasicId(store.basic_id); return oa ? mock.q.isFriend(oa.id, uid) : false; })() : null,
    friendUrl: friendLink(store, uid),
    liffId: store.liff_id,
  });
});

router.post('/s/:slug/order', upload.single('id_image'), async (req, res) => {
  const store = db.prepare('SELECT * FROM stores WHERE slug = ? AND approved = 1').get(req.params.slug);
  if (!store) return res.status(404).render('liff/message', { title: '店舗が見つかりません', message: '' });
  const b = req.body;
  const uid = b.mock_uid || '';
  const buyerId = uid || b.buyer_uid;
  const buyerName = uid ? (mockUser(uid) || {}).display_name : b.buyer_name;
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
  const menu = menus.find((m) => m.id === Number(b.menu_id));

  const fail = (error) => res.render('liff/store', {
    title: `${store.name}で贈る`, store, menus, uid, user: mockUser(uid), error, form: b, isFriend: null,
    friendUrl: friendLink(store, uid), liffId: store.liff_id,
  });

  if (!buyerId) return fail('LINEのユーザー情報が取れませんでした。LINEの中で開き直してください。');
  if (!menu) return fail('メニューを選んでください。');
  if (!String(b.recipient || '').trim()) return fail('贈る相手（宛先）を入れてください。');
  if (store.id_required && !req.file) return fail('このお店は身分証の画像が必要です。');
  const card = String(b.card_number || '').replace(/\s/g, '');
  const result = config.TEST_CARDS[card];
  if (!result) return fail('カード番号が正しくありません（体験ではテストカード 4242 4242 4242 4242 を使います）。');
  const [mm, yy] = String(b.exp || '').split('/').map(Number);
  const now = new Date();
  if (!mm || mm > 12 || !yy || (2000 + yy) * 12 + mm < now.getFullYear() * 12 + now.getMonth() + 1) return fail('有効期限は「月/年」の形（例 12/40）で、今日以降にしてください。');
  if (!/^\d{3}$/.test(String(b.cvc || ''))) return fail('CVC は3桁の数字です。');
  if (result === 'declined') {
    log.ng('app', 'カード決済 → 拒否されました（テストカード 4000 0000 0000 0002）');
    return fail('カードが拒否されました。別のカードをお試しください。');
  }

  const fee = Math.round(menu.price * store.fee_rate / 100);
  const id = db.prepare(`INSERT INTO orders (store_id, menu_id, menu_name, amount, fee, buyer_line_user_id, buyer_name, recipient, message, id_image, card_last4)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
    .run(store.id, menu.id, menu.name, menu.price, fee, buyerId, buyerName || '(名前不明)', b.recipient.trim(), b.message || '',
      req.file ? req.file.originalname : '', card.slice(-4)).lastInsertRowid;
  log.ok('app', `注文 #${id} を受け付けました → 状態「リクエスト中」`,
    `店舗: ${store.name}\nメニュー: ${menu.name} ${util.yen(menu.price)}（手数料 ${store.fee_rate}% = ${util.yen(fee)}）\nカード: **** ${card.slice(-4)} は「仮押さえ」だけ。まだお金は確定していません。\n→ お店の人が「受け取る」を押したときに確定します`);

  // 買った人へ：店舗の公式LINEからお知らせ（店舗のトークンを使う）
  if (store.access_token) {
    await line.push(line.storeConn(store), buyerId, `${menu.name} の贈り物を受け付けました（注文番号 #${id}）。\nお店で受け取られると完了です。`);
  }
  // お店の人へ：運営の公式LINEからお知らせ（運営のトークンを使う）
  if (store.manager_line_user_id) {
    await line.push(platform.conn(), store.manager_line_user_id, `【${store.name}】新しい贈り物が届きました（#${id} ${menu.name}）。\nメニューの「店舗管理」→ 贈り物一覧 から確認できます。`);
  }
  res.render('liff/done', { title: 'ありがとうございました', store, orderId: id, menu, uid });
});

// =====================================================
// お店の人向け（運営LINEのリッチメニューから開く）
// =====================================================
function needUid(req, res, next) {
  const uid = req.query.mock_uid || req.body.mock_uid;
  if (!uid) {
    return res.render('liff/message', { title: '疑似スマホから開いてください', message: 'この画面は「おくりギフト(dev)」のリッチメニューから開きます。' });
  }
  req.uid = uid;
  res.locals.uid = uid;
  next();
}

router.get('/platform/register', needUid, (req, res) => {
  res.render('liff/register', { title: '出店登録', error: '', form: {} });
});

router.post('/platform/register', needUid, (req, res) => {
  const b = req.body;
  const name = String(b.name || '').trim();
  if (!name) return res.render('liff/register', { title: '出店登録', error: '店名を入れてください。', form: b });
  const tmp = 'tmp-' + util.alnum(8);
  const id = db.prepare('INSERT INTO stores (name, slug, fee_rate, manager_line_user_id) VALUES (?, ?, ?, ?)')
    .run(name, tmp, config.DEFAULT_FEE_RATE, req.uid).lastInsertRowid;
  db.prepare('UPDATE stores SET slug = ? WHERE id = ?').run(`store-${id}`, id);
  if (b.menu_name && Number(b.menu_price) > 0) {
    db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)').run(id, b.menu_name.trim(), Math.floor(Number(b.menu_price)));
  }
  log.ok('app', `出店登録を受け付けました：「${name}」（未承認）`,
    `仮の slug: store-${id}\n店舗管理者: 登録した人のLINEユーザーID ${req.uid}\n→ 管理画面で承認し、slug を決めてください`);
  res.render('liff/message', { title: '出店登録を受け付けました', message: `「${name}」を受け付けました。運営の承認をお待ちください。`, back: `/liff/platform/manage?mock_uid=${req.uid}` });
});

router.get('/platform/manage', needUid, (req, res) => {
  const stores = db.prepare(`SELECT s.*, (SELECT COUNT(*) FROM orders o WHERE o.store_id = s.id AND o.status = 'requested') AS waiting
                             FROM stores s WHERE manager_line_user_id = ? ORDER BY id`).all(req.uid);
  res.render('liff/manage', { title: '店舗管理', stores });
});

function ownStore(req, res, next) {
  const store = db.prepare('SELECT * FROM stores WHERE id = ?').get(req.params.storeId);
  if (!store || store.manager_line_user_id !== req.uid) {
    return res.status(403).render('liff/message', { title: '権限がありません', message: 'このお店の店舗管理者ではありません。' });
  }
  req.store = store;
  next();
}

router.get('/platform/manage/:storeId', needUid, ownStore, (req, res) => {
  const orders = db.prepare('SELECT * FROM orders WHERE store_id = ? ORDER BY id DESC').all(req.store.id);
  res.render('liff/gifts', { title: '贈り物一覧', store: req.store, orders, yen: util.yen });
});

router.post('/platform/manage/:storeId/orders/:orderId/receive', needUid, ownStore, (req, res) => {
  const o = db.prepare('SELECT * FROM orders WHERE id = ? AND store_id = ?').get(req.params.orderId, req.store.id);
  if (o && o.status === 'requested') {
    db.prepare("UPDATE orders SET status = 'received', payment = 'captured', received_at = datetime('now','localtime') WHERE id = ?").run(o.id);
    log.ok('app', `注文 #${o.id} を「受け取る」→ 決済が確定しました（受取済み）`, `売上 ${util.yen(o.amount)} / 手数料 ${util.yen(o.fee)}`);
  }
  res.redirect(`/liff/platform/manage/${req.store.id}?mock_uid=${req.uid}`);
});

router.post('/platform/manage/:storeId/orders/:orderId/thanks', needUid, ownStore, async (req, res) => {
  const o = db.prepare('SELECT * FROM orders WHERE id = ? AND store_id = ?').get(req.params.orderId, req.store.id);
  if (o && o.status === 'received' && !o.thanks_sent) {
    const r = await line.push(line.storeConn(req.store), o.buyer_line_user_id,
      `${req.store.name}より：${o.recipient}への贈り物（${o.menu_name}）を受け取りました。ありがとうございました！` + (req.body.text ? `\n\n${req.body.text}` : ''));
    if (r.ok) db.prepare('UPDATE orders SET thanks_sent = 1 WHERE id = ?').run(o.id);
  }
  res.redirect(`/liff/platform/manage/${req.store.id}?mock_uid=${req.uid}`);
});

module.exports = router;
