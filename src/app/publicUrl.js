// =====================================================
// このアプリの「外から見たURL」を調べる
//   ・疑似LINE（コースA）… http://localhost:3000 のままでOK
//   ・本物のLINE（コースB）… インターネットから届く https のURL が必要
//     → cloudflared（トンネル）が発行したURLを自動で取りに行く
// =====================================================
const config = require('../config');

let cache = { url: '', at: 0 };

async function tunnelUrl() {
  if (config.PUBLIC_URL) return config.PUBLIC_URL;
  if (!config.TUNNEL_METRICS) return '';
  if (Date.now() - cache.at < 15000) return cache.url;
  try {
    const res = await fetch(config.TUNNEL_METRICS + '/quicktunnel', { signal: AbortSignal.timeout(1500) });
    const data = await res.json();
    cache = { url: data.hostname ? `https://${data.hostname}` : '', at: Date.now() };
  } catch {
    cache = { url: '', at: Date.now() };
  }
  return cache.url;
}

module.exports = {
  localBase: () => `http://localhost:${config.HOST_PORT}`,
  tunnelUrl,
};
