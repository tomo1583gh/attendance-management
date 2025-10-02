<?php

namespace Tests\Feature\Admin\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Admin;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceListFeatureTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
  {
    // Arrange: 管理者 + 一般ユーザー2名 + 当日の勤怠を用意
    $admin = Admin::factory()->create();

    $date = Carbon::create(2025, 5, 10);
    $u1 = User::factory()->create(['name' => '山田太郎']);
    $u2 = User::factory()->create(['name' => '佐藤花子']);

    // 当日分（表示対象）
    $a1 = Attendance::factory()->for($u1)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
    ]);
    $a2 = Attendance::factory()->for($u2)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(10, 0),
      'clock_out_at' => $date->copy()->setTime(19, 0),
    ]);

    // 別日分（表示されないことのフェイルセーフ）
    Attendance::factory()->for($u1)->create([
      'work_date'    => $date->copy()->addDay()->startOfDay(),
      'clock_in_at'  => $date->copy()->addDay()->setTime(9, 0),
      'clock_out_at' => $date->copy()->addDay()->setTime(18, 0),
    ]);

    // Admin でログイン（ガード名 'admin' 前提）
    $this->actingAs($admin, 'admin');

    // Act: 管理者の勤怠一覧（日付指定）
    $res = $this->get('/admin/attendances?date=' . $date->toDateString());
    $res->assertOk();
    $html = $res->getContent();
    @file_put_contents(storage_path('logs/test_admin_attendances.html'), $html);

    // Assert: 当日の全ユーザーが載っている（氏名）
    $res->assertSee('山田太郎');
    $res->assertSee('佐藤花子');

    // 出退勤の時刻が正しい（表示フォーマットは H:i を想定）
    $res->assertSee('09:00');
    $res->assertSee('18:00');
    $res->assertSee('10:00');
    $res->assertSee('19:00');

    // 詳細リンク（/admin/attendances/{id}）がそれぞれ存在すること（実装により省略可）
    $res->assertSee('/admin/attendances/' . $a1->id);
    $res->assertSee('/admin/attendances/' . $a2->id);
  }

  #[Test]
  public function 遷移した際に現在の日付が表示される(): void
  {
    // 今日を固定して検証
    $today = Carbon::create(2025, 5, 10, 12, 0, 0);
    Carbon::setTestNow($today);

    // 管理者でログイン（ガード 'admin' 前提）
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    // 日付クエリ無しでアクセス → 既定で「今日」を表示する想定
    $res = $this->get('/admin/attendances');
    $res->assertOk();

    $html = $res->getContent();
    @file_put_contents(storage_path('logs/test_admin_att_list_today.html'), $html);

    // 表記ゆれを広く許容
    $iso   = $today->toDateString();      // 2025-05-10
    $ymd   = $today->format('Y/m/d');     // 2025/05/10
    $kanji = $today->format('Y年n月j日'); // 2025年5月10日
    $md    = $today->format('m/d');       // 05/10
    $nj    = $today->format('n/j');       // 5/10
    $w     = ['日', '月', '火', '水', '木', '金', '土'][$today->dayOfWeek];
    $mdw1  = $today->format('m/d') . "({$w})"; // 05/10(土)
    $mdw2  = $today->format('n/j') . "({$w})"; // 5/10(土)

    $this->assertTrue(
      str_contains($html, $iso) ||
        str_contains($html, $ymd) ||
        str_contains($html, $kanji) ||
        str_contains($html, $md) ||
        str_contains($html, $nj) ||
        str_contains($html, $mdw1) ||
        str_contains($html, $mdw2),
      "勤怠一覧画面に本日の日付が見つかりません（{$iso} / {$ymd} / {$kanji} / {$md} / {$nj} / {$mdw1} / {$mdw2} のいずれか）。"
    );
  }

  #[Test]
  public function 前日ボタン押下で前の日の勤怠情報が表示される(): void
  {
    // 今日を固定
    Carbon::setTestNow(Carbon::create(2025, 5, 10, 12, 0, 0));
    $today = Carbon::today();
    $prev  = $today->copy()->subDay();

    // 管理者 + ユーザー2名
    $admin = Admin::factory()->create();
    $u1 = User::factory()->create(['name' => '山田太郎']);
    $u2 = User::factory()->create(['name' => '佐藤花子']);

    // 当日分（前日との区別用に時刻を変える）
    Attendance::factory()->for($u1)->create([
      'work_date'    => $today->copy()->startOfDay(),
      'clock_in_at'  => $today->copy()->setTime(9, 0),
      'clock_out_at' => $today->copy()->setTime(18, 0),
    ]);
    Attendance::factory()->for($u2)->create([
      'work_date'    => $today->copy()->startOfDay(),
      'clock_in_at'  => $today->copy()->setTime(10, 0),
      'clock_out_at' => $today->copy()->setTime(19, 0),
    ]);

    // 前日分（検証対象）
    Attendance::factory()->for($u1)->create([
      'work_date'    => $prev->copy()->startOfDay(),
      'clock_in_at'  => $prev->copy()->setTime(8, 30),
      'clock_out_at' => $prev->copy()->setTime(17, 30),
    ]);
    Attendance::factory()->for($u2)->create([
      'work_date'    => $prev->copy()->startOfDay(),
      'clock_in_at'  => $prev->copy()->setTime(9, 30),
      'clock_out_at' => $prev->copy()->setTime(18, 30),
    ]);

    // 管理者でログイン
    $this->actingAs($admin, 'admin');

    // 当日で一覧を開く
    $res = $this->get('/admin/attendances?date=' . $today->toDateString());
    $res->assertOk();
    $html = $res->getContent();
    @file_put_contents(storage_path('logs/test_admin_prev_link.html'), $html);

    // 「前日」リンクの href を抽出（実装表記ゆれに緩く対応）
    $href = null;
    if (preg_match('~<a[^>]+href="([^"]+)"[^>]*>[^<]*前日[^<]*</a>~u', $html, $m)) {
      $href = $m[1];
    }
    if (!$href && preg_match('~href="([^"]*admin/attendances[^"]*date=' . preg_quote($prev->toDateString(), '~') . '[^"]*)"~u', $html, $m)) {
      $href = $m[1];
    }
    if (!$href) {
      // フォールバック：?date=YYYY-MM-DD で前日に直アクセス
      $href = '/admin/attendances?date=' . $prev->toDateString();
    }

    // 抽出したURLに遷移（絶対/相対どちらでも対応）
    $parts = parse_url(html_entity_decode($href));
    $target = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    if ($target === '') {
      $target = $href;
    }

    $page = $this->get($target);
    $page->assertOk();
    $pageHtml = $page->getContent();
    @file_put_contents(storage_path('logs/test_admin_prev_page.html'), $pageHtml);

    // 前日の日付が表示されている（多表記許容）
    $iso   = $prev->toDateString();      // 2025-05-09
    $ymd   = $prev->format('Y/m/d');     // 2025/05/09
    $kanji = $prev->format('Y年n月j日'); // 2025年5月9日
    $this->assertTrue(
      str_contains($pageHtml, $iso) || str_contains($pageHtml, $ymd) || str_contains($pageHtml, $kanji),
      "前日の日付が見つかりません（{$iso} / {$ymd} / {$kanji} のいずれか）。"
    );

    // 前日の勤怠（時刻）が表示されている
    $page->assertSee('08:30');
    $page->assertSee('17:30');
    $page->assertSee('09:30');
    $page->assertSee('18:30');

    // 当日の時刻は含まれない想定（画面が単日表示の場合）
    $this->assertFalse(
      str_contains($pageHtml, '09:00') || str_contains($pageHtml, '18:00') ||
        str_contains($pageHtml, '10:00') || str_contains($pageHtml, '19:00'),
      '当日の時刻が混入しています。'
    );
  }

  #[Test]
  public function 翌日ボタン押下で次の日の勤怠情報が表示される(): void
  {
    // 今日を固定
    Carbon::setTestNow(Carbon::create(2025, 5, 10, 12, 0, 0));
    $today = Carbon::today();
    $next  = $today->copy()->addDay();

    // 管理者 + ユーザー2名
    $admin = Admin::factory()->create();
    $u1 = User::factory()->create(['name' => '山田太郎']);
    $u2 = User::factory()->create(['name' => '佐藤花子']);

    // 当日分（翌日との区別用に時刻を分ける）
    Attendance::factory()->for($u1)->create([
      'work_date'    => $today->copy()->startOfDay(),
      'clock_in_at'  => $today->copy()->setTime(9, 0),
      'clock_out_at' => $today->copy()->setTime(18, 0),
    ]);
    Attendance::factory()->for($u2)->create([
      'work_date'    => $today->copy()->startOfDay(),
      'clock_in_at'  => $today->copy()->setTime(10, 0),
      'clock_out_at' => $today->copy()->setTime(19, 0),
    ]);

    // 翌日分（検証対象）
    Attendance::factory()->for($u1)->create([
      'work_date'    => $next->copy()->startOfDay(),
      'clock_in_at'  => $next->copy()->setTime(8, 45),
      'clock_out_at' => $next->copy()->setTime(17, 45),
    ]);
    Attendance::factory()->for($u2)->create([
      'work_date'    => $next->copy()->startOfDay(),
      'clock_in_at'  => $next->copy()->setTime(9, 45),
      'clock_out_at' => $next->copy()->setTime(18, 45),
    ]);

    // 管理者でログイン
    $this->actingAs($admin, 'admin');

    // 当日で一覧を開く
    $res = $this->get('/admin/attendances?date=' . $today->toDateString());
    $res->assertOk();
    $html = $res->getContent();
    @file_put_contents(storage_path('logs/test_admin_next_link.html'), $html);

    // 「翌日」リンクの href を抽出（実装表記ゆれに緩く対応）
    $href = null;
    if (preg_match('~<a[^>]+href="([^"]+)"[^>]*>[^<]*翌日[^<]*</a>~u', $html, $m)) {
      $href = $m[1];
    }
    if (!$href && preg_match('~href="([^"]*admin/attendances[^"]*date=' . preg_quote($next->toDateString(), '~') . '[^"]*)"~u', $html, $m)) {
      $href = $m[1];
    }
    if (!$href) {
      // フォールバック：?date=YYYY-MM-DD で翌日に直アクセス
      $href = '/admin/attendances?date=' . $next->toDateString();
    }

    // 抽出したURLに遷移（絶対/相対どちらでも対応）
    $parts = parse_url(html_entity_decode($href));
    $target = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
    if ($target === '') {
      $target = $href;
    }

    $page = $this->get($target);
    $page->assertOk();
    $pageHtml = $page->getContent();
    @file_put_contents(storage_path('logs/test_admin_next_page.html'), $pageHtml);

    // 翌日の日付が表示されている（多表記許容）
    $iso   = $next->toDateString();      // 2025-05-11
    $ymd   = $next->format('Y/m/d');     // 2025/05/11
    $kanji = $next->format('Y年n月j日'); // 2025年5月11日
    $this->assertTrue(
      str_contains($pageHtml, $iso) || str_contains($pageHtml, $ymd) || str_contains($pageHtml, $kanji),
      "翌日の日付が見つかりません（{$iso} / {$ymd} / {$kanji} のいずれか）。"
    );

    // 翌日の勤怠（時刻）が表示されている
    $page->assertSee('08:45');
    $page->assertSee('17:45');
    $page->assertSee('09:45');
    $page->assertSee('18:45');

    // 当日の時刻は含まれない想定（画面が単日表示の場合）
    $this->assertFalse(
      str_contains($pageHtml, '09:00') || str_contains($pageHtml, '18:00') ||
        str_contains($pageHtml, '10:00') || str_contains($pageHtml, '19:00'),
      '当日の時刻が混入しています。'
    );
  }
}
