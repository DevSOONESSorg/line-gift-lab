@extends('mock.manager._layout')
@section('title', 'アカウントリスト')
@section('page')
<div class="d-flex align-items-center mb-2"><h1 class="h4 mb-0">アカウントリスト</h1><a class="btn btn-line ms-auto" href="{{ route('mock.manager.create') }}"><i class="bi bi-plus-lg"></i> 作成</a></div>
<p class="text-muted small">いま「ログイン中」のアカウントがメンバーになっている公式アカウントだけが表示されます（別のアカウントに切り替えると、見えるものが変わります）。</p>
<div class="card"><table class="table mb-0 align-middle">
  <thead><tr><th>アカウント名</th><th>ベーシックID</th><th>権限</th><th>友だち</th></tr></thead><tbody>
  @forelse ($oas as $o)
    <tr><td><a class="d-flex align-items-center gap-2 text-decoration-none text-dark" href="{{ route('mock.manager.oa.home', $o) }}">@include('mock._avatar', ['oa' => $o, 'size' => 'sm']) <strong>{{ $o->name }}</strong></a></td>
      <td><code>{{ $o->basic_id }}</code></td><td>{{ $roles[$o->role] }}</td>
      <td>{{ \Illuminate\Support\Facades\DB::connection('mockline')->table('friends')->where(['official_account_id' => $o->id, 'blocked' => false])->count() }}</td></tr>
  @empty
    <tr><td colspan="4" class="text-muted text-center py-4">まだありません。右上の「作成」から公式アカウントを作りましょう。</td></tr>
  @endforelse
</tbody></table></div>
@endsection
