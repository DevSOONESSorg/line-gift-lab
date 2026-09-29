@extends('layouts.liff')
@section('title', $title)
@section('content')
  <h1 class="h5 mt-3">{{ $title }}</h1>
  <p>{{ $message }}</p>
@endsection
