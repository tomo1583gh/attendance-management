<?php

namespace Tests\Feature\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailDateTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 勤怠詳細画面の「日付」が選択した日付になっている(): void
  {
    // Arrange
    $user = User::factory()->create(['name' => '日付確認ユーザー']);
    $date = Carbon::create(2025, 5, 10);
    $attendance = Attendance::factory()->for($user)->create([
      'work_date' => $date->copy()->startOfDay(),
    ]);
    $this->actingAs($user);

    // Act: 一覧→（当日リンク）→詳細 という実動線に合わせる
    $list = $this->get('/attendance/list?month=' . $date->format('Y-m'));
    $list->assertOk();
    $htmlList = $list->getContent();

    // 当日の「詳細」リンクを抽出（勤怠あり: /attendance/detail/{id}、勤怠なし: /attendance/detail/0?date=YYYY-MM-DD）
    $detailUrl = null;

    // (A) idリンク（絶対/相対両対応）
    $idPath = parse_url(route('attendance.detail', $attendance->id), PHP_URL_PATH) ?? '/attendance/detail/' . $attendance->id;
    if (preg_match('#href="([^"]*' . preg_quote($idPath, '#') . '(?:\?[^"]*)?)"#u', $htmlList, $m)) {
      $detailUrl = $m[1];
    }

    // (B) フォールバック: ?date=YYYY-MM-DD 形
    if (!$detailUrl) {
      $iso = $date->toDateString();
      if (preg_match('#href="([^"]*?/attendance/detail/0\?date=' . preg_quote($iso, '#') . ')"#u', $htmlList, $m)) {
        $detailUrl = $m[1];
      }
    }

    $this->assertNotNull($detailUrl, '一覧に当日の「詳細」リンクが見つかりませんでした。');

    // 取得したURLへ遷移
    $parsed = parse_url($detailUrl);
    $toGet  = ($parsed['path'] ?? $detailUrl) . (isset($parsed['query']) ? ('?' . $parsed['query']) : '');
    $detail = $this->get($toGet);
    $detail->assertOk();

    $html = $detail->getContent();
    @file_put_contents(storage_path('logs/test_attendance_detail.html'), $html); // 失敗時調査用

    // Assert: 画面内に「選択日」が埋め込まれていることを多表記で許容して検証
    $Y  = $date->format('Y');       // 2025
    $m2 = $date->format('m');       // 05
    $d2 = $date->format('d');       // 10
    $m  = (int) $date->format('n'); // 5
    $d  = (int) $date->format('j'); // 10
    $iso = $date->toDateString();   // 2025-05-10

    // 代表的な埋め込み先（input/hidden、timeタグ、data属性、URLクエリ、プレーンテキスト 等）
    $patterns = [
      // input系（nameにdateが含まれる／含まれなくてもvalue一致で拾う）
      '/<input[^>]*name="[^"]*date[^"]*"[^>]*value="' . preg_quote($iso, '/') . '"[^>]*>/iu',
      '/<input[^>]*value="' . preg_quote($iso, '/') . '"[^>]*name="[^"]*date[^"]*"[^>]*>/iu',
      '/<input[^>]*value="' . preg_quote($iso, '/') . '"[^>]*>/iu', // name不問（valueだけでヒット）

      // timeタグ
      '/<time[^>]*datetime="' . preg_quote($iso, '/') . '"[^>]*>/iu',
      '/<time[^>]*>[^<]*(?:' . $Y . '[\/\-]' . $m2 . '[\/\-]' . $d2 . '|0?' . $m . '\/0?' . $d . ')[^<]*<\/time>/u',

      // data属性
      '/data-(?:date|work[-_]date|selected[-_]date)="' . preg_quote($iso, '/') . '"/iu',

      // URLクエリ（form action/href 内）
      '/[?&]date=' . preg_quote($iso, '/') . '(?:[&#"]|$)/u',
      '/&amp;date=' . preg_quote($iso, '/') . '(?:[&#"]|$)/u',

      // ISOに時刻が付くパターン（T or スペース区切り）
      '/' . preg_quote($iso, '/') . '(?:[ T]\d{2}:\d{2}(?::\d{2})?)?/u',

      // 日本語表記／スラッシュ表記／月日表記（曜日併記許容）
      '/' . $Y . '[\/\-]' . $m2 . '[\/\-]' . $d2 . '/u',                                   // 2025/05/10 or 2025-05-10
      '/' . $Y . '年\s*0?' . $m . '月\s*0?' . $d . '日(?:[（(][^)）]*[）)])?/u',           // 2025年5月10日（曜）
      '/' . $Y . '\/0?' . $m . '\/0?' . $d . '(?:[（(][^)）]*[）)])?/u',                   // 2025/5/10（曜）
      '/0?' . $m . '\/0?' . $d . '(?:[（(][^)）]*[）)])?/u',                               // 05/10（曜）/ 5/10（曜）

      // 「日付」ラベル近接（保険）
      '/日付.{0,200}' . $Y . '[\/\-]' . $m2 . '[\/\-]' . $d2 . '/su',

      // メタタグ（任意で実装している場合）
      '/<meta[^>]*name="attendance[-_]date"[^>]*content="' . preg_quote($iso, '/') . '"[^>]*>/iu',
    ];

    $matched = false;
    foreach ($patterns as $p) {
      if (preg_match($p, $html)) {
        $matched = true;
        break;
      }
    }

    $this->assertTrue(
      $matched,
      "勤怠詳細画面に選択した日付が確認できませんでした。\n" .
        "想定日: {$iso}（多表記・属性で許容）\n" .
        "HTMLダンプ: storage/logs/test_attendance_detail.html を確認してください。"
    );
  }
}
