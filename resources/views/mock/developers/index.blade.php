@extends('mock.developers._layout')
@section('title', 'LINE Developers')
@section('page')
<h1 class="h4">プロバイダー</h1>
<p class="text-muted small">プロバイダーは「サービスを提供する会社の箱」です。その中に、チャネル（Messaging API・LINEログイン）が入ります。いまログイン中の「{{ $mockAccount->name }}」がメンバーになっているプロバイダーだけが表示されます。</p>
<div class="d-flex flex-wrap gap-3">
  @forelse ($providers as $p)
    <a class="oa-card" href="{{ route('mock.developers.provider', $p) }}"><span class="oa-icon dev"><i class="bi bi-building"></i></span><strong>{{ $p->name }}</strong></a>
  @empty
    <p class="text-muted">「{{ $mockAccount->name }}」がメンバーになっているプロバイダーはありません。</p>
  @endforelse
</div>
@endsection
