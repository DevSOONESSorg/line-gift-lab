<p class="small mb-2"><a href="{{ route('liff.manage') }}">← 店舗管理</a></p>
<div class="l-h">{{ $store->name }} - {{ request()->routeIs('liff.manage.menus') ? '商品登録' : '贈り物一覧' }} @if (! $store->is_approved)<span class="pill pill-wait align-middle">承認待ち</span>@endif</div>
<nav class="o-nav">
  <a class="{{ request()->routeIs('liff.manage.gifts') ? 'on' : '' }}" href="{{ route('liff.manage.gifts', $store) }}">贈り物一覧</a>
  <a class="{{ request()->routeIs('liff.manage.menus') ? 'on' : '' }}" href="{{ route('liff.manage.menus', $store) }}">商品登録</a>
</nav>
