<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title')</title>
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/css/liff.css" rel="stylesheet">
</head>
<body>
  <div class="liff">
    @include('partials.flash')
    @yield('content')
  </div>
  @stack('scripts')
</body>
</html>
