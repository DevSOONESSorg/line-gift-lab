// =====================================================
// 疑似 LINE Developers Console（developers.line.biz の代わり）
// プロバイダー・チャネル・LIFF・Webhook・トークン
// =====================================================
const express = require('express');
const core = require('../core');
const log = require('../../log');

const router = express.Router();
router.use(express.urlencoded({ extended: false }));

const TYPE_LABELS = { messaging: 'Messaging API', login: 'LINEログイン' };

router.get('/', (req, res) => {
  const providers = core.q.providersOf(res.locals.account.id);
  res.render('mock/developers/index', { title: 'プロバイダー', providers });
});

// ---- プロバイダー ----
function loadProvider(req, res, next) {
  const p = core.q.provider(req.params.pid);
  const member = p && core.q.providersOf(res.locals.account.id).some((x) => x.id === p.id);
  if (!member) return res.status(403).render('error', { title: '権限がありません', message: 'このプロバイダーのメンバーではありません。' });
  req.provider = p;
  res.locals.provider = p;
  next();
}

router.get('/provider/:pid', loadProvider, (req, res) => {
  const channels = core.db.prepare('SELECT * FROM channels WHERE provider_id = ? ORDER BY id').all(req.provider.id)
    .map((c) => ({ ...c, hasRole: !!core.q.channelRole(c.id, res.locals.account.id) }));
  res.render('mock/developers/provider', { title: req.provider.name, channels, TYPE_LABELS, creating: req.query.create || '' });
});

router.post('/provider/:pid/channels', loadProvider, (req, res) => {
  if (req.body.type !== 'login') {
    return res.redirect(`/mock/developers/provider/${req.provider.id}?msg=` + encodeURIComponent('Messaging APIチャネルは、ここでは作れません。LINE Official Account Manager で「Messaging APIを利用する」から作ります。'));
  }
  const name = String(req.body.name || '').trim();
  if (!name || req.body.app_type !== 'web') {
    return res.redirect(`/mock/developers/provider/${req.provider.id}?create=login&msg=` + encodeURIComponent('チャネル名を入れ、アプリタイプで「ウェブアプリ」を選んでください。'));
  }
  const ch = core.createLoginChannel({ providerId: req.provider.id, name, description: req.body.description, account: res.locals.account.id });
  res.redirect(`/mock/developers/channel/${ch.id}?msg=` + encodeURIComponent('チャネルを作成しました'));
});

// ---- チャネル ----
function loadChannel(req, res, next) {
  const ch = core.q.channel(req.params.cid);
  if (!ch) return res.status(404).render('error', { title: '見つかりません', message: 'そのチャネルはありません。' });
  const inProvider = core.q.providersOf(res.locals.account.id).some((x) => x.id === ch.provider_id);
  const role = core.q.channelRole(ch.id, res.locals.account.id);
  if (!inProvider || !role) {
    return res.status(403).render('error', {
      title: '権限なし',
      message: `「${res.locals.account.name}」には、このチャネル（${ch.name}）を開く権限がありません。自分が作っていないチャネルは「権限なし」になります（仕様です）。チャネルの権限設定（Roles）で追加してもらう必要があります。`,
    });
  }
  req.channel = ch;
  res.locals.channel = ch;
  res.locals.provider = core.q.provider(ch.provider_id);
  next();
}

router.get('/channel/:cid', loadChannel, (req, res) => {
  const ch = req.channel;
  const tab = req.query.tab || 'basic';
  const liffs = core.db.prepare('SELECT * FROM liff_apps WHERE channel_id = ?').all(ch.id);
  const roles = core.db.prepare('SELECT r.role, a.* FROM channel_roles r JOIN accounts a ON a.id = r.account_id WHERE r.channel_id = ?').all(ch.id);
  res.render('mock/developers/channel', {
    title: ch.name, tab, liffs, roles, TYPE_LABELS,
    oa: ch.oa_id ? core.q.oa(ch.oa_id) : null,
    accounts: core.q.accounts(),
    verify: req.query.verify ? JSON.parse(req.query.verify) : null,
    showSecret: req.query.show === 'secret',
  });
});

const back = (ch, tab, msg) => `/mock/developers/channel/${ch.id}?tab=${tab}` + (msg ? '&msg=' + encodeURIComponent(msg) : '');

router.post('/channel/:cid/secret', loadChannel, (req, res) => {
  core.reissueSecret(req.channel);
  res.redirect(back(req.channel, 'basic', 'チャネルシークレットを再発行しました。古いシークレットはもう使えません。') + '&show=secret');
});

router.post('/channel/:cid/token', loadChannel, (req, res) => {
  core.issueToken(req.channel);
  res.redirect(back(req.channel, 'messaging', 'チャネルアクセストークン（長期）を発行しました'));
});

router.post('/channel/:cid/webhook', loadChannel, (req, res) => {
  const url = String(req.body.webhook_url || '').trim();
  core.db.prepare('UPDATE channels SET webhook_url = ? WHERE id = ?').run(url, req.channel.id);
  log.info('line', `Webhook URLを登録しました（${req.channel.channel_id}）`, url || '(空)');
  res.redirect(back(req.channel, 'messaging', 'Webhook URLを更新しました'));
});

router.post('/channel/:cid/webhook/verify', loadChannel, async (req, res) => {
  const result = await core.sendWebhook(core.q.channel(req.channel.id), [], { verify: true });
  res.redirect(back(req.channel, 'messaging') + '&verify=' + encodeURIComponent(JSON.stringify(result)));
});

router.post('/channel/:cid/webhook/use', loadChannel, (req, res) => {
  const on = req.body.use_webhook === '1' ? 1 : 0;
  core.db.prepare('UPDATE channels SET use_webhook = ? WHERE id = ?').run(on, req.channel.id);
  log.info('line', `Use webhook を ${on ? 'ON' : 'OFF'} にしました（${req.channel.channel_id}）`);
  res.redirect(back(req.channel, 'messaging'));
});

router.post('/channel/:cid/liff', loadChannel, (req, res) => {
  if (req.channel.type !== 'login') return res.redirect(back(req.channel, 'basic', 'LIFFはLINEログインチャネルに追加します'));
  const endpoint = String(req.body.endpoint_url || '').trim();
  const scopes = [].concat(req.body.scopes || []).join(' ');
  if (!/^https?:\/\//.test(endpoint)) return res.redirect(back(req.channel, 'liff', 'エンドポイントURLは http:// か https:// で始まるURLを入れてください'));
  const liff = core.addLiff(req.channel, { name: req.body.name || req.channel.name, size: req.body.size, endpointUrl: endpoint, scopes: scopes || 'profile' });
  res.redirect(back(req.channel, 'liff', `LIFFアプリを追加しました。LIFF ID: ${liff.liff_id}`));
});

router.post('/channel/:cid/liff/:liffId/endpoint', loadChannel, (req, res) => {
  core.db.prepare('UPDATE liff_apps SET endpoint_url = ? WHERE liff_id = ? AND channel_id = ?').run(String(req.body.endpoint_url || '').trim(), req.params.liffId, req.channel.id);
  log.info('line', `LIFF ${req.params.liffId} のエンドポイントURLを変更しました`, req.body.endpoint_url);
  res.redirect(back(req.channel, 'liff', 'エンドポイントURLを更新しました'));
});

router.post('/channel/:cid/liff/:liffId/delete', loadChannel, (req, res) => {
  core.db.prepare('DELETE FROM liff_apps WHERE liff_id = ? AND channel_id = ?').run(req.params.liffId, req.channel.id);
  res.redirect(back(req.channel, 'liff', '削除しました'));
});

router.post('/channel/:cid/roles', loadChannel, (req, res) => {
  const acc = core.q.account(req.body.account_id);
  if (acc && !core.q.channelRole(req.channel.id, acc.id)) {
    core.db.prepare('INSERT INTO channel_roles VALUES (?, ?, ?)').run(req.channel.id, acc.id, 'admin');
    // プロバイダーのメンバーでなければ、チャネルは見えないので一緒に追加
    core.db.prepare('INSERT OR IGNORE INTO provider_members VALUES (?, ?, ?)').run(req.channel.provider_id, acc.id, 'member');
    log.ok('line', `チャネル ${req.channel.channel_id} の権限に「${acc.name}」を追加しました`);
  }
  res.redirect(back(req.channel, 'roles', '権限を追加しました'));
});

module.exports = router;
