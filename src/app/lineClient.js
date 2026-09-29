// =====================================================
// 自社サーバー → LINE社 へお願いする部分（Messaging API の呼び出し）
//
// どのお願いにも「チャネルアクセストークン」を付けます。
// 送り先は、チャネルIDかトークンが疑似LINEのものなら疑似LINE、そうでなければ本物の api.line.me です。
// =====================================================
const log = require('../log');
const util = require('../util');
const mock = require('../mockline/core');

function apiBase(conn) {
  const isMock = mock.q.channelByChannelId(conn.channelId) || (conn.token && mock.q.channelByToken(conn.token));
  return isMock ? 'http://127.0.0.1:3000/mock-line-api' : 'https://api.line.me';
}

async function call(conn, method, path, body) {
  const base = apiBase(conn);
  const where = base.includes('mock') ? '疑似LINE' : '本物のLINE';
  try {
    const res = await fetch(base + path, {
      method,
      headers: { Authorization: `Bearer ${conn.token}`, 'Content-Type': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
      signal: AbortSignal.timeout(8000),
    });
    const data = await res.json().catch(() => ({}));
    const detail = `送り先: ${where} ${method} ${path}\nトークン: ${util.mask(conn.token, 6)}` + (body ? `\n\n送った内容:\n${JSON.stringify(body, null, 2)}` : '') + `\n\n答え: ${res.status} ${JSON.stringify(data)}`;
    if (res.ok) log.ok('app', `LINEにお願い ${method} ${path} → ${res.status}`, detail);
    else log.ng('app', `LINEにお願い ${method} ${path} → ${res.status}`, detail);
    return { status: res.status, ok: res.ok, data };
  } catch (e) {
    log.ng('app', `LINEにお願い ${method} ${path} → つながりませんでした`, e.message);
    return { status: 0, ok: false, data: { message: e.message } };
  }
}

const text = (t) => ({ type: 'text', text: t });

module.exports = {
  apiBase,
  // 店舗の設定から接続情報を作る
  storeConn: (store) => ({ channelId: store.channel_id, token: store.access_token }),
  reply: (conn, replyToken, texts) => call(conn, 'POST', '/v2/bot/message/reply', { replyToken, messages: [].concat(texts).map(text) }),
  push: (conn, to, texts) => call(conn, 'POST', '/v2/bot/message/push', { to, messages: [].concat(texts).map(text) }),
  profile: (conn, userId) => call(conn, 'GET', `/v2/bot/profile/${userId}`),
  botInfo: (conn) => call(conn, 'GET', '/v2/bot/info'),
  // トークンがどのチャネルのものかを調べる（トークンは本文で渡す）
  async verifyToken(conn) {
    const base = apiBase(conn);
    try {
      const res = await fetch(base + '/v2/oauth/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ access_token: conn.token }).toString(),
        signal: AbortSignal.timeout(8000),
      });
      return { ok: res.ok, status: res.status, data: await res.json().catch(() => ({})) };
    } catch (e) {
      return { ok: false, status: 0, data: { message: e.message } };
    }
  },
};
