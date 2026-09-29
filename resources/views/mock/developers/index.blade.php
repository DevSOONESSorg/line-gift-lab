@extends('mock.developers._layout')
@section('title', 'プロバイダー')
@section('page')
<h1 class="h4">プロバイダー</h1>
<p class="text-muted">疑似 LINE Developers Console です。プロバイダーは「会社の箱」で、その中にチャネル（Messaging API / LINEログイン）が入っています。</p>
<div class="d-flex flex-wrap gap-3">
  @forelse ($providers as $p)
    <a class="oa-card" href="{{ route('mock.developers.provider', $p) }}"><span class="oa-icon dev">P</span><strong>{{ $p->name }}</strong></a>
  @empty
    <p class="text-muted">「{{ $mockAccount->name }}」がメンバーになっているプロバイダーはありません。</p>
  @endforelse
</div>
@endsection
