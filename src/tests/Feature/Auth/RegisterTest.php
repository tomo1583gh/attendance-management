<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RegisterTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 名前が未入力の場合_バリデーションメッセージが表示される()
  {
    // 準備：入力データ（nameだけ未入力）
    $data = [
      'name' => '',
      'email' => 'test@example.com',
      'password' => 'password123',
      'password_confirmation' => 'password123',
    ];

    // 実行：会員登録処理をPOST
    $response = $this->from('/register')
      ->post('/register', $data);

    // 検証
    $response->assertRedirect('/register'); // 元の画面に戻る
    $response->assertSessionHasErrors(['name']); // nameにエラーがあること

    // 実際のメッセージを検証（日本語メッセージ）
    $this->assertEquals(
      'お名前を入力してください',
      session('errors')->first('name')
    );
  }

  #[Test]
  public function メールアドレスが未入力の場合_バリデーションメッセージが表示される()
  {
    // 準備：emailだけ未入力
    $data = [
      'name' => 'テスト太郎',
      'email' => '',
      'password' => 'password123',
      'password_confirmation' => 'password123',
    ];

    // 実行：会員登録処理をPOST
    $response = $this->from('/register')
      ->post('/register', $data);

    // 検証
    $response->assertRedirect('/register'); // 元の画面に戻る
    $response->assertSessionHasErrors(['email']); // emailにエラーがあること

    // 実際のメッセージを検証（日本語メッセージ）
    $this->assertEquals(
      'メールアドレスを入力してください',
      session('errors')->first('email')
    );
  }

  #[Test]
  public function パスワードが8文字未満の場合_バリデーションメッセージが表示される(): void
  {
    // Arrange: 入力データを準備（パスワードを短くする）
    $formData = [
      'name' => 'テストユーザー',
      'email' => 'shortpass@example.com',
      'password' => '1234567', // 7文字
      'password_confirmation' => '1234567',
    ];

    // Act: 会員登録リクエストを送信
    $response = $this->from('/register')->post('/register', $formData);

    // Assert: リダイレクトしてエラーメッセージを表示
    $response->assertRedirect('/register');
    $response->assertSessionHasErrors([
      'password' => 'パスワードは8文字以上で入力してください',
    ]);
  }

  #[Test]
  public function パスワードが一致しない場合_バリデーションメッセージが表示される(): void
  {
    // Arrange: 入力データを準備（password と password_confirmation を不一致にする）
    $formData = [
      'name' => 'テストユーザー',
      'email' => 'mismatch@example.com',
      'password' => 'password123',
      'password_confirmation' => 'different456',
    ];

    // Act: 会員登録リクエストを送信
    $response = $this->from('/register')->post('/register', $formData);

    // Assert: リダイレクトしてエラーメッセージを表示
    $response->assertRedirect('/register');
    $response->assertSessionHasErrors(['password']);

    // 実際のメッセージを検証（日本語バリデーション）
    $this->assertEquals(
      'パスワードと一致しません',
      session('errors')->first('password')
    );
  }

  #[Test]
  public function パスワードが未入力の場合_バリデーションメッセージが表示される(): void
  {
    // Arrange: 入力データを準備（password を未入力にする）
    $formData = [
      'name' => 'テストユーザー',
      'email' => 'nopassword@example.com',
      'password' => '',
      'password_confirmation' => '',
    ];

    // Act: 会員登録リクエストを送信
    $response = $this->from('/register')->post('/register', $formData);

    // Assert: リダイレクトしてエラーメッセージを表示
    $response->assertRedirect('/register');
    $response->assertSessionHasErrors(['password']);

    // 実際のメッセージを検証（日本語バリデーションメッセージ）
    $this->assertEquals(
      'パスワードを入力してください',
      session('errors')->first('password')
    );
  }

  #[Test]
  public function フォームに内容が入力されていた場合_データが正常に保存される(): void
  {
    // Arrange: 入力データを準備
    $formData = [
      'name' => '保存テストユーザー',
      'email' => 'savetest@example.com',
      'password' => 'password123',
      'password_confirmation' => 'password123',
    ];

    // Act: 会員登録リクエストを送信
    $response = $this->post('/register', $formData);

    // Assert: リダイレクトが発生（登録後の想定ルートへ）
    $response->assertRedirect('/email/verify'); // Fortify の設定によっては /home や /mypage/profile に変わる場合あり

    // データベースにユーザーが保存されていることを確認
    $this->assertDatabaseHas('users', [
      'name' => '保存テストユーザー',
      'email' => 'savetest@example.com',
    ]);
  }
}


