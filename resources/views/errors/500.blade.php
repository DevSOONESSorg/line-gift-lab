@extends('layouts.lab')
@section('title', 'エラー')
@section('content')
  <h1 class="h4">エラーが発生しました</h1>
  <div class="alert alert-danger">サーバーの中でエラーが起きました。ターミナル（docker compose のログ）と storage/logs/laravel.log を見てみましょう。</div>
  <a href="javascript:history.back()">← 戻る</a>
@endsection
