// =====================================================
// データを全部消して、最初の状態に戻す
//   docker compose run --rm app npm run reset   （アプリを止めてから）
// 次に起動したとき、見本データ入りで作り直されます。
// =====================================================
const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, '..', 'data');
for (const f of fs.readdirSync(dir)) {
  if (/\.db(-.*)?$/.test(f)) fs.rmSync(path.join(dir, f));
}
fs.rmSync(path.join(dir, 'uploads'), { recursive: true, force: true });
console.log('データを消しました。次の起動で初期状態から作り直されます。');
