<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title','認証') - 勤怠管理</title>
  <link rel="stylesheet" href="{{ asset('css/style.auth.css') }}">
</head>
<body class="auth-body">
  <header class="auth-header" role="banner">
    <div class="auth-header__inner">
      <a href="{{ url('/') }}" class="auth-logo" aria-label="COACHTECH">
        <img src="{{ asset('images/logo-image/logo.svg') }}" alt="COACHTECH ロゴ">
      </a>
    </div>
  </header>

  <main class="auth-main" role="main">
    <section class="auth-card" aria-labelledby="auth-title">
      <h1 id="auth-title" class="auth-title">@yield('page_h1')</h1>

      @if(session('status'))
        <div class="auth-alert info">{{ session('status') }}</div>
      @endif

      @yield('content')

      <div class="auth-links">
        @yield('links')
      </div>
    </section>
  </main>
</body>
</html>
