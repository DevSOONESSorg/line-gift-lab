// =====================================================
// 疑似スマホ（お客さんのLINEアプリの役）
// =====================================================
const express = require('express');
const core = require('../core');
const log = require('../../log');

const router = express.Router();
router.use(express.urlencoded({ extended: false }));

// URL を「スマホの中のブラウザ」で開くときの行き先を決める
//   https://liff.line.me/{LIFF_ID}  → LIFF のエンドポイントURL に置き換える（本物のLINEと同じ動き）
//   https://line.me/R/ti/p/@xxx    → 友だち追加
function resolveOpen(target, me) {
  let u;
  try { u = new URL(target, 'http://localhost'); } catch { return { error: `URLとして読めません: ${target}` }; }

  if (u.hostname === 'line.me' && u.pathname.startsWith('/R/ti/p/')) {
    return { add: decodeURIComponent(u.pathname.replace('/R/ti/p/', '')) };
  }

  let liffId = null;
  if (u.hostname === 'liff.line.me') {
    const parts = u.pathname.split('/').filter(Boolean);
    liffId = parts.shift();
    const liff = core.q.liff(liffId);
    if (!liff) {
      log.ng('line', `LIFF ID「${liffId}」は見つかりません`, 'リッチメニューやQRに入れた LIFF ID が間違っていないか確認しましょう');
      return { error: `LIFF ID「${liffId}」のLIFFアプリはありません。LIFF IDの打ち間違いかもしれません。` };
    }
    let ep;
    try { ep = new URL(liff.endpoint_url); } catch { return { error: `LIFFのエンドポイントURLが正しくありません: ${liff.endpoint_url}` }; }
    const rest = parts.length ? '/' + parts.join('/') : '';
    ep.pathname = ep.pathname.replace(/\/$/, '') + rest;
    u.searchParams.forEach((v, k) => ep.searchParams.set(k, v));
    log.info('line', `LIFF ${liffId} を開きます → エンドポイントURL ${liff.endpoint_url}`, 'LINEは「LIFF ID → エンドポイントURL」の対応表を見て、そのページをLINEの中で開きます');
    u = ep;
  }

  const local = ['localhost', '127.0.0.1'].includes(u.hostname);
  if (!local && !liffId) return { external: u.toString() };

  if (liffId) {
    u.searchParams.set('mock_uid', me.user_id);
    u.searchParams.set('mock_liff', liffId);
  }
  return { iframe: local ? u.pathname + u.search : u.toString(), liffId };
}

router.get('/', (req, res) => {
  const me = core.q.me();
  const friends = core.db.prepare(`SELECT o.*, f.blocked FROM official_accounts o JOIN friends f ON f.oa_id = o.id
                                   WHERE f.user_id = ? ORDER BY o.id`).all(me.user_id);
  const chat = req.query.chat ? core.q.oa(req.query.chat) : null;
  let messages = [];
  let richMenu = null;
  let friendRow = null;
  if (chat) {
    friendRow = core.db.prepare('SELECT * FROM friends WHERE oa_id = ? AND user_id = ?').get(chat.id, me.user_id);
    messages = core.db.prepare('SELECT * FROM messages WHERE oa_id = ? AND user_id = ? ORDER BY id').all(chat.id, me.user_id);
    const rm = core.q.defaultRichMenu(chat.id);
    if (rm) richMenu = { ...rm, actions: JSON.parse(rm.actions) };
  }

  let open = null;
  let addTarget = req.query.add || null;
  if (req.query.open) {
    open = resolveOpen(req.query.open, me);
    if (open.add) { addTarget = open.add; open = null; }
  }
  let addOa = null;
  if (addTarget) addOa = core.q.oaByBasicId(addTarget) || { notFound: addTarget };

  res.render('mock/phone', {
    title: '疑似スマホ', me, friends, chat, messages, richMenu, friendRow, open, addOa,
    templates: require('../../config').RICHMENU_TEMPLATES,
    lastId: messages.length ? messages[messages.length - 1].id : 0,
  });
});

router.post('/name', (req, res) => {
  const name = String(req.body.display_name || '').trim().slice(0, 20) || 'あなた';
  core.db.prepare('UPDATE line_users SET display_name = ?').run(name);
  res.redirect('/mock/phone');
});

router.post('/add', async (req, res) => {
  const me = core.q.me();
  const oa = core.q.oaByBasicId(req.body.basic_id);
  if (!oa) return res.redirect('/mock/phone?add=' + encodeURIComponent(req.body.basic_id || ''));
  await core.follow(oa, me);
  res.redirect('/mock/phone?chat=' + oa.id);
});

router.post('/chat/:oaId/send', async (req, res) => {
  const me = core.q.me();
  const oa = core.q.oa(req.params.oaId);
  const text = String(req.body.text || '').trim();
  if (oa && text && core.q.isFriend(oa.id, me.user_id)) await core.userSendsText(oa, me, text);
  res.redirect('/mock/phone?chat=' + req.params.oaId);
});

router.post('/chat/:oaId/block', (req, res) => {
  const me = core.q.me();
  const oa = core.q.oa(req.params.oaId);
  if (oa) core.block(oa, me, req.body.blocked === '1');
  res.redirect('/mock/phone?chat=' + req.params.oaId);
});

// 新しいメッセージが来たかどうか（画面が2秒ごとに聞きに来る）
router.get('/chat/:oaId/last.json', (req, res) => {
  const me = core.q.me();
  const row = core.db.prepare('SELECT MAX(id) AS id FROM messages WHERE oa_id = ? AND user_id = ?').get(req.params.oaId, me.user_id);
  res.json({ lastId: row.id || 0 });
});

module.exports = router;
