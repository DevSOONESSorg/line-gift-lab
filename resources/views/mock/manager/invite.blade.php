@extends('mock.manager._layout')
@section('title', 'メンバー招待')
@section('page')
<h1 class="h4">メンバー招待</h1>
@if ($error)
  <div class="alert alert-danger">{{ $error }}</div>
@elseif ($already)
  <div class="alert alert-info">「{{ $mockAccount->name }}」はすでに「{{ $invOa->name }}」のメンバーです。<a href="{{ route('mock.manager.oa.home', $invOa) }}">開く</a><br>
    <small>招待した本人のアカウントのまま開いていませんか？ 参加させたいアカウントに切り替えてから、もう一度開いてください。</small></div>
@else
  <div class="card card-body" style="max-width:600px">
    <p>「<strong>{{ $invOa->name }}</strong>」に <strong>{{ $roles[$inv->role] }}</strong> として参加します。</p>
    <p>参加するアカウント：<strong>{{ $mockAccount->name }}</strong>（{{ $mockAccount->email }}）</p>
    <form method="post">@csrf<button class="btn btn-success">参加する</button></form>
  </div>
@endif
@endsection
