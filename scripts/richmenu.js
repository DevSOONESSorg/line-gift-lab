// =====================================================
// コースB：リッチメニューを Messaging API で作る（本物のLINE用）
//
//   docker compose exec app npm run richmenu -- list
//   docker compose exec app npm run richmenu -- create richmenus/six.json richmenus/images/six.png
//   docker compose exec app npm run richmenu -- default <richMenuId>
//   docker compose exec app npm run richmenu -- setup-tabs          … タブ切り替えメニュー（A/B）を一気に作る
//   docker compose exec app npm run richmenu -- link <userId> <richMenuId>   … その人だけ別のメニューにする
//   docker compose exec app npm run richmenu -- delete <richMenuId>
//   docker compose exec app npm run richmenu -- delete-all
//
// トークンは次の順で探します:
//   1) --store <slug> で指定した店舗（管理画面に入れた長期トークン）
//   2) .env の LINE_CHANNEL_ACCESS_TOKEN
// JSON の中の {LIFF_ID} は、その店舗の LIFF ID（または .env の LINE_LIFF_ID）に置き換わります。
// =====================================================
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const args = process.argv.slice(2);
function opt(name) {
  const i = args.indexOf(name);
  if (i < 0) return null;
  const v = args[i + 1];
  args.splice(i, 2);
  return v;
}
const storeSlug = opt('--store');

let token = process.env.LINE_CHANNEL_ACCESS_TOKEN || '';
let liffId = process.env.LINE_LIFF_ID || '';
if (storeSlug) {
  const Database = require('better-sqlite3');
  const s = new Database(path.join(ROOT, 'data', 'app.db')).prepare('SELECT * FROM stores WHERE slug = ?').get(storeSlug);
  if (!s) { console.error(`店舗「${storeSlug}」がありません`); process.exit(1); }
  token = s.access_token;
  liffId = s.liff_id || liffId;
}
if (!token) {
  console.error('トークンがありません。--store <slug> を付けるか、.env の LINE_CHANNEL_ACCESS_TOKEN に入れて docker compose up し直してください。');
  process.exit(1);
}

const API = 'https://api.line.me';
const DATA_API = 'https://api-data.line.me';

async function call(method, url, body, contentType = 'application/json') {
  const res = await fetch(url, {
    method,
    headers: { Authorization: `Bearer ${token}`, ...(body ? { 'Content-Type': contentType } : {}) },
    body: body ? (contentType === 'application/json' ? JSON.stringify(body) : body) : undefined,
  });
  const text = await res.text();
  let data; try { data = JSON.parse(text); } catch { data = text; }
  if (!res.ok) {
    console.error(`× ${method} ${url.replace(/^https:\/\/[^/]+/, '')} → ${res.status}`, data);
    if (res.status === 401) console.error('  → トークンが無効です。Developers で再発行し、管理画面（または .env）を更新しましょう。');
    process.exit(1);
  }
  return data;
}

function loadJson(file) {
  const raw = fs.readFileSync(path.resolve(ROOT, file), 'utf8').replace(/\{LIFF_ID\}/g, liffId || 'LIFF_IDが未設定');
  return JSON.parse(raw);
}

async function create(jsonFile, imageFile) {
  const body = loadJson(jsonFile);
  // 1. まず「どこを押すと何が起きるか」だけを登録（画像はまだ）
  await call('POST', `${API}/v2/bot/richmenu/validate`, body);
  const { richMenuId } = await call('POST', `${API}/v2/bot/richmenu`, body);
  // 2. そのメニューに画像をアップロード
  const img = fs.readFileSync(path.resolve(ROOT, imageFile));
  await call('POST', `${DATA_API}/v2/bot/richmenu/${richMenuId}/content`, img, imageFile.endsWith('.png') ? 'image/png' : 'image/jpeg');
  console.log(`○ 作成しました: ${richMenuId}（${body.name}）`);
  return richMenuId;
}

async function alias(aliasId, richMenuId) {
  const list = await call('GET', `${API}/v2/bot/richmenu/alias/list`);
  if ((list.aliases || []).some((a) => a.richMenuAliasId === aliasId)) {
    await call('POST', `${API}/v2/bot/richmenu/alias/${aliasId}`, { richMenuId });
  } else {
    await call('POST', `${API}/v2/bot/richmenu/alias`, { richMenuAliasId: aliasId, richMenuId });
  }
  console.log(`○ エイリアス ${aliasId} → ${richMenuId}`);
}

(async () => {
  const [cmd, a, b] = args;
  switch (cmd) {
    case 'list': {
      const { richmenus } = await call('GET', `${API}/v2/bot/richmenu/list`);
      let def = null;
      try { def = (await fetch(`${API}/v2/bot/user/all/richmenu`, { headers: { Authorization: `Bearer ${token}` } }).then((r) => r.json())).richMenuId; } catch {}
      if (!richmenus.length) console.log('（APIで作ったリッチメニューはありません。Managerで作ったものはここに出ません）');
      for (const m of richmenus) console.log(`${m.richMenuId === def ? '★' : '　'} ${m.richMenuId}  ${m.name}  ${m.size.width}x${m.size.height}  ボタン${m.areas.length}個`);
      const al = await call('GET', `${API}/v2/bot/richmenu/alias/list`);
      for (const x of al.aliases || []) console.log(`   エイリアス ${x.richMenuAliasId} → ${x.richMenuId}`);
      break;
    }
    case 'create':
      if (!a || !b) throw new Error('使い方: create <json> <画像>');
      await create(a, b);
      break;
    case 'default':
      await call('POST', `${API}/v2/bot/user/all/richmenu/${a}`);
      console.log(`○ 全員のデフォルトにしました: ${a}（スマホでトークを開き直すと反映）`);
      break;
    case 'link':
      await call('POST', `${API}/v2/bot/user/${a}/richmenu/${b}`);
      console.log(`○ ${a} さんだけ ${b} にしました`);
      break;
    case 'alias':
      await alias(a, b);
      break;
    case 'setup-tabs': {
      const idA = await create('richmenus/tab-a.json', 'richmenus/images/tab-a.png');
      const idB = await create('richmenus/tab-b.json', 'richmenus/images/tab-b.png');
      await alias('tab-a', idA);
      await alias('tab-b', idB);
      await call('POST', `${API}/v2/bot/user/all/richmenu/${idA}`);
      console.log('○ タブAをデフォルトにしました。スマホでタブを押して切り替わるか試しましょう。');
      break;
    }
    case 'delete':
      await call('DELETE', `${API}/v2/bot/richmenu/${a}`);
      console.log(`○ 削除しました: ${a}`);
      break;
    case 'delete-all': {
      const al = await call('GET', `${API}/v2/bot/richmenu/alias/list`);
      for (const x of al.aliases || []) await call('DELETE', `${API}/v2/bot/richmenu/alias/${x.richMenuAliasId}`);
      const { richmenus } = await call('GET', `${API}/v2/bot/richmenu/list`);
      for (const m of richmenus) await call('DELETE', `${API}/v2/bot/richmenu/${m.richMenuId}`);
      console.log(`○ APIで作ったリッチメニュー ${richmenus.length} 個とエイリアスを削除しました`);
      break;
    }
    default:
      console.log(fs.readFileSync(__filename, 'utf8').split('\n').slice(1, 20).join('\n'));
  }
})().catch((e) => { console.error(e.message); process.exit(1); });
