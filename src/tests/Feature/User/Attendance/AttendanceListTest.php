<?php

namespace Tests\Feature\UserAttendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;

class AttendanceListTest extends TestCase
{
  use RefreshDatabase;

  /** @test */
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

  /** @test */
  public function 休憩時刻が勤怠一覧画面で確認できる()
  {
    // 1) 現在時刻固定（JST）
    $inAt     = Carbon::create(2025, 9, 22, 9,  0, 0, 'Asia/Tokyo');
    $breakIn  = Carbon::create(2025, 9, 22, 12, 3, 0, 'Asia/Tokyo');
    $breakOut = Carbon::create(2025, 9, 22, 12, 18, 0, 'Asia/Tokyo');

    // 2) 勤務中のユーザーにする（出勤）
    Carbon::setTestNow($inAt);
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 3) 休憩入 → 休憩戻（サーバ側で now() 記録する前提なので setTestNow で時刻を進める）
    Carbon::setTestNow($breakIn);
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    Carbon::setTestNow($breakOut);
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    // 4) 勤怠一覧で「休憩の記録」が見えること（時刻 or 合計）
    $res = $this->get('/attendance/list');
    $res->assertStatus(200);
    $html = $res->getContent();

    // a) 開始/終了の時刻そのものを表示するUI
    $timesAppear = $this->timeAppears($html, $breakIn) && $this->timeAppears($html, $breakOut);

    // b) 合計だけを表示するUI（例: 15分 / 0:15 / 00:15 / 0時間15分 / 15m / 15 min）
    $durationAppear = $this->breakDurationAppears($html, $breakIn, $breakOut);

    $this->assertTrue(
      $timesAppear || $durationAppear,
      '勤怠一覧に休憩の開始/終了時刻（例 ' . $breakIn->format('H:i') . ' / ' . $breakOut->format('H:i') . '）'
        . 'または休憩合計（' . $breakIn->diffInMinutes($breakOut) . '分 相当）が見つかりませんでした。'
    );

    // （任意）当日の日付も出ているか軽く確認（表記ゆれ許容）
    $year = (int) $inAt->format('Y');
    $mon = (int) $inAt->format('n');
    $day = (int) $inAt->format('j');
    $datePatterns = [
      '/\b' . $year . '[-\/\.]0?' . $mon . '[-\/\.]0?' . $day . '\b/u',
      '/' . $year . '\s*年\s*0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',
      '/\b0?' . $mon . '\/0?' . $day . '(?:\s*\(.+?\))?\b/u',
      '/0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',
      '/' . $year . '\s*年\s*0?' . $mon . '\s*月/u',
    ];
    $foundDate = false;
    foreach ($datePatterns as $re) {
      if (preg_match($re, $html)) {
        $foundDate = true;
        break;
      }
    }
    $this->assertTrue($foundDate, '勤怠一覧に当日の日付が表示されていません。');
  }
}