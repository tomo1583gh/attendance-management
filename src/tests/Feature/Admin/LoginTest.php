<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Admin; // 管理者モデルを利用している想定

class LoginTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
  public function メールアドレス未入力の場合_バリデーションメッセージが表示される()
  {
    // 1. 管理者ユーザーを登録
    $admin = Admin::factory()->create([
      'password' => bcrypt('password123'),
    ]);

    // 2. メールアドレスを未入力でログイン処理
    $response = $this->from('/admin/login')->post('/admin/login', [
      'email' => '', // 未入力
      'password' => 'password123',
    ]);

    // 3. ログイン画面にリダイレクトされること
    $response->assertRedirect('/admin/login');

    // 4. email にバリデーションエラーがあること
    $response->assertSessionHasErrors(['email']);

    // 5. エラーメッセージ文言の確認
    $this->assertEquals(
      'メールアドレスを入力してください',
      session('errors')->first('email')
    );
  }

  /** @test */
  public function パスワードが未入力の場合_バリデーションメッセージが表示される()
  {
    // 1. 管理者を登録
    $admin = Admin::factory()->create([
      'password' => bcrypt('password123'),
    ]);

    // 2. パスワード未入力でログイン処理
    $response = $this->from('/admin/login')->post('/admin/login', [
      'email' => $admin->email,
      'password' => '', // 未入力
    ]);

    // 3. 元のログイン画面にリダイレクトされること
    $response->assertRedirect('/admin/login');

    // 4. セッションに password のエラーがあること
    $response->assertSessionHasErrors(['password']);

    // 5. バリデーションメッセージが正しいこと
    $this->assertEquals(
      'パスワードを入力してください',
      session('errors')->first('password')
    );
  }

  /** @test */
  public function 登録内容と一致しない場合_バリデーションメッセージが表示される()
  {
    // 1. 正しい管理者ユーザーを登録
    $admin = Admin::factory()->create([
      'email' => 'admin@example.com',
      'password' => bcrypt('password123'),
    ]);

    // 2. 誤ったメールアドレスを入力してログイン処理
    $response = $this->from('/admin/login')->post('/admin/login', [
      'email' => 'wrong@example.com', // 存在しない
      'password' => 'password123',
    ]);

    // 3. 元のログイン画面にリダイレクトされること
    $response->assertRedirect('/admin/login');

    // 4. email にバリデーションエラーが存在すること
    $response->assertSessionHasErrors(['email']);

    // 5. カスタムメッセージの確認
    $this->assertEquals(
      'ログイン情報が登録されていません',
      session('errors')->first('email')
    );
  }
}
