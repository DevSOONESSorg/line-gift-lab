// =====================================================
// LIFF の画面（LINEの中で開くWebページ）
//
//   ＜お客さん＞
//   /liff/platform/shops    … 運営LINEの「ギフトを贈る」＝共通掲載のお店を選ぶ
//   /liff/s/{slug}          … お店の「贈る」画面（共通掲載からも、お店の公式LINEからもここに来る）
//   ＜お店のオーナー＞（運営LINEのリッチメニューから）
//   /liff/platform/register … 出店登録（ユーザー登録・お店の情報・口座登録・掲載方法）
//   /liff/platform/manage   … 店舗管理（商品登録・贈り物一覧・受け取る）
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

function savedCard(userId) {
  return db.prepare('SELECT * FROM cards WHERE line_user_id = ?').get(userId || '') || null;
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
  const route = req.query.via === 'common' ? 'common' : 'original';
  if (route === 'common' && !store.listed) {
    return res.render('liff/message', { title: '共通掲載していません', message: `${store.name} は共通掲載していません。お店の公式LINEから贈ってください。` });
  }
  if (route === 'original' && req.query.mock_liff && req.query.mock_liff !== store.liff_id) {
    log.ng('app', `LIFF ${req.query.mock_liff} から「${store.name}」の画面が開かれました`,
      `この店舗に登録されている LIFF ID は ${store.liff_id || '(未設定)'} です。\n別のお店の LIFF のエンドポイントURL が、この店の slug になっていませんか？`);
  } else {
    log.info('app', `LIFF画面「${store.name}」の贈る画面を表示しました（${route === 'common' ? '運営LINEの共通掲載から' : 'お店の公式LINEから'}）`, uid ? `開いた人: ${uid}` : '本物のLINE（LIFF SDK）で開かれました');
  }
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
  const user = mockUser(uid);
  res.render('liff/store', {
    title: `${store.name}で贈る`, store, menus, uid, user, error: '', form: {}, route,
    card: uid ? savedCard(uid) : null,
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
  const route = b.route === 'common' ? 'common' : 'original';
  const buyerId = uid || b.buyer_uid;
  const buyerName = uid ? (mockUser(uid) || {}).display_name : b.buyer_name;
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
  const menu = menus.find((m) => m.id === Number(b.menu_id));
  const saved = savedCard(buyerId);

  const fail = (error) => res.render('liff/store', {
    title: `${store.name}で贈る`, store, menus, uid, user: mockUser(uid), error, form: b, isFriend: null, route,
    card: saved, friendUrl: friendLink(store, uid), liffId: store.liff_id,
  });

  if (!buyerId) return fail('LINEのユーザー情報が取れませんでした。LINEの中で開き直してください。');
  if (!menu) return fail('商品を選んでください。');
  if (!String(b.recipient || '').trim()) return fail('贈る相手（宛先）を入れてください。');
  if (store.id_required && !req.file) return fail('このお店は身分証の画像が必要です。');

  // ---- 支払い：登録済みカードを使う or 新しくカードを登録する ----
  let last4;
  if (saved && b.use_saved === '1') {
    last4 = saved.last4;
    log.info('app', `登録済みのカード **** ${last4} で支払います（カード番号の入力なし）`);
  } else {
    const card = String(b.card_number || '').replace(/\s/g, '');
    const result = config.TEST_CARDS[card];
    if (!result) return fail('カード番号が正しくありません（体験ではテストカード 4242 4242 4242 4242 を使います）。');
    const [mm, yy] = String(b.exp || '').split('/').map(Number);
    const now = new Date();
    if (!mm || mm > 12 || !yy || (2000 + yy) * 12 + mm < now.getFullYear() * 12 + now.getMonth() + 1) return fail('有効期限は「月/年」の形（例 12/40）で、今日以降にしてください。');
    if (!/^\d{3}$/.test(String(b.cvc || ''))) return fail('CVC は3桁の数字です。');
    if (result === 'declined') {
      log.ng('app', 'カード登録 → 拒否されました（テストカード 4000 0000 0000 0002）');
      return fail('カードが拒否されました。別のカードをお試しください。');
    }
    last4 = card.slice(-4);
    db.prepare('INSERT OR REPLACE INTO cards (line_user_id, last4, exp) VALUES (?, ?, ?)').run(buyerId, last4, b.exp);
    log.ok('app', `カード **** ${last4} を登録しました（次回からは入力不要）`,
      '自社が保存したのは「下4桁と有効期限」だけ。本物のサービスでは、カード番号そのものは決済会社が保管します');
  }

  const fee = Math.round(menu.price * store.fee_rate / 100);
  const id = db.prepare(`INSERT INTO orders (store_id, menu_id, menu_name, amount, fee, buyer_line_user_id, buyer_name, route, recipient, message, id_image, card_last4)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
    .run(store.id, menu.id, menu.name, menu.price, fee, buyerId, buyerName || '(名前不明)', route, b.recipient.trim(), b.message || '',
      req.file ? req.file.originalname : '', last4).lastInsertRowid;
  log.ok('app', `注文 #${id} を受け付けました → 状態「リクエスト中」（${route === 'common' ? '共通掲載から' : 'お店の公式LINEから'}）`,
    `店舗: ${store.name}\n商品: ${menu.name} ${util.yen(menu.price)}（手数料 ${store.fee_rate}% = ${util.yen(fee)}）\nカード: **** ${last4} は「仮押さえ」だけ。まだお金は確定していません。\n→ お店のオーナーが「受け取る」を押したときに確定します`);

  // 買った人へのお知らせ：来た道の公式LINEから送る
  //   お店の公式LINEから来た → お店のトークン ／ 共通掲載から来た → 運営のトークン
  const thanksText = `${store.name}「${menu.name}」の贈り物を受け付けました（注文番号 #${id}）。\nお店で受け取られると完了です。`;
  if (route === 'original' && store.access_token) await line.push(line.storeConn(store), buyerId, thanksText);
  else await line.push(platform.conn(), buyerId, thanksText);

  // お店のオーナーへ：運営の公式LINEからお知らせ（運営のトークンを使う）
  if (store.manager_line_user_id) {
    await line.push(platform.conn(), store.manager_line_user_id, `【${store.name}】新しい贈り物が届きました（#${id} ${menu.name}）。\nメニューの「店舗管理」→ 贈り物一覧 から確認できます。`);
  }
  res.render('liff/done', { title: 'ありがとうございました', store, orderId: id, menu, uid, route });
});

// =====================================================
// 運営LINEのリッチメニューから開く画面
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

// ---- お客さん：共通掲載のお店を選ぶ ----
router.get('/platform/shops', needUid, (req, res) => {
  const stores = db.prepare(`SELECT s.*, (SELECT COUNT(*) FROM menus m WHERE m.store_id = s.id) AS menu_count,
                               (SELECT MIN(price) FROM menus m WHERE m.store_id = s.id) AS min_price
                             FROM stores s WHERE approved = 1 AND listed = 1 ORDER BY id`).all();
  log.info('app', `共通掲載のお店一覧を表示しました（${stores.length}件）`, '「承認済み」かつ「共通掲載」のお店だけが並びます');
  res.render('liff/shops', { title: 'ギフトを贈るお店を選ぶ', stores, yen: util.yen });
});

// ---- オーナー：出店登録（ユーザー登録・お店・口座・掲載方法） ----
router.get('/platform/register', needUid, (req, res) => {
  res.render('liff/register', { title: '出店登録', error: '', form: { publish: 'common' } });
});

router.post('/platform/register', needUid, (req, res) => {
  const b = req.body;
  const t = (k) => String(b[k] || '').trim();
  const fail = (error) => res.render('liff/register', { title: '出店登録', error, form: b });
  if (!t('owner_name') || !t('owner_phone')) return fail('オーナーのお名前と電話番号を入れてください。');
  if (!t('name')) return fail('店名を入れてください。');
  if (!t('bank_name') || !t('bank_branch') || !/^\d{7}$/.test(t('bank_number')) || !t('bank_holder')) {
    return fail('口座情報を入れてください（口座番号は7桁の数字。体験なので本物の口座は入れないでください）。');
  }
  const original = b.publish === 'original';
  const tmp = 'tmp-' + util.alnum(8);
  const id = db.prepare(`INSERT INTO stores (name, slug, fee_rate, listed, wants_original, manager_line_user_id,
                           owner_name, owner_phone, bank_name, bank_branch, bank_type, bank_number, bank_holder)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`)
    .run(t('name'), tmp, config.DEFAULT_FEE_RATE, original ? 0 : 1, original ? 1 : 0, req.uid,
      t('owner_name'), t('owner_phone'), t('bank_name'), t('bank_branch'), b.bank_type === '当座' ? '当座' : '普通', t('bank_number'), t('bank_holder')).lastInsertRowid;
  db.prepare('UPDATE stores SET slug = ? WHERE id = ?').run(`store-${id}`, id);
  log.ok('app', `出店登録を受け付けました：「${t('name')}」（未承認・${original ? 'オリジナル公式LINE希望' : '共通掲載'}）`,
    `仮の slug: store-${id}\nオーナー: ${t('owner_name')}（LINEユーザーID ${req.uid}）\n口座: ${t('bank_name')} ${t('bank_branch')} ****${t('bank_number').slice(-3)}\n→ 次はオーナーが「店舗管理」から商品を登録。運営は管理画面で承認し、slug を決めます`);
  res.render('liff/message', {
    title: '出店登録を受け付けました',
    message: `「${t('name')}」を受け付けました。続けて「店舗管理」から商品を登録してください。運営の承認後に公開されます。`,
    back: `/liff/platform/manage/${id}/products?mock_uid=${req.uid}`, backLabel: '商品を登録する',
  });
});

// ---- オーナー：店舗管理 ----
router.get('/platform/manage', needUid, (req, res) => {
  const stores = db.prepare(`SELECT s.*, (SELECT COUNT(*) FROM orders o WHERE o.store_id = s.id AND o.status = 'requested') AS waiting,
                               (SELECT COUNT(*) FROM menus m WHERE m.store_id = s.id) AS menu_count
                             FROM stores s WHERE manager_line_user_id = ? ORDER BY id`).all(req.uid);
  res.render('liff/manage', { title: '店舗管理', stores });
});

function ownStore(req, res, next) {
  const store = db.prepare('SELECT * FROM stores WHERE id = ?').get(req.params.storeId);
  if (!store || store.manager_line_user_id !== req.uid) {
    return res.status(403).render('liff/message', { title: '権限がありません', message: 'このお店のオーナーではありません。' });
  }
  req.store = store;
  next();
}

// 商品登録（オーナー自身が行う）
router.get('/platform/manage/:storeId/products', needUid, ownStore, (req, res) => {
  const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(req.store.id);
  res.render('liff/products', { title: '商品登録', store: req.store, menus, yen: util.yen, error: '' });
});
router.post('/platform/manage/:storeId/products', needUid, ownStore, (req, res) => {
  const name = String(req.body.name || '').trim();
  const price = Math.floor(Number(req.body.price));
  if (name && price > 0) {
    db.prepare('INSERT INTO menus (store_id, name, price) VALUES (?, ?, ?)').run(req.store.id, name, price);
    log.info('app', `オーナーが「${req.store.name}」に商品「${name}」${util.yen(price)} を登録しました`);
  }
  res.redirect(`/liff/platform/manage/${req.store.id}/products?mock_uid=${req.uid}`);
});
router.post('/platform/manage/:storeId/products/:menuId/delete', needUid, ownStore, (req, res) => {
  db.prepare('DELETE FROM menus WHERE id = ? AND store_id = ?').run(req.params.menuId, req.store.id);
  res.redirect(`/liff/platform/manage/${req.store.id}/products?mock_uid=${req.uid}`);
});

// 贈り物一覧・受け取る
router.get('/platform/manage/:storeId', needUid, ownStore, (req, res) => {
  const orders = db.prepare('SELECT * FROM orders WHERE store_id = ? ORDER BY id DESC').all(req.store.id);
  res.render('liff/gifts', { title: '贈り物一覧', store: req.store, orders, yen: util.yen });
});

router.post('/platform/manage/:storeId/orders/:orderId/receive', needUid, ownStore, (req, res) => {
  const o = db.prepare('SELECT * FROM orders WHERE id = ? AND store_id = ?').get(req.params.orderId, req.store.id);
  if (o && o.status === 'requested') {
    db.prepare("UPDATE orders SET status = 'received', payment = 'captured', received_at = datetime('now','localtime') WHERE id = ?").run(o.id);
    log.ok('app', `注文 #${o.id} を「受け取る」→ 決済が確定しました（受取済み）`, `売上 ${util.yen(o.amount)} / 手数料 ${util.yen(o.fee)}\n売上は、オーナーが登録した口座に振り込まれる対象になります`);
  }
  res.redirect(`/liff/platform/manage/${req.store.id}?mock_uid=${req.uid}`);
});

router.post('/platform/manage/:storeId/orders/:orderId/thanks', needUid, ownStore, async (req, res) => {
  const o = db.prepare('SELECT * FROM orders WHERE id = ? AND store_id = ?').get(req.params.orderId, req.store.id);
  if (o && o.status === 'received' && !o.thanks_sent) {
    const text = `${req.store.name}より：${o.recipient}への贈り物（${o.menu_name}）を受け取りました。ありがとうございました！` + (req.body.text ? `\n\n${req.body.text}` : '');
    // お礼も「来た道」の公式LINEから
    const conn = o.route === 'original' && req.store.access_token ? line.storeConn(req.store) : platform.conn();
    const r = await line.push(conn, o.buyer_line_user_id, text);
    if (r.ok) db.prepare('UPDATE orders SET thanks_sent = 1 WHERE id = ?').run(o.id);
  }
  res.redirect(`/liff/platform/manage/${req.store.id}?mock_uid=${req.uid}`);
});

module.exports = router;
