<!DOCTYPE html>
<html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>領収書 #{{ $order->id }}</title>
<style>body{font-family:"Hiragino Sans","Noto Sans JP",sans-serif;max-width:560px;margin:30px auto;padding:20px;border:1px solid #333}h1{text-align:center;letter-spacing:.5em}.to{font-size:20px;border-bottom:1px solid #333;padding-bottom:4px}.amount{font-size:28px;text-align:center;margin:20px 0;border:2px solid #333;padding:10px}dl{display:grid;grid-template-columns:120px 1fr;gap:6px}dt{color:#555}.print{text-align:center;margin-top:20px}@media print{.print{display:none}}</style></head>
<body>
  <h1>領収書</h1>
  <p class="to">{{ $order->senderLabel() }} 様</p>
  <div class="amount">¥{{ number_format($order->amount) }}-（税込）</div>
  <p>但し　ギフト代として、上記正に領収いたしました。</p>
  <dl>
    <dt>注文番号</dt><dd>{{ $order->order_code }}</dd>
    <dt>お店</dt><dd>{{ $order->store->name }}</dd>
    <dt>品目</dt><dd>{{ $order->menu_name }}</dd>
    <dt>決済日</dt><dd>{{ ($order->received_at ?? $order->paid_at)?->format('Y年m月d日') }}</dd>
    <dt>お支払い</dt><dd>{{ $order->payment_method->label() }}{{ $order->card_last4 ? " **** {$order->card_last4}" : '' }}</dd>
  </dl>
  <p style="text-align:right;margin-top:30px">{{ config('app.name') }}（体験用の架空の発行者）</p>
  <div class="print"><button onclick="print()">印刷・PDFで保存</button></div>
</body></html>
