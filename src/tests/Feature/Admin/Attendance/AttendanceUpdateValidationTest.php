<?php

namespace Tests\Feature\Admin\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Attendance;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AttendanceUpdateValidationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる(): void
  {
    // ★ 管理者（adminガードのモデルで作成）
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);

    // 一般ユーザー（usersプロバイダ）
    $generalUsers = User::factory()->count(5)->create();

    // 参考：別の管理者（一覧に含まれない想定／Adminは別テーブルなので本来出ない）
    $otherAdmin = Admin::factory()->create([
      'name'  => '管理者B',
      'email' => 'admin_b@example.com',
    ]);

    // ★ 管理者ガードでログイン（web ではなく admin）
    $this->actingAs($admin, 'admin');

    // スタッフ一覧ページへ（/admin/users）
    $response = $this->get(route('admin.users.index'));

    // 200 OK
    $response->assertOk();

    // 全ての一般ユーザーの「氏名」「メールアドレス」が表示されていること
    foreach ($generalUsers as $user) {
      $response->assertSeeText($user->name);
      $response->assertSeeText($user->email);
    }

    // Adminモデルは users 一覧に含まれないはずなので、念のため不在を確認
    $response->assertDontSeeText($admin->email);
    $response->assertDontSeeText($otherAdmin->email);
  }

  #[Test]
  public function ユーザーの勤怠情報が正しく表示される(): void
  {
    // 管理者（adminガード側モデル）でログイン
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 対象ユーザー／他ユーザー
    $targetUser = User::factory()->create(['name' => '対象ユーザー', 'email' => 'user@example.com']);
    $otherUser  = User::factory()->create(['name' => '他ユーザー',   'email' => 'other@example.com']);

    // 対象ユーザーの当月(2025-10)勤怠2件（※ 時刻は *_at カラム）
    Attendance::factory()->create([
      'user_id'      => $targetUser->id,
      'work_date'    => Carbon::create(2025, 10, 1)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 1, 9, 0),
      'clock_out_at' => Carbon::create(2025, 10, 1, 18, 0),
    ]);
    Attendance::factory()->create([
      'user_id'      => $targetUser->id,
      'work_date'    => Carbon::create(2025, 10, 2)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 2, 8, 30),
      'clock_out_at' => Carbon::create(2025, 10, 2, 17, 15),
    ]);

    // 他ユーザーの同月勤怠（混入しないことの検証用）
    Attendance::factory()->create([
      'user_id'      => $otherUser->id,
      'work_date'    => Carbon::create(2025, 10, 1)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 1, 7, 7),
      'clock_out_at' => Carbon::create(2025, 10, 1, 19, 19),
    ]);

    // 月次勤怠一覧（?month=YYYY-MM 明示）
    $response = $this->get(route('admin.users.attendances.monthly', [
      'user'  => $targetUser->id,
      'month' => '2025-10',
    ]));
    $response->assertOk();

    // ヘッダー等にユーザー名が出ている
    $response->assertSeeText('対象ユーザー');

    // 本文を取得して時刻表記ゆれに強い正規表現でチェック
    $html = $response->getContent();

    // 対象ユーザーの2日分の入退勤時刻が表示される（09:00/9:00 等どちらも許容）
    $this->assertMatchesRegularExpression('/\b0?9:00\b/u',  $html); // 1日目 出勤
    $this->assertMatchesRegularExpression('/\b18:00\b/u',   $html); // 1日目 退勤
    $this->assertMatchesRegularExpression('/\b0?8:30\b/u',  $html); // 2日目 出勤
    $this->assertMatchesRegularExpression('/\b17:15\b/u',   $html); // 2日目 退勤

    // 他ユーザーの表示が混ざらない
    $response->assertDontSeeText('他ユーザー');
    $this->assertDoesNotMatchRegularExpression('/\b0?7:07\b/u', $html);
    $this->assertDoesNotMatchRegularExpression('/\b19:19\b/u',  $html);
  }

  #[Test]
  public function 「前月」を押下した時に表示月の前月の情報が表示される(): void
  {
    // 管理者（adminガード）でログイン
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 対象ユーザー
    $user = User::factory()->create(['name' => '対象ユーザー', 'email' => 'user@example.com']);

    // 2025-10 の勤怠（前月表示では出てほしくない）※ユニークな分で衝突回避
    Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 10, 5)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 5, 8, 34),  // 08:34
      'clock_out_at' => Carbon::create(2025, 10, 5, 17, 56), // 17:56
    ]);

    // 2025-09 の勤怠（前月表示で出てほしい）※ユニークな分で衝突回避
    Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 9, 10)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 9, 10, 10, 1),  // 10:01
      'clock_out_at' => Carbon::create(2025, 9, 10, 19, 2),  // 19:02
    ]);

    // まず 2025-10 表示（前月リンク押下前の状態を想定）
    $resOct = $this->get(route('admin.users.attendances.monthly', [
      'user'  => $user->id,
      'month' => '2025-10',
    ]));
    $resOct->assertOk();
    $octHtml = $resOct->getContent();

    // 10月のユニーク時刻は表示、9月のユニーク時刻は未表示
    $this->assertStringContainsString('08:34', $octHtml);
    $this->assertStringContainsString('17:56', $octHtml);
    $this->assertStringNotContainsString('10:01', $octHtml);
    $this->assertStringNotContainsString('19:02', $octHtml);

    // 「前月」ボタン押下相当として 2025-09 を直接表示
    $resSep = $this->get(route('admin.users.attendances.monthly', [
      'user'  => $user->id,
      'month' => '2025-09',
    ]));
    $resSep->assertOk();
    $sepHtml = $resSep->getContent();

    // 9月のユニーク時刻は表示、10月のユニーク時刻は未表示
    $this->assertStringContainsString('10:01', $sepHtml);
    $this->assertStringContainsString('19:02', $sepHtml);
    $this->assertStringNotContainsString('08:34', $sepHtml);
    $this->assertStringNotContainsString('17:56', $sepHtml);
  }

  #[Test]
  public function 「翌月」を押下した時に表示月の翌月の情報が表示される(): void
  {
    // 管理者（adminガード）でログイン
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 対象ユーザー
    $user = User::factory()->create(['name' => '対象ユーザー', 'email' => 'user@example.com']);

    // 2025-09（現月）— 翌月画面では出てほしくない。ユニークな時刻で衝突回避
    Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 9, 10)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 9, 10, 8, 11),   // 08:11
      'clock_out_at' => Carbon::create(2025, 9, 10, 17, 22),  // 17:22
    ]);

    // 2025-10（翌月）— 翌月画面で出てほしい。ユニークな時刻で衝突回避
    Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 10, 5)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 5, 9, 33),   // 09:33
      'clock_out_at' => Carbon::create(2025, 10, 5, 18, 44),  // 18:44
    ]);

    // まず 2025-09 表示（翌月押下前の状態）
    $resSep = $this->get(route('admin.users.attendances.monthly', [
      'user'  => $user->id,
      'month' => '2025-09',
    ]));
    $resSep->assertOk();
    $sepHtml = $resSep->getContent();

    // 9月のユニーク時刻は表示、10月のユニーク時刻は未表示（ゼロ埋めの揺れを許容）
    $this->assertMatchesRegularExpression('/\b0?8:11\b/u', $sepHtml);
    $this->assertMatchesRegularExpression('/\b17:22\b/u',  $sepHtml);
    $this->assertDoesNotMatchRegularExpression('/\b0?9:33\b/u', $sepHtml);
    $this->assertDoesNotMatchRegularExpression('/\b18:44\b/u',  $sepHtml);

    // 「翌月」リンクの存在（テキストとクエリの簡易確認）
    $this->assertStringContainsString('翌月', $sepHtml);
    $this->assertStringContainsString('month=2025-10', $sepHtml);

    // 「翌月」を押下した想定で 2025-10 を表示
    $resOct = $this->get(route('admin.users.attendances.monthly', [
      'user'  => $user->id,
      'month' => '2025-10',
    ]));
    $resOct->assertOk();
    $octHtml = $resOct->getContent();

    // 10月のユニーク時刻は表示、9月のユニーク時刻は未表示
    $this->assertMatchesRegularExpression('/\b0?9:33\b/u', $octHtml);
    $this->assertMatchesRegularExpression('/\b18:44\b/u',  $octHtml);
    $this->assertDoesNotMatchRegularExpression('/\b0?8:11\b/u', $octHtml);
    $this->assertDoesNotMatchRegularExpression('/\b17:22\b/u',  $octHtml);
  }

  #[Test]
  public function 「詳細」を押下すると、その日の勤怠詳細画面に遷移する(): void
  {
    // 管理者（adminガード）でログイン
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 対象ユーザー作成
    $user = User::factory()->create([
      'name'  => '対象ユーザー',
      'email' => 'user@example.com',
    ]);

    // 月次一覧に表示させる勤怠（当該日の詳細へ遷移させる用）
    $targetAttendance = Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 10, 5)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 5, 9, 12),   // 09:12
      'clock_out_at' => Carbon::create(2025, 10, 5, 17, 34),  // 17:34
    ]);

    // 別日の勤怠（誤遷移検知用）
    Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 10, 6)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 6, 8, 22),   // 08:22
      'clock_out_at' => Carbon::create(2025, 10, 6, 18, 10),  // 18:10
    ]);

    // 1) 月次勤怠一覧（対象月 2025-10）を開く
    $monthlyUrl = route('admin.users.attendances.monthly', [
      'user'  => $user->id,
      'month' => '2025-10',
    ]);
    $resMonthly = $this->get($monthlyUrl);
    $resMonthly->assertOk();

    // 一覧に「詳細」リンク（当該出勤の詳細URL）が含まれていること
    $detailUrl = route('admin.attendances.show', ['id' => $targetAttendance->id]);
    $monthlyHtml = $resMonthly->getContent();
    $this->assertStringContainsString($detailUrl, $monthlyHtml);
    $this->assertStringContainsString('詳細', $monthlyHtml);

    // 2) 「詳細」を押下した想定で、詳細URLにアクセス
    $resDetail = $this->get($detailUrl);
    $resDetail->assertOk();

    // 3) 詳細画面が当該レコードの内容を表示していること（ユーザー名と時刻）
    $detailHtml = $resDetail->getContent();
    $this->assertStringContainsString($user->name, $detailHtml);
    // 時刻のゼロ埋め揺れに対応（09:12 / 9:12 どちらでもOK）
    $this->assertMatchesRegularExpression('/\b0?9:12\b/u', $detailHtml);
    $this->assertMatchesRegularExpression('/\b17:34\b/u',  $detailHtml);

    // 別日の代表時刻が出ていない（= 別日の詳細に誤遷移していない）
    $this->assertDoesNotMatchRegularExpression('/\b0?8:22\b/u', $detailHtml);
    $this->assertDoesNotMatchRegularExpression('/\b18:10\b/u',  $detailHtml);
  }
}