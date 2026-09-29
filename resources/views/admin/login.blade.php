@extends('layouts.lab')
@section('title', 'ログイン')
@section('content')
<div class="row justify-content-center"><div class="col-md-5">
  <div class="card shadow-sm"><div class="card-body p-4">
    <h1 class="h4 mb-3">管理画面ログイン</h1>
    <form method="post" action="{{ route('admin.login.post') }}">
      @csrf
      <div class="mb-3"><label class="form-label">メールアドレス</label><input name="email" type="email" class="form-control" value="{{ old('email') }}" autofocus></div>
      <div class="mb-3"><label class="form-label">パスワード</label><input name="password" type="password" class="form-control"></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">ログインしたままにする</label></div>
      <button class="btn btn-primary w-100">ログイン</button>
    </form>
    <p class="small text-muted mt-3 mb-0">体験用アカウント：<code>admin@example.com</code> ／ <code>Taiken-2026</code></p>
  </div></div>
</div></div>
@endsection
