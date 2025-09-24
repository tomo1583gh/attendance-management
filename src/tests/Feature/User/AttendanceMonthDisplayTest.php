<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceMonthDisplayTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 勤怠一覧画面に遷移した際に現在の月が表示される(): void
  {
    // Arrange
    Carbon::setTestNow(Carbon::create(2025, 5, 12, 9, 0, 0)); // 現在日付を固定
    $user = User::factory()->create();
    $this->actingAs($user);

    // Act
    $response = $this->get('/attendance/list');

    // Assert
    $response->assertOk();

    // 画面では "YYYY/MM"（例: 2025/05）想定。念のため "YYYY-MM" も許容。
    $expectedSlash  = Carbon::now()->format('Y/m'); // 2025/05
    $expectedHyphen = Carbon::now()->format('Y-m'); // 2025-05

    $html = $response->getContent();
    $this->assertTrue(
      str_contains($html, $expectedSlash) || str_contains($html, $expectedHyphen),
      "現在の月（{$expectedSlash} または {$expectedHyphen}）が表示されていません。"
    );

    Carbon::setTestNow(); // 後片付け
  }

  #[Test]
  public function 「前月」を押下した時に表示月の前月の情報が表示される(): void
  {
    // 現在日付を固定（例：2025-05-12）
    Carbon::setTestNow(Carbon::create(2025, 5, 12, 9, 0, 0));

    $user = User::factory()->create();
    $this->actingAs($user);

    // 「前月」相当のURLにアクセス（?month=YYYY-MM）
    $prev = Carbon::now()->subMonth(); // 2025-04
    $response = $this->get('/attendance/list?month=' . $prev->format('Y-m'));
    $response->assertOk();

    // 画面の表示は "YYYY/MM" 想定。念のため "YYYY-MM" も許容。
    $expectedSlash  = $prev->format('Y/m'); // 2025/04
    $expectedHyphen = $prev->format('Y-m'); // 2025-04

    $html = $response->getContent();
    $this->assertTrue(
      str_contains($html, $expectedSlash) || str_contains($html, $expectedHyphen),
      "前月の情報（{$expectedSlash} または {$expectedHyphen}）が表示されていません。"
    );

    Carbon::setTestNow(); // 後片付け
  }

  #[Test]
  public function 「翌月」を押下した時に表示月の翌月の情報が表示される(): void
  {
    // 現在日付を固定（例：2025-05-12）
    Carbon::setTestNow(Carbon::create(2025, 5, 12, 9, 0, 0));

    // 1. 勤怠情報が登録されたユーザーにログインをする（登録有無は本テストでは不問）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 2. 勤怠一覧ページを開く → 3. 「翌月」ボタンを押す相当（?month=YYYY-MM を翌月にしてアクセス）
    $next = Carbon::now()->addMonth(); // 2025-06
    $response = $this->get('/attendance/list?month=' . $next->format('Y-m'));
    $response->assertOk();

    // 期待表示は "YYYY/MM" 想定。念のため "YYYY-MM" も許容。
    $expectedSlash  = $next->format('Y/m'); // 2025/06
    $expectedHyphen = $next->format('Y-m'); // 2025-06

    $html = $response->getContent();
    $this->assertTrue(
      str_contains($html, $expectedSlash) || str_contains($html, $expectedHyphen),
      "翌月の情報（{$expectedSlash} または {$expectedHyphen}）が表示されていません。"
    );

    Carbon::setTestNow(); // 後片付け
  }
}
