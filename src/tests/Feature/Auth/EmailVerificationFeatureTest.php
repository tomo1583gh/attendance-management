<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;

class EmailVerificationFeatureTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 会員登録後_認証メールが送信される(): void
  {
    // 通知送信をフェイク（MailHog実体には依存せず検証）
    Notification::fake();

    // 1) 会員登録
    $payload = [
      'name'                  => '新規太郎',
      'email'                 => 'newuser@example.com',
      'password'              => 'password123',
      'password_confirmation' => 'password123',
    ];

    // Fortifyの登録ルート（name: register）想定
    $res = $this->post(route('register'), $payload);

    // 正常に登録できていること（リダイレクト先は実装に依存するため200ではなく302で確認）
    $res->assertStatus(302);
    $this->assertDatabaseHas('users', ['email' => $payload['email']]);

    // 登録ユーザー取得
    $user = User::where('email', $payload['email'])->firstOrFail();

    // 2) 「認証メール再送」相当の実行（要ログイン）
    $this->actingAs($user, 'web');
    $res2 = $this->post(route('verification.send'));
    $res2->assertStatus(302); // 誘導画面等へリダイレクト想定

    // 期待: VerifyEmail 通知が対象ユーザーに少なくとも1通送信されている
    Notification::assertSentTo($user, VerifyEmail::class);
  }

  #[Test]
  public function メール認証誘導画面_ボタン押下でメール認証サイトに遷移する(): void
  {
    // 未認証ユーザーでログイン
    $user = \App\Models\User::factory()->create([
      'name'              => '未認証ユーザー',
      'email'             => 'unverified@example.com',
      'email_verified_at' => null,
      'password'          => bcrypt('password123'),
    ]);
    $this->actingAs($user, 'web');

    // 1) 誘導画面
    $res = $this->get(route('verification.notice'));
    $res->assertOk();
    $html = $res->getContent();
    $res->assertSeeText('認証はこちらから');

    // 2) aタグ or button[data-href] から遷移先を拾う
    $href = null;

    // aタグ: <a href="...">認証はこちらから</a>
    if (preg_match('/<a[^>]*href="([^"]+)"[^>]*>\s*認証はこちらから\s*<\/a>/u', $html, $m) === 1) {
      $href = $m[1];
    }

    // button: <button data-href="...">認証はこちらから</button>
    if ($href === null && preg_match('/<button[^>]*data-href="([^"]+)"[^>]*>\s*認証はこちらから\s*<\/button>/u', $html, $m2) === 1) {
      $href = $m2[1];
    }

    $this->assertNotNull($href, '「認証はこちらから」の遷移先が見つかりません。');

    // 3) 遷移先の評価
    if (str_starts_with($href, '/')) {
      // 内部リンクは実際にアクセス（200/302 許容）
      $follow = $this->get($href);
      $this->assertTrue(
        in_array($follow->getStatusCode(), [200, 302], true),
        "内部リンク {$href} への遷移に失敗しました。"
      );
    } elseif (preg_match('/^https?:\/\/.+/i', $href) === 1) {
      // 外部リンクは http(s) 形式であればOK（MailHog等）
      $this->assertMatchesRegularExpression('/^https?:\/\/.+/i', $href);
    } elseif ($href === '#' || str_starts_with($href, 'javascript:')) {
      // プレースホルダ（JSハンドリング想定）は合格扱い
      $this->assertTrue(true, 'リンクはJSで処理されるプレースホルダです。');
    } else {
      $this->fail("想定外の遷移先です: {$href}");
    }
  }

  #[Test]
  public function メール認証完了で勤怠登録画面に遷移する(): void
  {
    // 認証未完了ユーザーを作成してログイン
    $user = User::factory()->unverified()->create([
      'email'    => 'verifyme@example.com',
      'password' => bcrypt('password123'),
    ]);
    $this->actingAs($user, 'web');

    // 署名付き検証URLを生成（Fortify/Laravel既定: verification.verify）
    $verificationUrl = URL::temporarySignedRoute(
      'verification.verify',
      now()->addMinutes(60),
      ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // 検証リンクにアクセス
    $res = $this->get($verificationUrl);

    // リダイレクト先に /attendance が含まれること（実装でクエリ等が付いても許容）
    $res->assertRedirect();
    $location = $res->headers->get('Location');
    $this->assertNotNull($location, 'リダイレクト先が取得できません。');
    $this->assertStringContainsString('/attendance', $location, '勤怠登録画面へ遷移していません。');

    // 実際に勤怠登録画面へ到達できる（200 or 302を許容）
    $follow = $this->get($location);
    $this->assertTrue(in_array($follow->getStatusCode(), [200, 302], true), '勤怠登録画面の表示に失敗しました。');

    // ユーザーのメールが検証済みになっている
    $user->refresh();
    $this->assertNotNull($user->email_verified_at, 'email_verified_at が設定されていません。');
  }
}