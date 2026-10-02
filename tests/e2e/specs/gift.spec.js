// =====================================================
// E2E：お客さんがギフトを贈り、オーナーが受け取るまで
//
//   受講者が疑似スマホでやる操作を、そのままブラウザで自動再生します。
//   使うのは初期データの「シャンパンバー ルミエール」（構築済みの見本のお店）。
//
//   左のスマホ（お客さん）
//     トーク「ルミエール」→ リッチメニュー → 商品を選ぶ → 入力して「ギフトを贈る」
//   右のスマホ（オーナー）
//     トーク「おくりギフト」に届いた通知のリンク → 贈り物を開く → 「受け取る」
// =====================================================
const { test, expect } = require('@playwright/test');

test('お客さんがギフトを贈り、オーナーが受け取る', async ({ page }) => {
  const sender = `CIテスト${Date.now() % 100000}`;   // 毎回ちがう名前にして、自分の注文を見分ける

  await test.step('お客さん：お店のトークからリッチメニューを押す', async () => {
    await page.goto('/mock/phone/customer');
    await page.locator('a.talk', { hasText: 'ルミエール' }).click();
    await page.locator('.rm-area').first().click();     // 左はしのボタン＝「ギフトを贈る」
  });

  // LIFF の画面は、スマホの中の iframe で開く
  const liff = page.frameLocator('iframe[src*="/liff/"]');

  await test.step('お客さん：商品を選んで、ギフトを贈る', async () => {
    await expect(liff.getByText('シャンパンバー ルミエール').first()).toBeVisible();
    await liff.locator('a', { hasText: 'この商品を送る' }).first().click();

    await liff.locator('[name=sender_name]').fill(sender);
    await liff.locator('[name=message]').fill('CI からの自動テストです');
    // 初めてのお客さんはカードの入力欄が出る（登録済みなら出ない）
    const cardNumber = liff.locator('[name=card_number]');
    if (await cardNumber.isVisible()) {
      await cardNumber.fill('4242 4242 4242 4242');
      await liff.locator('[name=exp]').fill('12/40');
      await liff.locator('[name=cvc]').fill('123');
    }
    await liff.locator('#paybtn').click();

    await expect(liff.getByText('ありがとうございました')).toBeVisible();
    await expect(liff.getByText('ORDER NO')).toBeVisible();
  });

  await test.step('オーナー：通知が届き、リンクから贈り物一覧を開く', async () => {
    await page.goto('/mock/phone/owner');
    await page.locator('a.talk', { hasText: 'おくりギフト' }).click();
    // 届いた通知（送り主の名前が入っている吹き出し）の中のリンクを押す
    const notice = page.locator('div', { hasText: `${sender}様から` }).last();
    await expect(notice).toBeVisible();
    await notice.locator('a[href*="open="]').first().click();
  });

  await test.step('オーナー：贈り物を開いて「受け取る」', async () => {
    // 贈り物一覧の中から、さっき贈ったもの（送り主の名前で見分ける）のカードを探す
    const card = liff.locator('.l-card', { hasText: sender });
    await card.getByRole('link', { name: '贈り物を受け取る' }).click();
    await liff.getByRole('button', { name: 'この贈り物を受け取る' }).click();
    // 受け取ると同じ画面に戻り、次は「お礼を送る」ができるようになる
    await expect(liff.getByRole('link', { name: 'お礼を送る' })).toBeVisible();
  });
});

test('疑似スマホが2台並んで表示される', async ({ page }) => {
  await page.goto('/mock/phone');
  await expect(page.locator('iframe[src*="/mock/phone/customer"]')).toBeVisible();
  await expect(page.locator('iframe[src*="/mock/phone/owner"]')).toBeVisible();
});
