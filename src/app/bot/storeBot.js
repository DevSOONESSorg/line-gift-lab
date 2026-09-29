// =====================================================
// 店舗の公式LINEの bot（自動で返事をするプログラム）
//
// LINE社から Webhook で「イベント」が届くたびに handle() が呼ばれます。
// コースB では、ここを書きかえて返事の内容を変えていきます。
// =====================================================
const line = require('../lineClient');
const db = require('../db');
const util = require('../../util');

async function handle(store, event) {
  const conn = line.storeConn(store);

  // ---- 友だち追加されたとき ----
  if (event.type === 'follow') {
    return line.reply(conn, event.replyToken,
      `${store.name} の公式LINEです。\n下のメニューの「贈る」から、大切な人にギフトを贈れます。`);
  }

  // ---- 文字のメッセージが来たとき ----
  if (event.type === 'message' && event.message.type === 'text') {
    const text = event.message.text.trim();

    if (text === 'メニュー') {
      const menus = db.prepare('SELECT * FROM menus WHERE store_id = ? ORDER BY id').all(store.id);
      const lines = menus.map((m) => `・${m.name} ${util.yen(m.price)}`);
      return line.reply(conn, event.replyToken, lines.length ? `【メニュー】\n${lines.join('\n')}` : 'まだメニューがありません。');
    }

    if (text === 'こんにちは') {
      return line.reply(conn, event.replyToken, 'こんにちは！ご来店お待ちしています。');
    }

    // それ以外は、受け取った文字をそのまま返す（オウム返し）
    return line.reply(conn, event.replyToken, `「${text}」を受け取りました。`);
  }

  // ---- ボタン（postback）が押されたとき（コースB4で使う） ----
  if (event.type === 'postback') {
    const data = new URLSearchParams(event.postback.data);
    if (data.get('info') === 'hours') {
      return line.reply(conn, event.replyToken, '営業時間は 11:00〜22:00 です（体験用のダミーです）。');
    }
    return line.reply(conn, event.replyToken, `ボタンが押されました（data: ${event.postback.data}）`);
  }

  // それ以外のイベント（ブロックなど）は何もしない
}

module.exports = { handle };
