{{-- 画面の右下に出る構築ナビ（構築するお店を選んでいるときだけ）。中身は数秒ごとに自動で最新にする --}}
@php $guide = \App\Services\BuildGuide::current(); @endphp
@if ($guide && ! request()->routeIs('build'))
  <div class="build-nav" id="buildnav" data-src="{{ route('build.nav') }}">
    @include('build._nav-body', ['guide' => $guide])
  </div>
  <script>
    (() => {
      const nav = document.getElementById('buildnav');
      try { if (localStorage.getItem('buildnav-min')) nav.classList.add('min'); } catch (e) {}
      window.toggleBuildNav = () => { nav.classList.toggle('min'); try { localStorage.setItem('buildnav-min', nav.classList.contains('min') ? '1' : ''); } catch (e) {} };
      const scroll = () => nav.querySelector('.bstep-current')?.scrollIntoView({ block: 'nearest' });
      scroll();
      // ほかのタブや疑似スマホで操作したときも、ナビが追いつくように3秒ごとに確かめる
      setInterval(async () => {
        if (document.hidden) return;
        const html = await fetch(nav.dataset.src + '?uri=' + encodeURIComponent(location.pathname + location.search)).then((r) => r.text()).catch(() => null);
        if (!html) return;
        const t = document.createElement('div'); t.innerHTML = html;
        if (t.querySelector('[data-progress]')?.dataset.progress !== nav.querySelector('[data-progress]')?.dataset.progress) { nav.innerHTML = html; scroll(); }
      }, 3000);
    })();
  </script>
@endif
