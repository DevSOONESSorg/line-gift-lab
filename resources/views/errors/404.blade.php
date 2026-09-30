@extends('layouts.lab')
@section('title', '見つかりません')
@section('content')
  <h1 class="h4">ページが見つかりません</h1>
  {{-- abort(404, '…') で渡した日本語のメッセージだけ表示する（URL違いのときの英語の定型文は出さない） --}}
  @php $msg = $exception->getMessage(); @endphp
  <div class="alert alert-secondary">{{ ($msg && ! str_contains($msg, 'could not be found')) ? $msg : '「/'.request()->path().'」というページはありません。URLを確かめてください。' }}</div>
  <a href="javascript:history.back()">← 戻る</a>
@endsection
