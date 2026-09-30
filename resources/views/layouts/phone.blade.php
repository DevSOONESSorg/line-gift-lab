<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title')</title>
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
  <link href="/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="/css/lab.css" rel="stylesheet">
</head>
{{-- 疑似スマホ1台ぶんの画面。/mock/phone の左右の枠（iframe）の中に表示される --}}
<body class="phone-screen">
  @yield('content')
  @stack('scripts')
</body>
</html>
