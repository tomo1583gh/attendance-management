<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <title>@yield('title', '勤怠管理')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
  <header class="app-header">
    <div class="app-header__inner">
      @php
        // 管理画面かどうかを判定（ガード優先、保険でURLも見る）
        $isAdmin = Auth::guard('admin')->check() || request()->is('admin/*');
      @endphp

      <a href="{{ $isAdmin ? route('admin.attendances.daily') : route('attendance.index') }}"
         class="brand" aria-label="ホーム">
        <img class="auth-logo" src="{{ asset('images/logo-image/logo.svg') }}" alt="COACHTECH">
      </a>

      <nav class="nav">
        @if ($isAdmin)
          {{-- 管理者ナビ --}}
          <a href="{{ route('admin.attendances.daily') }}"
             class="{{ request()->routeIs('admin.attendances.*') ? 'is-active' : '' }}">勤怠一覧</a>
          <a href="{{ route('admin.users.index') }}"
             class="{{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}">スタッフ一覧</a>
          <a href="{{ route('admin.requests.index') }}"
             class="{{ request()->routeIs('admin.requests.*') ? 'is-active' : '' }}">申請一覧</a>

          <form action="{{ route('admin.logout') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="nav-logout">ログアウト</button>
          </form>
        @else
          {{-- 一般ユーザーナビ --}}
          <a href="{{ route('attendance.index') }}"
             class="{{ request()->routeIs('attendance.index') ? 'is-active' : '' }}">勤怠</a>
          <a href="{{ route('attendance.list') }}"
             class="{{ request()->routeIs('attendance.list') ? 'is-active' : '' }}">勤怠一覧</a>
          <a href="{{ route('request.list') }}"
             class="{{ request()->routeIs('request.*') ? 'is-active' : '' }}">申請</a>

          <form action="{{ route('logout') }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="nav-logout">ログアウト</button>
          </form>
        @endif
      </nav>
    </div>
  </header>

  <main class="main state--@yield('state')">
    <section class="panel">
      @yield('content')
    </section>
  </main>
</body>
</html>
