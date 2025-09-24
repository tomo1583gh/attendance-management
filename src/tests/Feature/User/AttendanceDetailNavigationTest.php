<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailNavigationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 「詳細」を押下すると、その日の勤怠詳細画面に遷移する(): void
  {
    // 1. 勤怠情報が登録されたユーザーにログインをする
    $user = User::factory()->create();
    $date = Carbon::create(2025, 5, 10);
    $attendance = Attendance::factory()->for($user)->create([
      'work_date' => $date->copy()->startOfDay(),
    ]);
    $this->actingAs($user);

    // 2. 勤怠一覧ページを開く（対象月を指定してブレを排除）
    $response = $this->get('/attendance/list?month=' . $date->format('Y-m'));
    $response->assertOk();
    $html = $response->getContent();

    // 3. 「詳細」ボタンを押下する相当：当日のリンクを抽出
    // 仕様：
    //  - 勤怠あり  => /attendance/detail/{id}
    //  - 勤怠なし  => /attendance/detail/0?date=YYYY-MM-DD
    // 今回は勤怠ありなので {id} のリンクがあるはず（無ければ date クエリでフォールバック）
    $detailUrl = null;

    // (A) id リンク（絶対/相対両対応）を探す
    $idPath = parse_url(route('attendance.detail', $attendance->id), PHP_URL_PATH) ?? '/attendance/detail/' . $attendance->id;
    if (preg_match('#href="([^"]*' . preg_quote($idPath, '#') . '(?:\?[^"]*)?)"#u', $html, $m)) {
      $detailUrl = $m[1];
    }

    // (B) 見つからなければ date クエリ形を探す（絶対/相対両対応）
    if (!$detailUrl) {
      $iso = $date->toDateString(); // YYYY-MM-DD
      if (preg_match('#href="([^"]*?/attendance/detail/0\?date=' . preg_quote($iso, '#') . ')"#u', $html, $m)) {
        $detailUrl = $m[1];
      }
    }

    // 見つからない場合はHTMLを保存して失敗
    if (!$detailUrl) {
      @file_put_contents(storage_path('logs/test_attendance_list.html'), $html);
      $this->fail("一覧に当日の「詳細」リンクが見つかりませんでした。\nHTML: storage/logs/test_attendance_list.html を確認してください。");
    }

    // 期待されるURL（相対パス基準）を算出
    $expectedIdPath   = $idPath;                                   // /attendance/detail/{id}
    $expectedDatePath = '/attendance/detail/0?date=' . $date->toDateString();

    // クリック相当で遷移
    $parsed = parse_url($detailUrl);
    $toGet  = ($parsed['path'] ?? $detailUrl) . (isset($parsed['query']) ? ('?' . $parsed['query']) : '');
    $detailResponse = $this->get($toGet);
    $detailResponse->assertOk(); // その日の勤怠詳細画面が開ける

    // 「その日の勤怠詳細画面に遷移する」= 当日の詳細URLであることを確認（id 形 or date 形のいずれか）
    $this->assertTrue(
      $toGet === $expectedIdPath || $toGet === $expectedDatePath,
      "遷移先URLが想定外です: {$toGet}\n想定: {$expectedIdPath} または {$expectedDatePath}"
    );

    // 参考：ページ種別の簡易確認（見出しなど。必要に応じて残す/外す）
    $this->assertStringContainsString('勤怠詳細', $detailResponse->getContent());
  }
}
