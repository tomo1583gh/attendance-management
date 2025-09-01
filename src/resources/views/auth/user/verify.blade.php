@extends('layouts.auth')
@section('title','メール認証')

{{-- 画面タイトルはデザイン上表示しない（スクリーンリーダー用にsr-only） --}}
@section('page_h1')
<span class="sr-only">メール認証のお願い</span>
@endsection

@section('content')
<div class="verify-hero" role="region" aria-label="メール認証の案内">
  <p class="verify-text">
    登録していただいたメールアドレスに認証メールを送付しました。
  </p>
  <p class="verify-text">
    メール認証を完了してください。
  </p>

  {{-- デザイン上の大きなボタン（実機ではメール内リンクで認証） --}}
  <a class="verify-btn" href="#" role="button" aria-disabled="true">認証はこちらから</a>

  {{-- 再送はFormで実動 --}}
  <form method="POST" action="{{ route('verification.send') }}" class="verify-resend">
    @csrf
    <button class="verify-resend-link" type="submit">認証メールを再送する</button>
  </form>
</div>
@endsection
