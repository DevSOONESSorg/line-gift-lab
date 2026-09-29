@extends('mock.manager._layout')
@section('title', '権限管理')
@section('page')
<h1 class="h4">権限管理</h1>
<table class="table bg-white"><thead><tr><th>メンバー</th><th>メール</th><th>権限</th></tr></thead><tbody>
  @foreach ($members as $m)<tr><td>{{ $m->name }}</td><td>{{ $m->email }}</td><td>{{ $roles[$m->role] }}</td></tr>@endforeach
</tbody></table>
<div class="card card-body">
  <h2 class="h6">メンバーを追加</h2>
  @if ($role !== 'admin')
    <p class="text-muted mb-0">メンバーを追加できるのは管理者だけです。</p>
  @else
    <form method="post" action="{{ route('mock.manager.oa.members.invite', $oa) }}" class="d-flex gap-2">@csrf
      <select name="role" class="form-select w-auto"><option value="operator">運用担当者（リッチメニュー・応答設定・Messaging API が扱える）</option><option value="admin">管理者（すべて）</option></select>
      <button class="btn btn-success">URLを発行</button></form>
  @endif
  @if ($invite)
    @php $inviteUrl = route('mock.manager.invite', $invite->token); @endphp
    <div class="alert alert-success mt-3 mb-0">
      招待URLを発行しました（<strong>24時間有効・1回だけ</strong>）。参加してもらう人に渡してください。
      <div class="input-group mt-2"><input class="form-control font-monospace small" value="{{ $inviteUrl }}" readonly><button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('{{ $inviteUrl }}'); this.textContent='コピーしました'">コピー</button></div>
      <div class="small mt-2">受け取った人は、<strong>自分のアカウントでログインしてから</strong>このURLを開きます。体験では上の「ログイン中」を「会社の作業アカウント」に切り替えてから開きます。</div>
    </div>
  @endif
</div>
@endsection
