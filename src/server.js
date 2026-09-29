// =====================================================
// アプリの入口（ここから起動します）
//   npm run dev  または  docker compose up
//
// この1つのサーバーの中に、立場のちがう2つのシステムが入っています。
//   ① 自社サービス「おくりギフト」 … /admin, /liff, /api/webhook   （src/app/）
//   ② 疑似LINE（LINE社の役）       … /mock, /mock-line-api          （src/mockline/）
//   ＋ 裏側ビュー（観察カメラ）      … /inside                         （src/inside.js）
// 本物では ① と ② は別の会社の別のサーバーです。
// =====================================================
const path = require('path');
const express = require('express');
const config = require('./config');
const util = require('./util');
const mock = require('./mockline/core');

require('./seed').run();

// ---- 古いデータのまま新しい版を動かしていないかチェック ----
{
  const cols = require('./app/db').prepare('PRAGMA table_info(orders)').all().map((c) => c.name);
  if (!cols.includes('route')) {
    console.warn('\n★ data/ のデータが古い版のものです。止めてから次を実行して、作り直してください:');
    console.warn('   docker compose run --rm app npm run reset\n');
  }
}

// ---- async の処理でエラーが起きても、サーバーが落ちないようにする ----
// （Express 4 は async 関数のエラーを拾わないので、ここで拾ってエラー画面に回す）
const Layer = require('express/lib/router/layer');
const origFn = Layer.prototype.handle_request;
Layer.prototype.handle_request = function (req, res, next) {
  const fn = this.handle;
  if (fn.length > 3) return origFn.call(this, req, res, next);
  try {
    const r = fn(req, res, next);
    if (r && typeof r.catch === 'function') r.catch(next);
  } catch (e) {
    next(e);
  }
};
process.on('unhandledRejection', (e) => console.error('処理されなかったエラー:', e));

const app = express();
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(express.static(path.join(__dirname, '..', 'public')));
app.use('/mock/uploads', express.static(path.join(__dirname, '..', 'data', 'uploads')));

// どの画面でも使う共通の値
app.use((req, res, next) => {
  res.locals.appName = config.APP_NAME;
  res.locals.msg = req.query.msg || '';
  res.locals.area = req.path.split('/')[1] || 'home';
  res.locals.mockPage = req.path.split('/')[2] || '';
  // 疑似LINE の Manager / Developers に「誰としてログインしているか」
  const accId = util.cookie(req, 'mock_account') || 'personal';
  res.locals.account = mock.q.account(accId) || mock.q.account('personal');
  res.locals.accounts = mock.q.accounts();
  next();
});

// ---------- 自社サービス ----------
app.use('/api/webhook', require('./app/routes/webhook'));
app.use('/admin', require('./app/routes/admin'));
app.use('/liff', require('./app/routes/liff'));

// ---------- 疑似LINE ----------
app.use('/mock-line-api', require('./mockline/routes/api'));
app.use('/mock/phone', require('./mockline/routes/phone'));
app.use('/mock/manager', require('./mockline/routes/manager'));
app.use('/mock/developers', require('./mockline/routes/developers'));
app.post('/mock/switch-account', express.urlencoded({ extended: false }), (req, res) => {
  const acc = mock.q.account(req.body.account_id);
  if (acc) res.setHeader('Set-Cookie', `mock_account=${acc.id}; Path=/; SameSite=Lax`);
  res.redirect(req.body.back && req.body.back.startsWith('/mock/') ? req.body.back.replace(/[?&]msg=[^&]*/, '') : '/mock/manager');
});

// ---------- 裏側ビュー・トップ ----------
app.use('/inside', require('./inside'));
app.get('/', (req, res) => res.render('home', { title: 'トップ' }));

app.use((req, res) => {
  res.status(404).render('error', { title: 'ページが見つかりません', message: `${req.path} はありません。` });
});
app.use((err, req, res, next) => {
  console.error(err);
  const old = /no such column|has no column/.test(err.message);
  res.status(500).render('error', {
    title: 'エラーが発生しました',
    message: err.message + (old ? '（data/ のデータが古い版のものです。止めてから「docker compose run --rm app npm run reset」で作り直してください）' : ''),
  });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`${config.APP_NAME}（教材）を起動しました → http://localhost:${config.HOST_PORT}`);
});
