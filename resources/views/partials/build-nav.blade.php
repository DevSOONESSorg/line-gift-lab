{{-- 画面の右下に出る構築ナビ（構築するお店を選んでいるときだけ）。中身は数秒ごとに自動で最新にする --}}
@php $guide = \App\Services\BuildGuide::current(); @endphp
@if ($guide && ! request()->routeIs('build'))
  <div class="build-nav" id="buildnav" data-src="{{ route('build.nav') }}">
    @include('build._nav-body', ['guide' => $guide])
  </div>
  <script>
    (() => {
      const nav = document.getElementById('buildnav');
      // せまい画面では、はじめは小さくしておく（フォームのボタンをふさがないため）
      let saved = null; try { saved = localStorage.getItem('buildnav-min'); } catch (e) {}
      if (saved === '1' || (saved === null && window.innerWidth < 1200)) nav.classList.add('min');
      const sync = () => document.body.classList.toggle('buildnav-open', !nav.classList.contains('min'));
      sync();
      window.toggleBuildNav = () => { nav.classList.toggle('min'); sync(); try { localStorage.setItem('buildnav-min', nav.classList.contains('min') ? '1' : '0'); } catch (e) {} };
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
