@extends('layouts.lab')
@section('title', '権限がありません')
@section('content')
  <h1 class="h4">権限がありません</h1>
  <div class="alert alert-danger">{{ $exception->getMessage() ?: 'この操作はできません。' }}</div>
  <a href="javascript:history.back()">← 戻る</a>
@endsection
