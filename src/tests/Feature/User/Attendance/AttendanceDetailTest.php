<?php

namespace Tests\Feature\User\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceDetailTest extends TestCase
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

    // 年と月日の間に 空白 / 改行 / <br> / タグ切り替え を許容
    $between = '(?:\s|<br\s*\/?>|<\/[^>]+>\s*<[^>]+>)*';

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
      '/' . $Y . '[\/\-]' . $m2 . '[\/\-]' . $d2 . '/u',                                   
      '/' . $Y . '年' . $between . '0?' . $m . '月' . $between . '0?' . $d . '日(?:[（(][^)）]*[）)])?/su',
      '/' . $Y . '\/0?' . $m . '\/0?' . $d . '(?:[（(][^)）]*[）)])?/u',                   
      '/0?' . $m . '\/0?' . $d . '(?:[（(][^)）]*[）)])?/u',                               

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

  #[Test]
  public function 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している(): void
  {
    // 1) ユーザー & 勤怠（出勤/退勤）を用意
    $user = User::factory()->create(['name' => '打刻確認ユーザー']);
    $date = Carbon::create(2025, 5, 10);
    $clockIn  = $date->copy()->setTime(9, 0, 0);   // 09:00
    $clockOut = $date->copy()->setTime(18, 0, 0);  // 18:00

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $clockIn,
      'clock_out_at' => $clockOut,
    ]);

    $this->actingAs($user);

    // 2) 勤怠詳細ページを開く
    $response = $this->get(route('attendance.detail', $attendance->id));
    $response->assertOk();
    $html = $response->getContent();

    // 3) 出勤・退勤欄を確認（編集可/不可の両方に対応）
    $in  = $clockIn->format('H:i');   // "09:00"
    $out = $clockOut->format('H:i');  // "18:00"

    // A) 編集可: <input name="clock_in" value="09:00"> / <input name="clock_out" value="18:00">
    $hasInputs =
      preg_match('/<input[^>]*name="clock_in"[^>]*value="' . preg_quote($in, '/')  . '"[^>]*>/u', $html) === 1
      && preg_match('/<input[^>]*name="clock_out"[^>]*value="' . preg_quote($out, '/') . '"[^>]*>/u', $html) === 1;

    // B) 編集不可: 「09:00 〜 18:00」テキスト（出勤・退勤ブロック内で近接確認）
    $hasPlain =
      preg_match('/出勤・退勤(.|\R){0,400}' . preg_quote($in, '/') . '(.|\R){0,50}〜(.|\R){0,50}' . preg_quote($out, '/') . '/u', $html) === 1;

    if (!($hasInputs || $hasPlain)) {
      @file_put_contents(storage_path('logs/test_attendance_detail_clock.html'), $html);
    }

    $this->assertTrue(
      $hasInputs || $hasPlain,
      "「出勤・退勤」の表示が打刻と一致しません。\n" .
        "期待: {$in} 〜 {$out}\n" .
        "HTMLダンプ: storage/logs/test_attendance_detail_clock.html を確認してください。"
    );
  }

  #[Test]
  public function 休憩にて記されている時間がログインユーザーの打刻と一致している(): void
  {
    // 1. 勤怠情報が登録されたユーザーにログインをする
    $user = User::factory()->create(['name' => '休憩確認ユーザー']);
    $date = Carbon::create(2025, 5, 10);

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0, 0),
    ]);

    // 休憩を2件作成（コントローラの並び順ロジックに合わせて order_no を付与）
    $attendance->breaks()->create([
      'order_no' => 1,
      'start_at' => $date->copy()->setTime(12, 0, 0),
      'end_at'   => $date->copy()->setTime(12, 30, 0),
    ]);
    $attendance->breaks()->create([
      'order_no' => 2,
      'start_at' => $date->copy()->setTime(15, 0, 0),
      'end_at'   => $date->copy()->setTime(15, 10, 0),
    ]);

    $this->actingAs($user);

    // 2. 勤怠詳細ページを開く
    $response = $this->get(route('attendance.detail', $attendance->id));
    $response->assertOk();
    $html = $response->getContent();

    // 3. 休憩欄を確認する（編集可: input の value / 編集不可: テキスト表示 の両対応）
    $pairs = [
      ['12:00', '12:30'],
      ['15:00', '15:10'],
    ];

    foreach ($pairs as [$start, $end]) {
      // 編集可パターン（input要素）
      $hasInputs =
        preg_match('/<input[^>]*name="breaks\[\d+\]\[start\]"[^>]*value="' . preg_quote($start, '/') . '"[^>]*>/u', $html) === 1
        && preg_match('/<input[^>]*name="breaks\[\d+\]\[end\]"[^>]*value="' . preg_quote($end, '/') . '"[^>]*>/u', $html) === 1;

      // 編集不可パターン（プレーンテキスト：「〜」の近接で確認）
      $hasPlain =
        preg_match('/休憩(?:\d+)?(?:.|\R){0,300}' . preg_quote($start, '/') . '(?:.|\R){0,40}〜(?:.|\R){0,40}' . preg_quote($end, '/') . '/u', $html) === 1;

      if (!($hasInputs || $hasPlain)) {
        @file_put_contents(storage_path('logs/test_attendance_detail_breaks.html'), $html);
      }

      $this->assertTrue(
        $hasInputs || $hasPlain,
        "「休憩」にて記されている時間が打刻と一致しません。期待: {$start} 〜 {$end}\n" .
          "HTMLダンプ: storage/logs/test_attendance_detail_breaks.html を確認してください。"
      );
    }
  }
}
