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
  res.status(500).render('error', { title: 'エラーが発生しました', message: err.message });
});

const PORT = 3000;
app.listen(PORT, () => {
  console.log(`${config.APP_NAME}（教材）を起動しました → http://localhost:${config.HOST_PORT}`);
});
