<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title') - {{ config('app.name') }} 教材</title>
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/css/lab.css" rel="stylesheet">
  @stack('head')
</head>
<body class="@yield('bodyClass')">
  @include('partials.labnav')
  @yield('bar')
  <div class="@yield('containerClass', 'container py-4')">
    @include('partials.flash')
    @yield('content')
  </div>
  <footer class="text-center text-muted small py-4">株式会社SOONESS SE仕事体験コース 教材 ／ 「おくりギフト」「疑似LINE」は体験用の架空のサービスです</footer>
  <script src="/vendor/bootstrap/bootstrap.bundle.min.js"></script>
  @include('partials.build-nav')
  @stack('scripts')
</body>
</html>
