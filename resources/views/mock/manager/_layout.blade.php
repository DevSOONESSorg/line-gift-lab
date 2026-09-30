@extends('layouts.lab')
@section('containerClass', '')
@section('bar')
  {{-- 本物の LINE Official Account Manager の上のバー（緑）に似せています --}}
  <div class="oam-top">
    <a class="oam-logo" href="{{ route('mock.manager') }}"><b>LINE</b> Official Account Manager <small>疑似</small></a>
    @isset($oa)<span class="oam-cur">@include('mock._avatar', ['oa' => $oa, 'size' => 'xs']) {{ $oa->name }} <small>{{ $oa->basic_id }}</small></span>@endisset
    <span class="ms-auto">@include('partials.account-bar')</span>
  </div>
@endsection
@section('content')
<div class="d-flex mock-wrap">
  @isset($oa)
  <aside class="mock-side oam-side">
    <a class="{{ request()->routeIs('mock.manager.oa.home') ? 'on' : '' }}" href="{{ route('mock.manager.oa.home', $oa) }}"><i class="bi bi-house"></i> ホーム</a>
    <span class="off"><i class="bi bi-chat-dots"></i> チャット</span>
    <span class="off"><i class="bi bi-bar-chart"></i> 分析</span>
    <div class="side-label">トークルーム管理</div>
    <a class="{{ request()->routeIs('mock.manager.oa.richmenus*') ? 'on' : '' }}" href="{{ route('mock.manager.oa.richmenus', $oa) }}"><i class="bi bi-grid-3x2"></i> リッチメニュー</a>
    <div class="side-label">設定</div>
    <a class="{{ request()->routeIs('mock.manager.oa.settings') ? 'on' : '' }}" href="{{ route('mock.manager.oa.settings', $oa) }}"><i class="bi bi-gear"></i> アカウント設定</a>
    <a class="{{ request()->routeIs('mock.manager.oa.members') ? 'on' : '' }}" href="{{ route('mock.manager.oa.members', $oa) }}"><i class="bi bi-people"></i> 権限管理</a>
    <a class="{{ request()->routeIs('mock.manager.oa.response') ? 'on' : '' }}" href="{{ route('mock.manager.oa.response', $oa) }}"><i class="bi bi-reply"></i> 応答設定</a>
    <a class="{{ request()->routeIs('mock.manager.oa.messaging') ? 'on' : '' }}" href="{{ route('mock.manager.oa.messaging', $oa) }}"><i class="bi bi-code-slash"></i> Messaging API</a>
    <div class="side-label"></div>
    <a href="{{ route('mock.manager') }}"><i class="bi bi-list-ul"></i> アカウントリスト</a>
  </aside>
  @endisset
  <main class="flex-grow-1 p-4 oam-main">
    @yield('page')
  </main>
</div>
@endsection
