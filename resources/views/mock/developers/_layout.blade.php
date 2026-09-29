@extends('layouts.lab')
@section('bar')@include('partials.account-bar')@endsection
@section('content')
  <div class="small text-muted mb-2"><a href="{{ route('mock.developers') }}">プロバイダー</a>
    @isset($provider) › <a href="{{ route('mock.developers.provider', $provider) }}">{{ $provider->name }}</a>@endisset
    @isset($channel) › {{ $channel->name }}@endisset</div>
  @yield('page')
@endsection
