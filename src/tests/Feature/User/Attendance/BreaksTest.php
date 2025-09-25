<?php

namespace Tests\Feature\User\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class BreaksTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 休憩ボタンが正しく機能する()
  {
    // 1) 現在時刻を固定（JST）
    $now = Carbon::create(2025, 9, 22, 12, 0, 0, 'Asia/Tokyo');
    Carbon::setTestNow($now);

    // 2) 出勤中ユーザーとしてログイン（当日未打刻 → 出勤）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 出勤（画面から押すのと同じPOST）
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 出勤中の画面で「休憩入」ボタンが表示されていること
    $response = $this->get('/attendance');
    $response->assertStatus(200);
    $response->assertSee('出勤中');
    // 生HTMLで厳密にボタン/フォームの存在を確認
    $response->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $response->assertSee('action="' . route('attendance.breakIn') . '"', false);

    // 3) 休憩入の処理を実行
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // 休憩中の画面を確認
    $after = $this->get('/attendance');
    $after->assertStatus(200);
    $after->assertSee('休憩中');
    // 「休憩戻」ボタンが表示され、「休憩入」「退勤」は表示されない
    $after->assertSee('<button type="submit" class="btn btn--ghost">休憩戻</button>', false);
    $after->assertSee('action="' . route('attendance.breakOut') . '"', false);

    $after->assertDontSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $after->assertDontSee('action="' . route('attendance.breakIn') . '"', false);
    $after->assertDontSee('<button type="submit" class="btn btn--primary">退勤</button>', false);
    $after->assertDontSee('action="' . route('attendance.clockOut') . '"', false);
  }

  #[Test]
  public function 休憩は一日に何回でもできる()
  {
    // 1) 現在時刻を固定（JST）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 12, 0, 0, 'Asia/Tokyo'));

    // 2) 出勤中ユーザーとしてログイン（当日未打刻 → 出勤）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 出勤
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 出勤中画面に「休憩入」ボタンがあること（生HTMLで厳密確認）
    $page = $this->get('/attendance');
    $page->assertStatus(200);
    $page->assertSee('出勤中');
    $page->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $page->assertSee('action="' . route('attendance.breakIn') . '"', false);

    // --- 1回目の休憩 ---
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    $break1 = $this->get('/attendance');
    $break1->assertStatus(200);
    $break1->assertSee('休憩中');
    $break1->assertSee('<button type="submit" class="btn btn--ghost">休憩戻</button>', false);
    $break1->assertSee('action="' . route('attendance.breakOut') . '"', false);
    $break1->assertDontSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);

    // 少し時間を進めて休憩戻
    Carbon::setTestNow(Carbon::now('Asia/Tokyo')->addMinutes(10));
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    $work1 = $this->get('/attendance');
    $work1->assertStatus(200);
    $work1->assertSee('出勤中');
    $work1->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $work1->assertSee('action="' . route('attendance.breakIn') . '"', false);

    // --- 2回目の休憩（「何回でも」の検証）---
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    $break2 = $this->get('/attendance');
    $break2->assertStatus(200);
    $break2->assertSee('休憩中');

    Carbon::setTestNow(Carbon::now('Asia/Tokyo')->addMinutes(5));
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    // 最終確認：再度「休憩入」ボタンが表示される（＝同日に複数回可能）
    $final = $this->get('/attendance');
    $final->assertStatus(200);
    $final->assertSee('出勤中');
    $final->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $final->assertSee('action="' . route('attendance.breakIn') . '"', false);
  }

  #[Test]
  public function 休憩戻ボタンが正しく機能する()
  {
    // 1) 現在時刻を固定（JST）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 12, 0, 0, 'Asia/Tokyo'));

    // 2) 出勤中ユーザーとしてログイン（当日未打刻 → 出勤）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 出勤
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 休憩入
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // 休憩中画面の確認：休憩戻ボタンが出ている
    $page = $this->get('/attendance');
    $page->assertStatus(200);
    $page->assertSee('休憩中');
    $page->assertSee('<button type="submit" class="btn btn--ghost">休憩戻</button>', false);
    $page->assertSee('action="' . route('attendance.breakOut') . '"', false);
    $page->assertDontSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);

    // 少し時間を進めて 休憩戻
    Carbon::setTestNow(Carbon::now('Asia/Tokyo')->addMinute());
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    // 休憩戻後の画面：ステータスが「出勤中」に戻り、休憩入ボタンが再表示
    $after = $this->get('/attendance');
    $after->assertStatus(200);
    $after->assertSee('出勤中');
    $after->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $after->assertSee('action="' . route('attendance.breakIn') . '"', false);

    // 「休憩戻」のフォームは表示されない
    $after->assertDontSee('action="' . route('attendance.breakOut') . '"', false);
  }

  #[Test]
  public function 休憩戻は一日に何回でもできる()
  {
    // 1) 現在時刻を固定（JST）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 12, 0, 0, 'Asia/Tokyo'));

    // 2) 出勤中ユーザーとしてログイン → 出勤
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // --- 1回目の休憩入 ---
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // 休憩中で「休憩戻」ボタンが見えること
    $page1 = $this->get('/attendance');
    $page1->assertStatus(200);
    $page1->assertSee('休憩中');
    $page1->assertSee('<button type="submit" class="btn btn--ghost">休憩戻</button>', false);
    $page1->assertSee('action="' . route('attendance.breakOut') . '"', false);

    // --- 1回目の休憩戻 ---
    Carbon::setTestNow(Carbon::now('Asia/Tokyo')->addMinutes(5));
    $this->from('/attendance')
      ->post(route('attendance.breakOut'))
      ->assertStatus(302);

    // 出勤中に戻り、再度「休憩入」可能
    $work = $this->get('/attendance');
    $work->assertStatus(200);
    $work->assertSee('出勤中');
    $work->assertSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $work->assertSee('action="' . route('attendance.breakIn') . '"', false);

    // --- 2回目の休憩入（同日に再度休憩できることの検証）---
    Carbon::setTestNow(Carbon::now('Asia/Tokyo')->addMinutes(1));
    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // 3) 確認：「休憩戻」ボタンが再び表示される（＝一日に何回でもできる）
    $page2 = $this->get('/attendance');
    $page2->assertStatus(200);
    $page2->assertSee('休憩中');
    $page2->assertSee('<button type="submit" class="btn btn--ghost">休憩戻</button>', false);
    $page2->assertSee('action="' . route('attendance.breakOut') . '"', false);

    // 休憩中なので「休憩入」「退勤」は表示されない
    $page2->assertDontSee('<button type="submit" class="btn btn--ghost">休憩入</button>', false);
    $page2->assertDontSee('action="' . route('attendance.breakIn') . '"', false);
    $page2->assertDontSee('<button type="submit" class="btn btn--primary">退勤</button>', false);
    $page2->assertDontSee('action="' . route('attendance.clockOut') . '"', false);
  }

  #[Test]
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
