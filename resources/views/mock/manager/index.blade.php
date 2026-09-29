@extends('mock.manager._layout')
@section('title', 'アカウントリスト')
@section('page')
<h1 class="h4">アカウントリスト <a class="btn btn-sm btn-success" href="{{ route('mock.manager.create') }}">作成</a></h1>
<p class="text-muted">疑似 LINE Official Account Manager です。ログイン中のアカウントがメンバーになっている公式アカウントだけが表示されます。</p>
<div class="d-flex flex-wrap gap-3">
  @forelse ($oas as $o)
    <a class="oa-card" href="{{ route('mock.manager.oa.home', $o) }}"><span class="oa-icon">{{ mb_substr($o->name, 0, 1) }}</span><span><strong>{{ $o->name }}</strong><br><small>{{ $o->basic_id }} ・ {{ $roles[$o->role] }}</small></span></a>
  @empty
    <p class="text-muted">まだありません。「作成」から公式アカウントを作りましょう。</p>
  @endforelse
</div>
@endsection
