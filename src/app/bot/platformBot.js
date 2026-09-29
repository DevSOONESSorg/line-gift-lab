// =====================================================
// サービス運営の公式LINE「おくりギフト(dev)」の bot
// お店の人が友だち追加して、出店登録や贈り物の受け取りに使います。
// =====================================================
const line = require('../lineClient');
const platform = require('../platform');

async function handle(event) {
  const conn = platform.conn();
  if (event.type === 'follow') {
    return line.reply(conn, event.replyToken,
      'おくりギフト(dev) です。\nお店を登録するときは、下のメニューの「出店登録」を押してください。\n登録したお店の贈り物は「店舗管理」から確認できます。');
  }
  if (event.type === 'message') {
    return line.reply(conn, event.replyToken, '操作は下のメニュー（出店登録／店舗管理）からお願いします。');
  }
}

module.exports = { handle };
