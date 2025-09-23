<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class LoginTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function メールアドレスが未入力の場合_バリデーションメッセージが表示される()
  {
    // 1. ユーザーを登録
    $user = User::factory()->create([
      'password' => bcrypt('password123'),
    ]);

    // 2. メールアドレス以外のユーザー情報（パスワードのみ）を入力してログイン
    $response = $this->from('/login')->post('/login', [
      'email' => '', // 未入力
      'password' => 'password123',
    ]);

    // 3. リダイレクト先が /login であること
    $response->assertRedirect('/login');

    // 4. セッションに email のエラーが存在すること
    $response->assertSessionHasErrors(['email']);

    // 5. バリデーションメッセージの文言確認
    $this->assertEquals(
      'メールアドレスを入力してください',
      session('errors')->first('email')
    );
  }

  /** @test */
  public function パスワードが未入力の場合_バリデーションメッセージが表示される()
  {
    // 1. ユーザーを登録
    $user = User::factory()->create([
      'password' => bcrypt('password123'),
    ]);

    // 2. パスワードを空欄にしてログイン処理
    $response = $this->from('/login')->post('/login', [
      'email' => $user->email,
      'password' => '', // ← 未入力
    ]);

    // 3. リダイレクト先が /login であること
    $response->assertRedirect('/login');

    // 4. セッションに password のエラーが存在すること
    $response->assertSessionHasErrors(['password']);

    // 5. バリデーションメッセージの文言確認
    $this->assertEquals(
      'パスワードを入力してください',
      session('errors')->first('password')
    );
  }

  /** @test */
  public function 登録内容と一致しない場合_バリデーションメッセージが表示される()
  {
    // 1. 正しいユーザーを登録
    $user = User::factory()->create([
      'email' => 'test@example.com',
      'password' => bcrypt('password123'),
    ]);

    // 2. 誤ったメールアドレスを入力
    $response = $this->from('/login')->post('/login', [
      'email' => 'wrong@example.com', // 登録されていない
      'password' => 'password123',
    ]);

    // 3. ログイン画面にリダイレクトされること
    $response->assertRedirect('/login');

    // 4. エラーが email に対して返っていること
    $response->assertSessionHasErrors(['email']);

    // 5. メッセージ文言を確認
    $this->assertEquals(
      'ログイン情報が登録されていません',
      session('errors')->first('email')
    );
  }
}
