<?php

namespace Tests\Feature\User\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class ClockOutTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 退勤ボタンが正しく機能する()
  {
    // 1) 現在時刻を固定（JST）
    $start = Carbon::create(2025, 9, 22, 9, 0, 0, 'Asia/Tokyo');
    Carbon::setTestNow($start);

    // 2) 出勤中ユーザーとしてログイン（当日未打刻 → 出勤）
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 出勤中の画面で「退勤」ボタンが表示されていることを確認
    $page = $this->get('/attendance');
    $page->assertStatus(200);
    $page->assertSee('出勤中');
    // 生HTMLでボタン/フォームの存在を厳密確認（部分一致の誤検出を回避）
    $page->assertSee('<button type="submit" class="btn btn--primary">退勤</button>', false);
    $page->assertSee('action="' . route('attendance.clockOut') . '"', false);

    // 3) 退勤の処理を実行（時間を進めてからPOST）
    Carbon::setTestNow($start->copy()->addHours(8)); // 17:00 を想定
    $this->from('/attendance')
      ->post(route('attendance.clockOut'))
      ->assertStatus(302);

    // 退勤後の画面確認：ステータスが「退勤済」になっていること
    $after = $this->get('/attendance');
    $after->assertStatus(200);
    $after->assertSee('退勤済');
    $after->assertSee('本日の業務は終了です。');

    // 退勤ボタン/フォームは表示されない（文言の部分一致でなくHTMLをチェック）
    $after->assertDontSee('<button type="submit" class="btn btn--primary">退勤</button>', false);
    $after->assertDontSee('action="' . route('attendance.clockOut') . '"', false);

    // 出勤・休憩系のボタン/フォームも表示されない
    $after->assertDontSee('action="' . route('attendance.clockIn') . '"', false);
    $after->assertDontSee('action="' . route('attendance.breakIn') . '"', false);
    $after->assertDontSee('action="' . route('attendance.breakOut') . '"', false);
  }

  #[Test]
  public function 退勤時刻が勤怠一覧画面で確認できる()
  {
    // 1) 現在時刻固定（JST）
    $inAt  = Carbon::create(2025, 9, 22, 9, 34, 0, 'Asia/Tokyo');
    Carbon::setTestNow($inAt);

    // 2) 勤務外ユーザーでログイン → 出勤
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 3) 退勤（時刻を進めてからPOST）
    $outAt = $inAt->copy()->setTime(18, 5, 0);
    Carbon::setTestNow($outAt);

    $this->from('/attendance')
      ->post(route('attendance.clockOut'))
      ->assertStatus(302);

    // 4) 勤怠一覧で退勤時刻が表示されることを確認
    $res = $this->get('/attendance/list');
    $res->assertStatus(200);
    $html = $res->getContent();

    // 表記ゆれ（H:i / H:i:s / H時m分 / H:m 等）を許容して退勤時刻を検出
    $this->assertTrue(
      $this->timeAppears($html, $outAt),
      '勤怠一覧に退勤時刻が見つかりませんでした: ' . $outAt->toDateTimeString()
    );

    // 当日の日付も表示（表記ゆれ許容）
    $year = (int)$outAt->format('Y');
    $mon = (int)$outAt->format('n');
    $day = (int)$outAt->format('j');
    $datePatterns = [
      '/\b' . $year . '[-\/\.]0?' . $mon . '[-\/\.]0?' . $day . '\b/u',
      '/' . $year . '\s*年\s*0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',
      '/\b0?' . $mon . '\/0?' . $day . '(?:\s*\(.+?\))?\b/u',
      '/0?' . $mon . '\s*月\s*0?' . $day . '\s*日(?:\s*\(.+?\))?/u',
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
  
  private function timeAppears(string $html, \Carbon\Carbon $time): bool
  {
    // 09:05, 9:5, 09:05:00, 9時5分 など様々な表記を許容
    $h = (int)$time->format('G');
    $m = (int)$time->format('i');
    $s = (int)$time->format('s');

    $patterns = [
      // 09:05, 9:5, 09:05:00, 9:5:0
      '/\b0?' . $h . '[:：]0?' . $m . '(?::0?' . $s . ')?\b/u',
      // 9時5分
      '/' . $h . '\s*時\s*0?' . $m . '\s*分/u',
      // 9時
      '/' . $h . '\s*時/u',
    ];

    foreach ($patterns as $re) {
      if (preg_match($re, $html)) {
        return true;
      }
    }
    return false;
  }
}
