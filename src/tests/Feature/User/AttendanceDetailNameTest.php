<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailNameTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 勤怠詳細画面の「名前」がログインユーザーの氏名になっている(): void
  {
    // 1. 勤怠情報が登録されたユーザーにログインをする
    $user = User::factory()->create([
      'name'  => '表示名テストユーザー',
      'email' => 'nametest@example.com',
    ]);
    $attendance = Attendance::factory()->for($user)->create([
      'work_date' => Carbon::create(2025, 5, 10)->startOfDay(),
    ]);
    $this->actingAs($user);

    // 2. 勤怠詳細ページを開く
    $response = $this->get(route('attendance.detail', $attendance->id));
    $response->assertOk();
    $html = $response->getContent();

    // 3. 名前欄を確認する → 「名前」ラベルとユーザー名が表示されていること
    $this->assertStringContainsString('名前', $html, '勤怠詳細画面に「名前」ラベルが表示されていません。');
    $this->assertStringContainsString($user->name, $html, '勤怠詳細画面にログインユーザー名が表示されていません。');

    // 「名前 … ユーザー名」が同一ブロック内に存在すること（緩めの近接チェック）
    $pattern = '/名前.{0,200}' . preg_quote($user->name, '/') . '/su';
    $this->assertMatchesRegularExpression(
      $pattern,
      $html,
      '「名前」欄にログインユーザー名が表示されていません（近接不一致）。'
    );
  }
}
