@extends('layouts.lab')
@section('containerClass', '')
@section('bar')
  {{-- 本物の LINE Developers コンソールの上のバーに似せています --}}
  <div class="ldc-top">
    <a class="ldc-logo" href="{{ route('mock.developers') }}"><b>LINE</b> Developers <small>コンソール・疑似</small></a>
    <span class="ms-auto">@include('partials.account-bar')</span>
  </div>
@endsection
@section('content')
<div class="d-flex mock-wrap">
  <aside class="mock-side ldc-side">
    <a class="{{ request()->routeIs('mock.developers') ? 'on' : '' }}" href="{{ route('mock.developers') }}"><i class="bi bi-house"></i> コンソールホーム</a>
    <div class="side-label">プロバイダー</div>
    @foreach (\App\Models\Mock\Provider::of(session('mock_account', 'personal')) as $p)
      <a class="{{ isset($provider) && $provider->id === $p->id ? 'on' : '' }}" href="{{ route('mock.developers.provider', $p) }}"><i class="bi bi-building"></i> {{ \Illuminate\Support\Str::limit($p->name, 22) }}</a>
    @endforeach
  </aside>
  <main class="flex-grow-1 p-4 ldc-main">
    <div class="small text-muted mb-2"><a href="{{ route('mock.developers') }}">TOP</a>
      @isset($provider) › <a href="{{ route('mock.developers.provider', $provider) }}">{{ $provider->name }}</a>@endisset
      @isset($channel) › {{ $channel->name }}@endisset</div>
    @yield('page')
  </main>
</div>
@endsection
