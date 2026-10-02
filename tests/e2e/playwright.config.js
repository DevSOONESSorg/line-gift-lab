// =====================================================
// E2E テスト（ブラウザを自動で操作するテスト）の設定
//   動かし方（教材を起動した状態で）:
//     cd tests/e2e && npm ci && npx playwright test
//   BASE_URL を変えると、別の場所で動いている教材にも使えます
// =====================================================
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './specs',
  timeout: 60_000,
  retries: process.env.CI ? 1 : 0,       // CI では、たまたまの失敗に備えて1回だけやり直す
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:3000',
    viewport: { width: 420, height: 860 },  // スマホの縦長の画面
    locale: 'ja-JP',
    screenshot: 'only-on-failure',          // 失敗したら、その瞬間の画面を残す
    trace: 'retain-on-failure',             // 失敗したら、操作の記録（あとで再生できる）を残す
  },
});
