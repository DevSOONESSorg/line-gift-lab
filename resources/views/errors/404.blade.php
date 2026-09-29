@extends('layouts.lab')
@section('title', '見つかりません')
@section('content')
  <h1 class="h4">ページが見つかりません</h1>
  <div class="alert alert-secondary">{{ $exception->getMessage() ?: request()->path().' はありません。' }}</div>
  <a href="javascript:history.back()">← 戻る</a>
@endsection
