// =====================================================
// 疑似LINE の Messaging API（https://api.line.me の代わり）
//
// 自社サーバーは「返信して」「この人に送って」とここへお願いします。
// そのとき必ず「チャネルアクセストークン」を見せます（＝話しかける許可証）。
// =====================================================
const express = require('express');
const core = require('../core');
const log = require('../../log');
const util = require('../../util');

const router = express.Router();
router.use(express.json());
router.use(express.urlencoded({ extended: false }));

// ---- トークンの検証（このトークンはどのチャネルのもの？） ----
// 本物: POST https://api.line.me/v2/oauth/verify  （本文に access_token=...）
router.post('/v2/oauth/verify', (req, res) => {
  const channel = core.q.channelByToken((req.body || {}).access_token || '');
  if (!channel) return res.status(400).json({ error: 'invalid_request', error_description: 'access token expired' });
  log.info('line', `API トークン検証 → 200（このトークンはチャネル ${channel.channel_id} のもの）`);
  res.json({ client_id: channel.channel_id, expires_in: 2592000, scope: '' });
});

// ---- トークンの確認（許可証チェック） ----
router.use((req, res, next) => {
  const auth = req.headers.authorization || '';
  const token = auth.replace(/^Bearer\s+/i, '');
  const channel = token ? core.q.channelByToken(token) : null;
  if (!channel) {
    log.ng('line', `API ${req.method} ${req.path} → 401 トークンが無効です`,
      `受け取ったトークン: ${util.mask(token)}\n→ 発行し直したのに管理画面を更新していない／別のチャネルのトークン、などが原因です`);
    return res.status(401).json({ message: 'Authentication failed. Confirm that the access token in the authorization header is valid.' });
  }
  req.channel = channel;
  req.oa = channel.oa_id ? core.q.oa(channel.oa_id) : null;
  next();
});

function textOf(messages) {
  return (messages || []).map((m) => (m.type === 'text' ? m.text : `［${m.type} メッセージ（疑似LINEでは表示できません）］`));
}

// ---- 返信（reply） ----
router.post('/v2/bot/message/reply', (req, res) => {
  const { replyToken, messages } = req.body || {};
  const row = core.db.prepare('SELECT * FROM reply_tokens WHERE token = ?').get(replyToken || '');
  if (!row || row.channel_id !== req.channel.id || row.used || row.expires_at < Date.now()) {
    log.ng('line', 'API 返信 → 400 返信用トークン（replyToken）が無効です', 'replyToken は1回だけ・約1分以内しか使えません');
    return res.status(400).json({ message: 'Invalid reply token' });
  }
  core.db.prepare('UPDATE reply_tokens SET used = 1 WHERE token = ?').run(replyToken);
  for (const t of textOf(messages)) core.addMessage(req.oa.id, row.user_id, 'out', 'bot', t);
  log.ok('line', `API 返信 → 200 「${req.oa.name}」からユーザーに返信を届けました`, textOf(messages).join('\n---\n'));
  res.json({});
});

// ---- 送信（push） ----
router.post('/v2/bot/message/push', (req, res) => {
  const { to, messages } = req.body || {};
  if (!to) return res.status(400).json({ message: "The property, 'to', in the request body is invalid" });
  if (!core.q.isFriend(req.oa.id, to)) {
    // 本物のLINEも、友だちでない／ブロックされている人への送信はエラーにならず「届かない」だけ
    log.ng('line', `API 送信 → 200 でも届いていません（${util.mask(to, 6)} は「${req.oa.name}」の友だちではない／ブロック中）`);
    return res.json({ sentMessages: [] });
  }
  for (const t of textOf(messages)) core.addMessage(req.oa.id, to, 'out', 'bot', t);
  log.ok('line', `API 送信 → 200 「${req.oa.name}」からメッセージを届けました`, textOf(messages).join('\n---\n'));
  res.json({ sentMessages: [{ id: util.digits(18) }] });
});

// ---- プロフィール取得 ----
router.get('/v2/bot/profile/:userId', (req, res) => {
  const user = core.db.prepare('SELECT * FROM line_users WHERE user_id = ?').get(req.params.userId);
  if (!user || !core.q.isFriend(req.oa.id, user.user_id)) return res.status(404).json({ message: 'Not found' });
  res.json({ userId: user.user_id, displayName: user.display_name, language: 'ja' });
});

// ---- ボット情報（このトークンはどの公式アカウントのもの？） ----
router.get('/v2/bot/info', (req, res) => {
  if (!req.oa) return res.status(404).json({ message: 'Not found' });
  log.info('line', `API ボット情報 → 200（このトークンは「${req.oa.name}」${req.oa.basic_id} のもの）`);
  res.json({
    userId: req.oa.bot_user_id,
    basicId: req.oa.basic_id,
    displayName: req.oa.name,
    chatMode: req.oa.response_mode === 'bot' ? 'bot' : 'chat',
    markAsReadMode: 'auto',
  });
});

router.use((req, res) => res.status(404).json({ message: 'Not found（疑似LINEには用意していないAPIです）' }));

module.exports = router;
