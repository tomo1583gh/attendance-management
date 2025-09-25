<?php

namespace Tests\Feature\User\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class ClockInTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 出勤ボタンが正しく機能する()
  {
    // 1) 現在時刻を固定（JST）
    $now = Carbon::create(2025, 9, 22, 9, 00, 0, 'Asia/Tokyo');
    Carbon::setTestNow($now);

    // 2) 勤務外ユーザーでログイン（当日の打刻なし）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 打刻画面にアクセスして「出勤」ボタンがあることを確認
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // ボタン存在をHTMLで厳密に確認（部分一致で「出勤中」に誤ヒットしないように）
    $response->assertSee('<button type="submit" class="btn btn--primary">出勤</button>', false);
    $response->assertSee('action="' . route('attendance.clockIn') . '"', false);

    // 3) 出勤の処理を実行（画面から押すのと同じPOST）
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 再度画面確認：ステータスが「出勤中」になっていること
    $after = $this->get('/attendance');
    $after->assertStatus(200);

    // Bladeの @case('working') は「出勤中」を出力
    $after->assertSee('出勤中');

    // 出勤中UI：退勤・休憩入が表示、出勤ボタンは消えている
    $after->assertSee('退勤');
    $after->assertSee('休憩入');
    $after->assertDontSee('<button type="submit" class="btn btn--primary">出勤</button>', false);
  }

  #[Test]
  public function 出勤は一日一回のみできる()
  {
    // 1) 時刻を固定（JST）
    $start = Carbon::create(2025, 9, 22, 9, 0, 0, 'Asia/Tokyo');
    Carbon::setTestNow($start);

    // 2) ログイン（当日の打刻なし＝勤務外）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 出勤 → 退勤（実フローをHTTPで踏む）
    $this->from('/attendance')->post(route('attendance.clockIn'))->assertStatus(302);

    Carbon::setTestNow($start->copy()->addHours(8)); // 17:00 に進めて退勤
    $this->from('/attendance')->post(route('attendance.clockOut'))->assertStatus(302);

    // 4) 退勤後の画面確認
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // ステータスは「退勤済」
    $response->assertSee('退勤済');
    $response->assertSee('本日の業務は終了です。');

    // 5) 出勤ボタンが表示されない（“一日一回のみ”のUI保証）
    // - 文言だけでなく、ボタンHTMLとフォームactionも存在しないことを厳密に確認
    $response->assertDontSee('<button type="submit" class="btn btn--primary">出勤</button>', false);
    $response->assertDontSee('action="' . route('attendance.clockIn') . '"', false);

    // 退勤の文言ではなく、ボタン/フォームの不在でチェック
    $response->assertDontSee('<button type="submit" class="btn btn--pyimary">退勤</button>', false);
    $response->assertDontSee('action="' . route('attendance.clockOut') . '"', false);

    // 休憩ボタンもフォームactionで不在確認（文言での部分一致を避ける）
    $response->assertDontSee('action="' . route('attendance.breakIn') . '"', false);
    $response->assertDontSee('action="' . route('attendance.breakOut') . '"', false);
  }

  #[Test]
  public function 出勤時刻が勤怠一覧画面で確認できる()
  {
    // 1) 現在時刻を固定（JST）— 一覧の表示時刻とズレないよう秒は 00 に
    $now = Carbon::create(2025, 9, 22, 9, 34, 0, 'Asia/Tokyo');
    Carbon::setTestNow($now);

    // 2) 勤務外ユーザーでログイン（当日の打刻なし）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 出勤（画面の「出勤」押下と等価のPOST）
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // --- 休憩開始（現在時刻を 30 分進めてから POST）---
    $breakIn = $now->copy()->addMinutes(30);
    Carbon::setTestNow($breakIn);
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // --- 休憩終了（さらに 15 分進めてから POST）---
    $breakOut = $breakIn->copy()->addMinutes(15);
    Carbon::setTestNow($breakOut);
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    // 4) 勤怠一覧画面にアクセス
    $res = $this->get('/attendance/list');
    $res->assertStatus(200);

    // 5) 出勤時刻（分まで）を確認（例: 09:34）
    $expectedTime = $now->format('H:i');
    $res->assertSee($expectedTime);

    // HTML 全文を取得
    $html = $res->getContent();

    // （休憩合計の表示を検証）
    $this->assertTrue(
      $this->breakDurationAppears($html, $breakIn, $breakOut),
      '勤怠一覧に休憩合計（' . $breakIn->diffInMinutes($breakOut) . '分 相当）が見つかりませんでした。'
    );

    // 6) 当日の日付が表示されていることも確認（表記ゆれを幅広く許容）
    $year = (int) $now->format('Y');
    $mon  = (int) $now->format('n');  // 1桁月も許容
    $day  = (int) $now->format('j');  // 1桁日も許容

    $datePatterns = [
      // 2025-09-22 / 2025/9/22 / 2025.09.22
      '/\b' . $year . '[-\/\.]0?' . $mon . '[-\/\.]0?' . $day . '\b/u',

      // 2025年9月22日（曜日付き "(月)" など任意）
      '/' . $year . '\s*年\s*0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',

      // 9/22（曜日付き任意）
      '/\b0?' . $mon . '\/0?' . $day . '(?:\s*\(.+?\))?\b/u',

      // 9月22日（曜日付き任意）
      '/0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',

      // 月ヘッダだけ表示のUI（例: 2025年9月）
      '/' . $year . '\s*年\s*0?' . $mon . '\s*月/u',
    ];

    $found = false;
    foreach ($datePatterns as $re) {
      if (preg_match($re, $html)) {
        $found = true;
        break;
      }
    }
    $this->assertTrue($found, '勤怠一覧に当日の日付が表示されていません。');
  }

  /**
   * 一覧の時刻表記ゆれ（H:i / H:i:s / H時m分 / H:m 等）を許容してマッチ確認
   */
  private function timeAppears(string $html, \Carbon\Carbon $t): bool
  {
    $H   = $t->format('H');              // "12"
    $Hnp = (string)intval($H);           // "12" or "9"
    $i   = $t->format('i');              // "03"
    $inp = (string)intval($i);           // "3"

    $patterns = [
      // 12:03 / 12:03:00 / 9:03 / 9:3 など
      '/\b' . preg_quote($H, '/')   . ':' . preg_quote($i, '/')   . '(?::\d{2})?\b/u',
      '/\b' . preg_quote($Hnp, '/') . ':' . preg_quote($i, '/')   . '(?::\d{2})?\b/u',
      '/\b' . preg_quote($Hnp, '/') . ':' . preg_quote($inp, '/') . '(?::\d{2})?\b/u',

      // 12時03分 / 12時3分 / 9時03分 / 9時3分
      '/' . preg_quote($H,   '/') . '\s*時\s*' . preg_quote($i,   '/') . '\s*分/u',
      '/' . preg_quote($Hnp, '/') . '\s*時\s*' . preg_quote($i,   '/') . '\s*分/u',
      '/' . preg_quote($Hnp, '/') . '\s*時\s*' . preg_quote($inp, '/') . '\s*分/u',
    ];

    foreach ($patterns as $re) {
      if (preg_match($re, $html)) return true;
    }
    return false;
  }

  /**
   * 一覧の「休憩合計」表記（15分 / 0:15 / 00:15 / 0時間15分 / 15m / 15 min 等）を幅広く許容して検出
   */
  private function breakDurationAppears(string $html, \Carbon\Carbon $start, \Carbon\Carbon $end): bool
  {
    $minutes = $end->diffInMinutes($start); // 例: 15
    $hours   = intdiv($minutes, 60);        // 0
    $mins    = $minutes % 60;               // 15

    // "H:MM"（先頭ゼロあり/なし）を作る
    $hmmA = $hours . ':' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT);   // 0:15
    $hmmB = str_pad((string)$hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT); // 00:15

    $patterns = [
      // 15分 / １５分
      '/\b' . $minutes . '\s*分\b/u',
      // 0時間15分 / ０時間１５分
      '/\b' . $hours . '\s*時間\s*' . $mins . '\s*分\b/u',
      // 0:15 / 00:15
      '/\b' . preg_quote($hmmA, '/') . '\b/u',
      '/\b' . preg_quote($hmmB, '/') . '\b/u',
      // 15m / 15 min / 15mins / 15 minutes
      '/\b' . $minutes . '\s*m\b/i',
      '/\b' . $minutes . '\s*(min|mins|minute|minutes)\b/i',
    ];

    foreach ($patterns as $re) {
      if (preg_match($re, $html)) {
        return true;
      }
    }
    return false;
  }
}