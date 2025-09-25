<?php

namespace Tests\Feature\UserAttendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Attendance;
use PHPUnit\Framework\Attributes\Test;

class AttendanceListTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 自分が行った勤怠情報が全て表示されている(): void
  {
    // Arrange
    $me = User::factory()->create(['name' => '山田太郎', 'email' => 'taro@example.com']);

    // 2025-05 の 10, 11, 12 に勤怠を作成
    $base = Carbon::create(2025, 5, 10);
    $mine = Attendance::factory()->count(3)->for($me)->sequence(
      ['work_date' => $base->copy()->day(10)],
      ['work_date' => $base->copy()->day(11)],
      ['work_date' => $base->copy()->day(12)],
    )->create();

    // 現在月依存を避けるため現在日時を 2025-05-12 に固定（なくてもOKだが安定のため）
    Carbon::setTestNow($base->copy()->day(12));

    // ログイン
    $this->actingAs($me);

    // Act: 対象月を指定して一覧へ
    $monthParam = $base->format('Y-m'); // "2025-05"
    $response = $this->get("/attendance/list?month={$monthParam}");
    $response->assertOk();
    $html = $response->getContent();

    // Debug（失敗時に中身確認したい場合）
    @file_put_contents(storage_path('logs/test_attendance_list.html'), $html);

    // Assert:
    // 勤怠がある日は `/attendance/detail/{id}` のリンクが出る仕様 → そのリンクの存在で確認
    foreach ($mine as $a) {
      $abs = route('attendance.detail', $a->id);           // 例: http://localhost/attendance/detail/3
      $rel = parse_url($abs, PHP_URL_PATH) ?? '';          // 例: /attendance/detail/3

      $this->assertTrue(
        str_contains($html, $abs) || str_contains($html, $rel),
        "自分の勤怠詳細リンクが見つかりません: {$rel}（または {$abs}）\n" .
          "HTML: storage/logs/test_attendance_list.html を確認してください。"
      );
    }

    // 後片付け
    Carbon::setTestNow();
  }

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