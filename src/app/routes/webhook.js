// =====================================================
// Webhook の受け口（LINE社 → 自社サーバー）
//
//   POST /api/webhook/line/store/{slug}   … 各店舗の公式LINE から
//   POST /api/webhook/line/platform       … 運営の公式LINE から
//
// やること:
//   1. URL の slug から「どの店舗宛てか」を決める
//   2. 署名（X-Line-Signature）を、その店舗のチャネルシークレットで確かめる
//   3. すぐに 200 を返す（＝受け取りました）
//   4. イベントを bot に渡して処理する
// =====================================================
const express = require('express');
const db = require('../db');
const log = require('../../log');
const util = require('../../util');
const platform = require('../platform');
const storeBot = require('../bot/storeBot');
const platformBot = require('../bot/platformBot');

const router = express.Router();

// 署名の確認には「届いたままの本文（バイト列）」が必要なので raw で受け取る
router.use(express.raw({ type: '*/*', limit: '1mb' }));

function describe(events) {
  if (!events.length) return '中身なし（Verify＝接続確認）';
  return events.map((e) => (e.type === 'message' ? `message「${e.message.text || e.message.type}」` : e.type)).join(', ');
}

router.post('/line/store/:slug', (req, res) => {
  const raw = req.body instanceof Buffer ? req.body.toString('utf8') : '';
  const signature = req.get('X-Line-Signature');
  const store = db.prepare('SELECT * FROM stores WHERE slug = ?').get(req.params.slug);

  if (!store) {
    log.ng('app', `Webhook受信 → 404「${req.params.slug}」という slug の店舗はありません`,
      '管理画面の slug と、Developers に登録した Webhook URL の slug が同じか確認しましょう');
    return res.status(404).json({ message: 'store not found' });
  }
  if (!store.channel_secret) {
    log.ng('app', `Webhook受信 → 500「${store.name}」のチャネルシークレットが未設定です`, '管理画面の LINE連携設定 に入れましょう');
    return res.status(500).json({ message: 'channel secret not set' });
  }
  if (!util.signatureMatches(store.channel_secret, raw, signature)) {
    log.ng('app', `Webhook受信 → 401 署名が合いません（「${store.name}」宛て）`,
      `届いた署名:   ${signature}\n自分で計算:   ${util.sign(store.channel_secret, raw)}\n（管理画面のシークレット ${util.mask(store.channel_secret)} で計算）\n\n→ LINE社が使ったシークレットと、管理画面に入れたシークレットが違います。\n  打ち間違い・別のチャネルの値・再発行後に更新していない、などが原因です`);
    return res.status(401).json({ message: 'invalid signature' });
  }

  let body;
  try { body = JSON.parse(raw); } catch { return res.status(400).json({ message: 'bad json' }); }
  const events = body.events || [];
  log.ok('app', `Webhook受信 → 200 署名OK（「${store.name}」宛て）: ${describe(events)}`,
    `slug「${store.slug}」→ 店舗「${store.name}」と判断\nシークレットで計算した署名が一致 → 本物のLINE社からの通知と確認`);

  // 先に 200 を返し、処理はそのあとで行う（LINE社を待たせない）
  res.status(200).json({});
  for (const ev of events) {
    storeBot.handle(store, ev).catch((e) => log.ng('app', 'bot の処理中にエラー', e.stack));
  }
});

router.post('/line/platform', (req, res) => {
  const raw = req.body instanceof Buffer ? req.body.toString('utf8') : '';
  if (!util.signatureMatches(platform.secret(), raw, req.get('X-Line-Signature'))) {
    log.ng('app', 'Webhook受信（運営LINE）→ 401 署名が合いません');
    return res.status(401).json({ message: 'invalid signature' });
  }
  const events = JSON.parse(raw).events || [];
  log.ok('app', `Webhook受信（運営LINE）→ 200 署名OK: ${describe(events)}`);
  res.status(200).json({});
  for (const ev of events) platformBot.handle(ev).catch((e) => log.ng('app', 'bot の処理中にエラー', e.stack));
});

module.exports = router;
