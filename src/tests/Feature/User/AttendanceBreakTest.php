<?php

namespace Tests\Feature\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceBreakTest extends TestCase
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
}
