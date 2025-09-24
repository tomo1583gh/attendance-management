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
}