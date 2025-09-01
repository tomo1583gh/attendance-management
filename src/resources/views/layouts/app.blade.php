<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title','勤怠管理アプリ')</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  </head>

  <body>
    <header class="site-header">
      <div class="container header-inner">
        <h1 class="site-title"><a href="/">勤怠管理</a></h1>
        <nav class="site-nav">
          @auth
            <a href="/attendance">打刻</a>
            <a href="/attendance/list">勤怠一覧</a>
            <a href="/stamp_correction_request/list">申請一覧</a>
            <form method="POST" action="/logout" class="inline">@csrf<button type="submit" class="btn btn-outline">ログアウト</button></form>
          @endauth
          @auth('admin')
            <a href="/admin/attendances">日次勤怠</a>
            <a href="/admin/users">スタッフ一覧</a>
            <a href="/admin/requests">申請一覧</a>
            <form method="POST" action="/admin/logout" class="inline">@csrf<button type="submit" class="btn btn-outline">ログアウト</button></form>
          @endauth
        </nav>
      </div>
    </header>

    <main class="container">
      @if(session('message'))<div class="alert success">{{ session('message') }}</div>@endif
      @yield('content')
    </main>

    <footer class="site-footer">
      <div class="container"><small>&copy; {{ date('Y') }} 勤怠管理</small></div>
    </footer>
  </body>
</html>
