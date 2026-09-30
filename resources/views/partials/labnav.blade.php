{{-- 教材の画面を行き来するための上のメニュー（本物にはない） --}}
<nav class="labnav">
  <a class="brand" href="{{ route('home') }}">{{ config('app.name') }} <small>教材</small></a>
  <a class="g g-admin {{ request()->is('admin*') ? 'on' : '' }}" href="{{ route('admin.dashboard') }}">管理画面<small>自社</small></a>
  <a class="g g-phone {{ request()->is('mock/phone*') ? 'on' : '' }}" href="{{ route('mock.phone') }}">スマホ<small>疑似</small></a>
  <a class="g g-manager {{ request()->is('mock/manager*') ? 'on' : '' }}" href="{{ route('mock.manager') }}">Manager<small>疑似LINE</small></a>
  <a class="g g-dev {{ request()->is('mock/developers*') ? 'on' : '' }}" href="{{ route('mock.developers') }}">Dev Console<small>疑似LINE</small></a>
  <a class="g g-inside {{ request()->is('inside*') ? 'on' : '' }}" href="{{ route('inside') }}">裏側ビュー</a>
  <a class="g g-build {{ request()->is('build*') ? 'on' : '' }}" href="{{ route('build') }}"><i class="bi bi-signpost-split"></i> 構築ナビ</a>
</nav>
