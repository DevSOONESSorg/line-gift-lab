// =====================================================
// サービス運営の公式LINE「おくりギフト(dev)」の bot
// お客さんは共通掲載のお店からギフトを贈るのに、お店のオーナーは出店登録・商品登録・受け取りに使います。
// =====================================================
const line = require('../lineClient');
const platform = require('../platform');

async function handle(event) {
  const conn = platform.conn();
  if (event.type === 'follow') {
    return line.reply(conn, event.replyToken,
      'おくりギフト(dev) です。\n【ギフトを贈る方】下のメニューの「ギフトを贈る」からお店を選べます。\n【お店のオーナーの方】「出店登録」で登録し、「店舗管理」で商品登録や贈り物の受け取りができます。');
  }
  if (event.type === 'message') {
    return line.reply(conn, event.replyToken, '操作は下のメニュー（ギフトを贈る／出店登録／店舗管理）からお願いします。');
  }
}

module.exports = { handle };
