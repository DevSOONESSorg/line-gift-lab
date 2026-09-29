// =====================================================
// サービス運営の公式LINE（おくりギフト(dev)）の接続情報
// 最初の起動時に疑似LINEの中に作られ、値が settings に保存されます。
// =====================================================
const db = require('./db');

module.exports = {
  conn: () => ({ channelId: db.setting('platform_channel_id'), token: db.setting('platform_token') }),
  secret: () => db.setting('platform_secret'),
  basicId: () => db.setting('platform_basic_id'),
};
