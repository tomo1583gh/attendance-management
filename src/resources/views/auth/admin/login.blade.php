@extends('layouts.auth')
@section('title','管理者ログイン')
@section('page_h1','管理者ログイン')

@section('content')
<form method="POST" action="{{ route('admin.login') }}" class="auth-form" novalidate>
  @csrf

  <label class="auth-label" for="email">メールアドレス</label>
  <input id="email" name="email" type="email" value="{{ old('email') }}" required class="auth-input" autocomplete="username">
  @error('email')<p class="auth-error">{{ $message }}</p>@enderror

  <label class="auth-label" for="password">パスワード</label>
  <input id="password" name="password" type="password" required class="auth-input" autocomplete="current-password">
  @error('password')<p class="auth-error">{{ $message }}</p>@enderror

  <button class="auth-btn auth-btn--primary" type="submit">管理者ログイン</button>
</form>
@endsection

@section('links')
<a href="{{ route('login') }}"class="auth-links__item">一般ログインはこちら</a>
@endsection
