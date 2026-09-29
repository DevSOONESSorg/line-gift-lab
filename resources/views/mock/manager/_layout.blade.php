@extends('layouts.lab')
@section('containerClass', '')
@section('bar')@include('partials.account-bar')@endsection
@section('content')
<div class="d-flex mock-wrap">
  @isset($oa)
  <aside class="mock-side">
    <div class="d-flex gap-2 px-3 pb-3 border-bottom mb-2"><span class="oa-icon">{{ mb_substr($oa->name, 0, 1) }}</span>
      <div class="small"><strong>{{ $oa->name }}</strong><br>{{ $oa->basic_id }} ・ {{ $roles[$role] ?? '' }}</div></div>
    <a href="{{ route('mock.manager.oa.home', $oa) }}">ホーム</a>
    <a href="{{ route('mock.manager.oa.response', $oa) }}">応答設定</a>
    <a href="{{ route('mock.manager.oa.richmenus', $oa) }}">リッチメニュー</a>
    <div class="side-label">設定</div>
    <a href="{{ route('mock.manager.oa.settings', $oa) }}">アカウント設定</a>
    <a href="{{ route('mock.manager.oa.members', $oa) }}">権限管理</a>
    <a href="{{ route('mock.manager.oa.messaging', $oa) }}">Messaging API</a>
    <div class="side-label"></div>
    <a href="{{ route('mock.manager') }}">← アカウントリスト</a>
  </aside>
  @endisset
  <main class="flex-grow-1 p-4" style="max-width:1100px">
    @yield('page')
  </main>
</div>
@endsection
