@extends('layouts.auth')
@section('title','会員登録')
@section('page_h1','会員登録')

@section('content')
<form method="POST" action="{{ route('register') }}" class="auth-form" novalidate>
  @csrf

  <label class="auth-label" for="name">名前</label>
  <input id="name" name="name" type="text" value="{{ old('name') }}" required class="auth-input" autocomplete="name">
  @error('name')<p class="auth-error">{{ $message }}</p>@enderror

  <label class="auth-label" for="email">メールアドレス</label>
  <input id="email" name="email" type="email" value="{{ old('email') }}" required class="auth-input" autocomplete="email">
  @error('email')<p class="auth-error">{{ $message }}</p>@enderror

  <label class="auth-label" for="password">パスワード</label>
  <input id="password" name="password" type="password" required minlength="8" class="auth-input" autocomplete="new-password">
  @error('password')<p class="auth-error">{{ $message }}</p>@enderror

  <label class="auth-label" for="password_confirmation">パスワード確認</label>
  <input id="password_confirmation" name="password_confirmation" type="password" required class="auth-input" autocomplete="new-password">
  @error('password_confirmation')<p class="auth-error">{{ $message }}</p>@enderror

  <button class="auth-btn auth-btn--primary" type="submit">登録する</button>
</form>
@endsection

@section('links')
<a class="auth-link" href="{{ route('login') }}">ログインはこちら</a>
@endsection
