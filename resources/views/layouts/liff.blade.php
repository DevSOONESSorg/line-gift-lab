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
  @if (! empty($mockUid))
  <script>
    // 疑似スマホ：この画面を開いているのが「どちらのスマホの人か」を、次に開く画面にも伝える
    // （2台のスマホを同じブラウザで開いているので、URL に ?mock_uid= を付けて区別する）
    (() => {
      const uid = @json($mockUid);
      const add = (href) => { const u = new URL(href, location.href); if (u.origin === location.origin && u.pathname.startsWith('/liff')) u.searchParams.set('mock_uid', uid); return u.toString(); };
      if (!new URLSearchParams(location.search).has('mock_uid')) history.replaceState(null, '', add(location.href));
      document.querySelectorAll('a[href]').forEach((a) => { a.href = add(a.getAttribute('href')); });
      document.querySelectorAll('form').forEach((f) => {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'mock_uid'; i.value = uid; f.appendChild(i);
        if (f.method.toLowerCase() === 'post') f.action = add(f.getAttribute('action') || location.href);
      });
    })();
  </script>
  @endif
  @stack('scripts')
</body>
</html>
