// =====================================================
// 障害ドリル：設定をどこか1か所こっそり壊す（職員用）
//
//   docker compose exec app npm run drill -- list              … 一覧（答えは出ません）
//   docker compose exec app npm run drill -- 3                 … 3番を仕込む
//   docker compose exec app npm run drill -- random            … ランダムに1つ仕込む
//   docker compose exec app npm run drill -- 3 --store club-azure   … 店舗を指定
//
// 店舗を指定しないときは、見本カフェ以外でいちばん新しい店舗が対象です。
// 何を壊したかは表示しません。答えは docs/facilitator.md にあります。
// 裏側ビューにも記録を残しません（ヒントになってしまうため）。
// =====================================================
const path = require('path');
const Database = require('better-sqlite3');
const crypto = require('crypto');

const DATA = path.join(__dirname, '..', 'data');
const app = new Database(path.join(DATA, 'app.db'));
const line = new Database(path.join(DATA, 'mock-line.db'));

const args = process.argv.slice(2);
const storeArg = args.includes('--store') ? args[args.indexOf('--store') + 1] : null;
const which = args.find((a) => a !== '--store' && a !== storeArg) || 'list';

const store = storeArg
  ? app.prepare('SELECT * FROM stores WHERE slug = ?').get(storeArg)
  : app.prepare("SELECT * FROM stores WHERE slug <> 'sample-cafe' ORDER BY id DESC LIMIT 1").get();

const DRILLS = {
  1: { card: 'トークで話しかけても、botが返事をしない（A）' },
  2: { card: 'トークで話しかけても、botが返事をしない（B）' },
  3: { card: '返事が2通来る' },
  4: { card: 'トークで話しかけても、botが返事をしない（C）' },
  5: { card: '「贈る」を押すと、ちがうお店が開く' },
  6: { card: '「贈る」を押しても、何も買えない（A）' },
  7: { card: '「贈る」を押しても、何も買えない（B）' },
  8: { card: 'リッチメニューの「贈る」を押すとエラーになる' },
  9: { card: '購入のお知らせがLINEに届かなくなった' },
};

if (which === 'list') {
  console.log('障害ドリル一覧（参加者に渡す「症状カード」）');
  for (const [n, d] of Object.entries(DRILLS)) console.log(`  ${n}. ${d.card}`);
  console.log('\n対象店舗:', store ? `${store.name}（${store.slug}）` : '見つかりません');
  process.exit(0);
}

if (!store) { console.error('対象の店舗が見つかりません。--store <slug> で指定してください。'); process.exit(1); }
const mch = line.prepare("SELECT * FROM channels WHERE channel_id = ? AND type = 'messaging'").get(store.channel_id);
const oa = mch ? line.prepare('SELECT * FROM official_accounts WHERE id = ?').get(mch.oa_id) : null;
if (!mch || !oa) { console.error(`「${store.name}」は疑似LINEとつながっていません（チャネルIDを確認）。`); process.exit(1); }

const n = which === 'random' ? String(1 + crypto.randomInt(Object.keys(DRILLS).length)) : which;

const actions = {
  // 1: 自社側のシークレットを1文字変える → 署名が合わない（401）
  1: () => app.prepare('UPDATE stores SET channel_secret = ? WHERE id = ?')
    .run(store.channel_secret.slice(0, -1) + (store.channel_secret.endsWith('a') ? 'b' : 'a'), store.id),
  // 2: LINE側でトークンを再発行（自社側は古いまま） → 返信APIが 401
  2: () => line.prepare('UPDATE channels SET access_token = ? WHERE id = ?')
    .run(crypto.randomBytes(8).toString('hex') + '/' + crypto.randomBytes(40).toString('base64'), mch.id),
  // 3: 応答メッセージを ON → 返事が2通
  3: () => line.prepare('UPDATE official_accounts SET auto_reply_on = 1 WHERE id = ?').run(oa.id),
  // 4: Webhook URL の slug を打ち間違える → 404
  4: () => {
    const typo = store.slug.includes('-') ? store.slug.replace('-', '_') : store.slug + 's';
    line.prepare('UPDATE channels SET webhook_url = replace(webhook_url, ?, ?) WHERE id = ?')
      .run(`/store/${store.slug}`, `/store/${typo}`, mch.id);
  },
  // 5: LIFF のエンドポイントURL を見本カフェにする → ちがう店が開く
  5: () => line.prepare('UPDATE liff_apps SET endpoint_url = replace(endpoint_url, ?, ?) WHERE liff_id = ?')
    .run(`/liff/s/${store.slug}`, '/liff/s/sample-cafe', store.liff_id),
  // 6: 未承認に戻す → 準備中
  6: () => app.prepare('UPDATE stores SET approved = 0 WHERE id = ?').run(store.id),
  // 7: メニューを全部消す → 買えない
  7: () => app.prepare('DELETE FROM menus WHERE store_id = ?').run(store.id),
  // 8: リッチメニューの LIFF ID を1文字変える → LIFF が見つからない
  8: () => {
    const rm = line.prepare('SELECT * FROM rich_menus WHERE oa_id = ? AND is_default = 1').get(oa.id);
    if (!rm) throw new Error('リッチメニューがありません');
    const acts = JSON.parse(rm.actions).map((a) => (a.type === 'link' ? { ...a, value: a.value.replace(/.$/, (c) => (c === 'x' ? 'y' : 'x')) } : a));
    line.prepare('UPDATE rich_menus SET actions = ? WHERE id = ?').run(JSON.stringify(acts), rm.id);
  },
  // 9: 自分のスマホが店の公式アカウントをブロック → push が届かない
  9: () => line.prepare('UPDATE friends SET blocked = 1 WHERE oa_id = ?').run(oa.id),
};

if (!actions[n]) { console.error('番号が正しくありません。list で一覧を見てください。'); process.exit(1); }
actions[n]();
console.log(`仕込みました。対象:「${store.name}」`);
console.log(`参加者に渡す症状カード：「${DRILLS[n].card}」`);
