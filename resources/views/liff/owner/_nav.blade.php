<p class="small mt-2"><a href="{{ route('liff.manage') }}">← 店舗管理</a></p>
<h1 class="h6">{{ $store->name }} @if (! $store->is_approved)<span class="badge text-bg-warning">承認待ち</span>@endif</h1>
<nav class="nav nav-pills nav-fill small mb-3">
  <a class="nav-link {{ request()->routeIs('liff.manage.gifts', 'liff.manage.thank') ? 'active' : '' }}" href="{{ route('liff.manage.gifts', $store) }}">ギフト一覧</a>
  <a class="nav-link {{ request()->routeIs('liff.manage.menus') ? 'active' : '' }}" href="{{ route('liff.manage.menus', $store) }}">商品登録</a>
</nav>
