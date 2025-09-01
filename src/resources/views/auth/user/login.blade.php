@extends('layouts.auth')
@section('title','ログイン')
@section('page_h1','ログイン')

@section('content')
<form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
  @csrf

  <label class="auth-label" for="email">メールアドレス</label>
  <input id="email" name="email" type="email" value="{{ old('email') }}" required class="auth-input" autocomplete="username">
  @error('email')<p class="auth-error">{{ $message }}</p>@enderror

  <label class="auth-label" for="password">パスワード</label>
  <input id="password" name="password" type="password" required class="auth-input" autocomplete="current-password">
  @error('password')<p class="auth-error">{{ $message }}</p>@enderror

  @if(session('auth_error'))<p class="auth-error">ログイン情報が登録されていません</p>@endif

  <button class="auth-btn auth-btn--primary" type="submit">ログイン</button>
</form>
@endsection

@section('links')
<a class="auth-link" href="{{ route('register') }}">会員登録はこちら</a>
@endsection
