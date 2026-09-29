// =====================================================
// 設定ファイル
// アプリの「決まりごと」をここにまとめています。
// =====================================================

module.exports = {
  // 画面左上に出るサービス名（.env の APP_NAME があればそちらが優先）
  APP_NAME: process.env.APP_NAME || 'おくりギフト',

  // ブラウザで開くときのポート（docker-compose.yml から渡される）
  HOST_PORT: process.env.HOST_PORT || process.env.PORT || 3000,

  // 公開URL（コースB）。空なら cloudflared から自動で取得を試みる
  PUBLIC_URL: (process.env.PUBLIC_URL || '').replace(/\/$/, ''),
  TUNNEL_METRICS: process.env.TUNNEL_METRICS || '',

  // 新しい店舗のデフォルト手数料率（%）
  DEFAULT_FEE_RATE: 10,

  // テストカード（本物のお金は動きません）
  TEST_CARDS: {
    '4242424242424242': 'ok',        // 成功する
    '4000000000000002': 'declined',  // 「カードが拒否されました」になる
  },

  // 疑似LINE：応答メッセージ（Manager の「応答メッセージ」がONのときに自動で返る文）
  DEFAULT_AUTO_REPLY:
    'メッセージありがとうございます！\n申し訳ありませんが、このアカウントから個別のご返信はできません。',

  // 疑似LINE：あいさつメッセージ（友だち追加したときに届く文）
  DEFAULT_GREETING: '友だち追加ありがとうございます！',

  // 疑似LINE：リッチメニューのテンプレート
  //   areas は画像全体を 1 としたときの位置（x, y, 幅 w, 高さ h）
  RICHMENU_TEMPLATES: {
    'large-6': { label: '大・6分割（3×2）', size: 'large', areas: grid(3, 2) },
    'large-4': { label: '大・4分割（2×2）', size: 'large', areas: grid(2, 2) },
    'large-3': { label: '大・3分割（上1＋下2）', size: 'large',
      areas: [{ x: 0, y: 0, w: 1, h: 0.5 }, { x: 0, y: 0.5, w: 0.5, h: 0.5 }, { x: 0.5, y: 0.5, w: 0.5, h: 0.5 }] },
    'large-1': { label: '大・1分割', size: 'large', areas: grid(1, 1) },
    'small-3': { label: '小・3分割（横3）', size: 'small', areas: grid(3, 1) },
    'small-2': { label: '小・2分割（横2）', size: 'small', areas: grid(2, 1) },
    'small-1': { label: '小・1分割', size: 'small', areas: grid(1, 1) },
  },
  RICHMENU_SIZES: {
    large: [[2500, 1686], [1200, 810], [800, 540]],
    small: [[2500, 843], [1200, 405], [800, 270]],
  },
};

function grid(cols, rows) {
  const areas = [];
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < cols; c++) {
      areas.push({ x: c / cols, y: r / rows, w: 1 / cols, h: 1 / rows });
    }
  }
  return areas;
}
