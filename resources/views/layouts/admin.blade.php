<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title') - {{ config('app.name') }}</title>
  {{-- 実物の管理画面と同じく Bootstrap 5.3 と Bootstrap Icons を使う（実物は CDN から、教材はネットがなくても動くよう public/vendor に同梱） --}}
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/css/lab.css" rel="stylesheet">
</head>
<body class="admin-body">
  @include('partials.labnav')
  <div class="d-flex">
    <aside class="admin-side d-flex flex-column">
      <div class="admin-brand">{{ config('app.name') }}</div>
      @php
        $menu = [
          ['admin.dashboard', 'speedometer2', 'ダッシュボード', 'admin'],
          ['admin.stores.index', 'shop', '店舗管理', 'admin/stores*'],
          ['admin.orders.index', 'bag', '注文管理', 'admin/orders*'],
          ['admin.users.index', 'people', 'ユーザー管理', 'admin/users*'],
          ['admin.sales', 'graph-up', '売上集計', 'admin/sales*'],
          ['admin.menu-templates.index', 'journal-text', '商品テンプレート', 'admin/menu-templates*'],
          ['admin.agents.index', 'diagram-3', '代理店管理', 'admin/agents*'],
        ];
      @endphp
      <nav class="nav flex-column">
        @foreach ($menu as [$route, $icon, $label, $pattern])
          <a class="nav-link {{ request()->is($pattern) ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi bi-{{ $icon }} me-2"></i>{{ $label }}</a>
        @endforeach
        <form method="post" action="{{ route('admin.logout') }}" class="mt-3">@csrf
          <button class="nav-link btn btn-link text-start w-100"><i class="bi bi-box-arrow-left me-2"></i>ログアウト</button></form>
      </nav>
      @php $platformBasicId = \App\Models\Setting::get('platform_basic_id'); @endphp
      <div class="mt-auto p-3 border-top border-secondary text-center">
        <div class="small text-white-50 mb-1">LINE友だち登録（出店の入口）</div>
        <div id="qr" class="bg-white p-2 d-inline-block rounded"></div>
        <div class="small mt-1"><code class="text-white-50">{{ $platformBasicId }}</code></div>
        <a class="btn btn-sm btn-outline-light mt-2" href="{{ route('mock.phone', ['add' => $platformBasicId, 'to' => 'owner']) }}">オーナーのスマホで読み取る</a>
      </div>
    </aside>
    <main class="admin-main flex-grow-1 p-4">
      @include('partials.flash')
      @yield('content')
    </main>
  </div>
  {{-- 環境の表示（実物の「PROD」表示と同じ役割。本番を触っていないか、いつも目で確認する） --}}
  <div class="stage-badge stage-{{ $stage }}">{{ strtoupper($stage) }}</div>
  <script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  <script src="/vendor/qrcodejs/qrcode.min.js"></script>
  <script>
    new QRCode(document.getElementById('qr'), { text: 'https://line.me/R/ti/p/{{ $platformBasicId }}', width: 96, height: 96 });
    // data-confirm が付いたフォームは、送信前に確認する（取消・削除などの取り返しのつかない操作）
    document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
  </script>
  @stack('scripts')
</body>
</html>
