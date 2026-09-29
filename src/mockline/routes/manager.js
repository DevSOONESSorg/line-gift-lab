// =====================================================
// 疑似 LINE Official Account Manager（manager.line.biz の代わり）
// 公式アカウントの作成・権限管理・応答設定・リッチメニュー・Messaging API の有効化
// =====================================================
const path = require('path');
const fs = require('fs');
const express = require('express');
const multer = require('multer');
const core = require('../core');
const log = require('../../log');
const util = require('../../util');
const config = require('../../config');

const router = express.Router();
router.use(express.urlencoded({ extended: false }));

const UPLOAD_DIR = path.join(__dirname, '..', '..', '..', 'data', 'uploads', 'richmenu');
fs.mkdirSync(UPLOAD_DIR, { recursive: true });
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 1024 * 1024 } }); // 1MBまで（本物と同じ）

// ---- 公式アカウントごとの画面では「そのアカウントのメンバーか」を確認 ----
function loadOa(req, res, next) {
  const oa = core.q.oa(req.params.oaId);
  if (!oa) return res.status(404).render('error', { title: '見つかりません', message: 'その公式アカウントはありません。' });
  const role = core.q.oaRole(oa.id, res.locals.account.id);
  if (!role) {
    return res.status(403).render('error', {
      title: '権限がありません',
      message: `「${res.locals.account.name}」は、公式アカウント「${oa.name}」のメンバーではありません。権限管理で招待URLを発行してもらい、参加してください。`,
    });
  }
  req.oa = oa;
  res.locals.oa = oa;
  res.locals.role = role;
  next();
}

const ROLE_LABELS = { admin: '管理者', operator: '運用担当者' };

// ---- アカウント一覧 ----
router.get('/', (req, res) => {
  res.render('mock/manager/index', { title: 'アカウントリスト', oas: core.q.oasOf(res.locals.account.id), ROLE_LABELS });
});

// ---- 作成 ----
router.get('/create', (req, res) => res.render('mock/manager/create', { title: 'LINE公式アカウントの作成', error: '' }));
router.post('/create', (req, res) => {
  const name = String(req.body.name || '').trim();
  if (!name || !req.body.country || !req.body.agree) {
    return res.render('mock/manager/create', { title: 'LINE公式アカウントの作成', error: 'アカウント名・所在国を入れて、規約に同意してください。' });
  }
  const oa = core.createOfficialAccount({ name, industry: req.body.industry || '', owner: res.locals.account.id });
  res.redirect(`/mock/manager/oa/${oa.id}?msg=` + encodeURIComponent(`「${name}」を作成しました。あなたが管理者です。`));
});

// ---- ホーム ----
router.get('/oa/:oaId', loadOa, (req, res) => {
  const friends = core.db.prepare('SELECT COUNT(*) c FROM friends WHERE oa_id = ? AND blocked = 0').get(req.oa.id).c;
  const channel = core.q.messagingChannelOfOa(req.oa.id);
  res.render('mock/manager/home', { title: req.oa.name, friends, channel });
});

// ---- 設定：アカウント設定 ----
router.get('/oa/:oaId/settings', loadOa, (req, res) => {
  res.render('mock/manager/settings', { title: 'アカウント設定', tab: 'account' });
});

// ---- 設定：権限管理 ----
router.get('/oa/:oaId/settings/members', loadOa, (req, res) => {
  const members = core.db.prepare(`SELECT m.role, a.* FROM oa_members m JOIN accounts a ON a.id = m.account_id WHERE m.oa_id = ?`).all(req.oa.id);
  const invite = req.query.invite ? core.db.prepare('SELECT * FROM invites WHERE token = ?').get(req.query.invite) : null;
  res.render('mock/manager/members', { title: '権限管理', tab: 'members', members, invite, ROLE_LABELS,
    inviteUrl: invite ? `${req.protocol}://${req.get('host')}/mock/manager/invite/${invite.token}` : '' });
});
router.post('/oa/:oaId/settings/members/invite', loadOa, (req, res) => {
  if (res.locals.role !== 'admin') return res.status(403).render('error', { title: '権限がありません', message: 'メンバーを追加できるのは管理者だけです。' });
  const role = req.body.role === 'admin' ? 'admin' : 'operator';
  const token = util.hex(24);
  core.db.prepare('INSERT INTO invites VALUES (?, ?, ?, ?, NULL)').run(token, req.oa.id, role, Date.now() + 24 * 3600 * 1000);
  log.info('line', `「${req.oa.name}」の招待URLを発行しました（権限: ${ROLE_LABELS[role]}）`, '24時間有効・1回だけ使えます');
  res.redirect(`/mock/manager/oa/${req.oa.id}/settings/members?invite=${token}`);
});

// 招待URLを開く（参加する側）
router.get('/invite/:token', (req, res) => {
  const inv = core.db.prepare('SELECT * FROM invites WHERE token = ?').get(req.params.token);
  let error = '';
  if (!inv) error = 'この招待URLは存在しません。';
  else if (inv.used_by) error = 'この招待URLはすでに使われています（1回だけ有効）。もう一度発行してもらってください。';
  else if (inv.expires_at < Date.now()) error = 'この招待URLは有効期限（24時間）が切れています。';
  const oa = inv ? core.q.oa(inv.oa_id) : null;
  res.render('mock/manager/invite', { title: 'メンバー招待', inv, oa, error, ROLE_LABELS,
    already: oa ? core.q.oaRole(oa.id, res.locals.account.id) : null });
});
router.post('/invite/:token', (req, res) => {
  const inv = core.db.prepare('SELECT * FROM invites WHERE token = ?').get(req.params.token);
  if (!inv || inv.used_by || inv.expires_at < Date.now()) return res.redirect(`/mock/manager/invite/${req.params.token}`);
  const acc = res.locals.account.id;
  if (!core.q.oaRole(inv.oa_id, acc)) core.db.prepare('INSERT INTO oa_members VALUES (?, ?, ?)').run(inv.oa_id, acc, inv.role);
  core.db.prepare('UPDATE invites SET used_by = ? WHERE token = ?').run(acc, inv.token);
  const oa = core.q.oa(inv.oa_id);
  log.ok('line', `「${res.locals.account.name}」が「${oa.name}」に${ROLE_LABELS[inv.role]}として参加しました`);
  res.redirect(`/mock/manager/oa/${inv.oa_id}?msg=` + encodeURIComponent('参加しました'));
});

// ---- 設定：Messaging API ----
router.get('/oa/:oaId/settings/messaging-api', loadOa, (req, res) => {
  const channel = core.q.messagingChannelOfOa(req.oa.id);
  res.render('mock/manager/messaging', {
    title: 'Messaging API', tab: 'messaging', channel,
    provider: channel ? core.q.provider(channel.provider_id) : null,
    providers: core.q.providersOf(res.locals.account.id),
    hasRole: channel ? !!core.q.channelRole(channel.id, res.locals.account.id) : false,
  });
});
router.post('/oa/:oaId/settings/messaging-api', loadOa, (req, res) => {
  const providerId = req.body.provider_id === 'new' ? null : Number(req.body.provider_id);
  const newProviderName = String(req.body.new_provider || '').trim();
  if (!providerId && !newProviderName) return res.redirect(`/mock/manager/oa/${req.oa.id}/settings/messaging-api?msg=` + encodeURIComponent('プロバイダーを選んでください'));
  if (providerId && !core.q.providersOf(res.locals.account.id).some((p) => p.id === providerId)) {
    return res.status(403).render('error', { title: '権限がありません', message: 'そのプロバイダーのメンバーではありません。' });
  }
  core.enableMessagingApi(req.oa, { providerId, newProviderName, account: res.locals.account.id, privacyUrl: req.body.privacy_url || '' });
  res.redirect(`/mock/manager/oa/${req.oa.id}/settings/messaging-api?msg=` + encodeURIComponent('Messaging APIを有効にしました'));
});

// ---- 応答設定 ----
router.get('/oa/:oaId/response', loadOa, (req, res) => {
  res.render('mock/manager/response', { title: '応答設定', channel: core.q.messagingChannelOfOa(req.oa.id) });
});
router.post('/oa/:oaId/response', loadOa, (req, res) => {
  const b = req.body;
  core.db.prepare(`UPDATE official_accounts SET response_mode = ?, auto_reply_on = ?, auto_reply_text = ?, greeting_on = ?, greeting_text = ? WHERE id = ?`)
    .run(b.response_mode === 'chat' ? 'chat' : 'bot', b.auto_reply_on === '1' ? 1 : 0, b.auto_reply_text || '',
      b.greeting_on === '1' ? 1 : 0, b.greeting_text || '', req.oa.id);
  // Manager の「Webhook」と Developers の「Use webhook」は同じ1つのスイッチ
  const ch = core.q.messagingChannelOfOa(req.oa.id);
  if (ch && b.webhook !== undefined) core.db.prepare('UPDATE channels SET use_webhook = ? WHERE id = ?').run(b.webhook === '1' ? 1 : 0, ch.id);
  log.info('line', `「${req.oa.name}」の応答設定を変更しました`,
    `応答モード: ${b.response_mode === 'chat' ? 'チャット' : 'Bot'}\n応答メッセージ: ${b.auto_reply_on === '1' ? 'ON' : 'OFF'}\nWebhook: ${ch ? (b.webhook === '1' ? 'ON' : 'OFF') : '（Messaging API未設定）'}\nあいさつメッセージ: ${b.greeting_on === '1' ? 'ON' : 'OFF'}`);
  res.redirect(`/mock/manager/oa/${req.oa.id}/response?msg=` + encodeURIComponent('保存しました'));
});

// ---- リッチメニュー ----
router.get('/oa/:oaId/richmenus', loadOa, (req, res) => {
  const menus = core.db.prepare('SELECT * FROM rich_menus WHERE oa_id = ? ORDER BY id DESC').all(req.oa.id);
  res.render('mock/manager/richmenus', { title: 'リッチメニュー', menus, templates: config.RICHMENU_TEMPLATES });
});
router.get('/oa/:oaId/richmenus/new', loadOa, (req, res) => {
  res.render('mock/manager/richmenu-form', { title: 'リッチメニューを作成', templates: config.RICHMENU_TEMPLATES, sizes: config.RICHMENU_SIZES, error: '', form: {} });
});
router.post('/oa/:oaId/richmenus/new', loadOa, upload.single('image'), (req, res) => {
  const b = req.body;
  const tpl = config.RICHMENU_TEMPLATES[b.template];
  const fail = (error) => res.render('mock/manager/richmenu-form', { title: 'リッチメニューを作成', templates: config.RICHMENU_TEMPLATES, sizes: config.RICHMENU_SIZES, error, form: b });

  if (!b.title) return fail('タイトルを入れてください（管理用。お客さんには見えません）。');
  if (!tpl) return fail('テンプレートを選んでください。');
  if (!req.file) return fail('背景画像を選んでください（PNG または JPEG・1MBまで）。');
  const size = util.imageSize(req.file.buffer);
  if (!size) return fail('PNG か JPEG の画像を選んでください。');
  const okSizes = config.RICHMENU_SIZES[tpl.size];
  if (!okSizes.some(([w, h]) => w === size.width && h === size.height)) {
    return fail(`画像サイズが合いません。選んだ画像は ${size.width}×${size.height}px です。「${tpl.label}」には ${okSizes.map(([w, h]) => `${w}×${h}`).join(' / ')} のどれかが必要です。`);
  }
  const actions = tpl.areas.map((_, i) => ({ type: b[`action_type_${i}`] || 'none', value: String(b[`action_value_${i}`] || '').trim() }));
  const bad = actions.findIndex((a) => a.type !== 'none' && !a.value);
  if (bad >= 0) return fail(`ボタン ${String.fromCharCode(65 + bad)} のURL／テキストが空です。`);

  const file = `${req.oa.id}-${Date.now()}.${size.type === 'png' ? 'png' : 'jpg'}`;
  fs.writeFileSync(path.join(UPLOAD_DIR, file), req.file.buffer);
  if (b.is_default === '1') core.db.prepare('UPDATE rich_menus SET is_default = 0 WHERE oa_id = ?').run(req.oa.id);
  core.db.prepare(`INSERT INTO rich_menus (oa_id, title, template, image_path, width, height, bar_text, actions, is_default)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)`)
    .run(req.oa.id, b.title, b.template, file, size.width, size.height, b.bar_text || 'メニュー', JSON.stringify(actions), b.is_default === '1' ? 1 : 0);
  log.ok('line', `「${req.oa.name}」にリッチメニュー「${b.title}」を作成しました`,
    actions.map((a, i) => `ボタン${String.fromCharCode(65 + i)}: ${a.type === 'link' ? 'リンク → ' + a.value : a.type === 'text' ? 'テキスト「' + a.value + '」を送る' : '設定なし'}`).join('\n'));
  res.redirect(`/mock/manager/oa/${req.oa.id}/richmenus?msg=` + encodeURIComponent('保存しました'));
});
router.post('/oa/:oaId/richmenus/:rmId/default', loadOa, (req, res) => {
  core.db.prepare('UPDATE rich_menus SET is_default = 0 WHERE oa_id = ?').run(req.oa.id);
  core.db.prepare('UPDATE rich_menus SET is_default = 1 WHERE id = ? AND oa_id = ?').run(req.params.rmId, req.oa.id);
  res.redirect(`/mock/manager/oa/${req.oa.id}/richmenus?msg=` + encodeURIComponent('表示するメニューを切り替えました'));
});
router.post('/oa/:oaId/richmenus/:rmId/delete', loadOa, (req, res) => {
  core.db.prepare('DELETE FROM rich_menus WHERE id = ? AND oa_id = ?').run(req.params.rmId, req.oa.id);
  res.redirect(`/mock/manager/oa/${req.oa.id}/richmenus?msg=` + encodeURIComponent('削除しました'));
});

module.exports = router;
