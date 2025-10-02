<?php

namespace Tests\Feature\Admin\Attendance;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Admin;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceDetailFeatureTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 勤怠詳細画面に表示されるデータが選択したものになっている(): void
  {
    // Arrange: 管理者 + 対象ユーザーの勤怠（他レコードも用意して混入防止）
    $admin = Admin::factory()->create();

    $targetUser = User::factory()->create(['name' => '管理者確認ユーザー']);
    $date = Carbon::create(2025, 5, 10);

    $targetAttendance = Attendance::factory()->for($targetUser)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
      'note'         => '管理者メモ',
    ]);

    // 混入チェック用：別ユーザー/別日のレコード
    $otherUser = User::factory()->create(['name' => '別ユーザー']);
    Attendance::factory()->for($otherUser)->create([
      'work_date'    => $date->copy()->addDay()->startOfDay(),
      'clock_in_at'  => $date->copy()->addDay()->setTime(8, 30),
      'clock_out_at' => $date->copy()->addDay()->setTime(17, 30),
      'note'         => '別レコード',
    ]);

    // Admin でログイン
    $this->actingAs($admin, 'admin');

    // Act: 対象勤怠の管理者詳細ページへ
    $res = $this->get('/admin/attendances/' . $targetAttendance->id);
    $res->assertOk();
    $html = $res->getContent();
    @file_put_contents(storage_path('logs/test_admin_att_detail.html'), $html);

    // Assert: ユーザー名
    $res->assertSee('管理者確認ユーザー');

    // Assert: 日付（表記ゆれに対応）
    $iso   = $date->toDateString();      // 2025-05-10
    $ymd   = $date->format('Y/m/d');     // 2025/05/10
    $kanji = $date->format('Y年n月j日'); // 2025年5月10日
    $this->assertTrue(
      str_contains($html, $iso) || str_contains($html, $ymd) || str_contains($html, $kanji),
      "詳細画面に対象日が見つかりません（{$iso} / {$ymd} / {$kanji} のいずれか）。"
    );

    // Assert: 出退勤時刻（プレーン表示 or input[value] の両方を許容）
    $this->assertTrue(
      str_contains($html, '09:00') || preg_match('/name="clock_in".*value="09:00"/u', $html),
      '出勤時刻 09:00 が確認できません。'
    );
    $this->assertTrue(
      str_contains($html, '18:00') || preg_match('/name="clock_out".*value="18:00"/u', $html),
      '退勤時刻 18:00 が確認できません。'
    );

    // Assert: 備考
    $res->assertSee('管理者メモ');

    // Assert: 混入防止（他レコードの情報が出ていない）
    $this->assertFalse(
      str_contains($html, '別ユーザー') ||
        str_contains($html, '08:30') ||
        str_contains($html, '17:30') ||
        str_contains($html, '別レコード'),
      '他レコードの情報が詳細画面に混入しています。'
    );
  }

  #[Test]
  public function 出勤時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
  {
    // Arrange: 管理者 + 対象勤怠
    $admin = Admin::factory()->create();
    $user  = User::factory()->create(['name' => '対象ユーザー']);
    $date  = Carbon::create(2025, 5, 10);

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
      'note'         => '初期メモ',
    ]);

    // 管理者でログイン
    $this->actingAs($admin, 'admin');

    // Admin詳細ページURL（実装に合わせて調整）
    $detailUrl = '/admin/attendances/' . $attendance->id;

    // Update用URL（名前付きルートがあれば route('admin.attendances.update', $attendance) に変更）
    $updateUrl = $detailUrl; // REST想定: PUT /admin/attendances/{id}

    // Act: 出勤 > 退勤 となる不正値を送信（他のバリデーションに引っかからないよう note は入れておく）
    $response = $this->from($detailUrl)
      ->followingRedirects()
      ->put($updateUrl, [
        'clock_in'  => '19:00',   // 出勤の方が遅い
        'clock_out' => '09:00',
        // 'breaks' は送らない（休憩の相関で別メッセージに逸れないように）
        'note'      => '管理者修正テスト',
        '_method'   => 'PUT',     // ルーティングの都合で必要なら
      ]);

    // Assert: バリデーションエラーメッセージが表示される
    $response->assertOk(); // リダイレクト後の詳細画面が開けていること
    $response->assertSee('出勤時間もしくは退勤時間が不適切な値です');

  }

  #[Test]
  public function 休憩開始時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
  {
    // Arrange: 管理者 + 対象勤怠
    $admin = Admin::factory()->create();
    $user  = User::factory()->create(['name' => '対象ユーザー']);
    $date  = Carbon::create(2025, 5, 10);

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
      'note'         => '初期メモ',
    ]);

    // 管理者ログイン
    $this->actingAs($admin, 'admin');

    // 詳細画面URL（必要なら route('admin.attendances.show', $attendance) に）
    $detailUrl = '/admin/attendances/' . $attendance->id;

    // 更新URL（REST想定: PUT /admin/attendances/{id}。名前付きがあれば route('admin.attendances.update', $attendance) へ）
    $updateUrl = $detailUrl;

    // Act: 退勤(18:00)より後の休憩開始(18:30)を送信 → 目的のバリデーションを誘発
    $response = $this->from($detailUrl)
      ->followingRedirects()
      ->put($updateUrl, [
        'clock_in'  => '09:00',
        'clock_out' => '18:00',
        'breaks'    => [
          ['start' => '18:30', 'end' => null], // start が退勤より後
        ],
        'note'      => '管理者修正テスト',
        '_method'   => 'PUT', // ルート都合で必要なら
      ]);

    // Assert: エラーメッセージを確認
    $response->assertOk();
    $response->assertSee('休憩時間が不適切な値です');

  }

  #[Test]
  public function 休憩終了時間が退勤時間より後になっている場合_エラーメッセージが表示される(): void
  {
    // Arrange: 管理者 + 対象勤怠
    $admin = Admin::factory()->create();
    $user  = User::factory()->create(['name' => '対象ユーザー']);
    $date  = Carbon::create(2025, 5, 10);

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
      'note'         => '初期メモ',
    ]);

    // 管理者ログイン
    $this->actingAs($admin, 'admin');

    // 詳細URL / 更新URL（プロジェクトのルーティングに合わせて調整）
    $detailUrl = '/admin/attendances/' . $attendance->id;
    // 例: 名前付きなら -> $updateUrl = route('admin.attendances.update', $attendance);
    $updateUrl = $detailUrl; // REST想定: PUT /admin/attendances/{id}

    // Act: 退勤(18:00)より後の休憩終了(18:30)を送信 → 目的のバリデーションを誘発
    $response = $this->from($detailUrl)
      ->followingRedirects()
      ->put($updateUrl, [
        'clock_in'  => '09:00',
        'clock_out' => '18:00',
        'breaks'    => [
          ['start' => '12:00', 'end' => '18:30'], // end が退勤より後
        ],
        'note'      => '管理者修正テスト',
        '_method'   => 'PUT', // ルート都合で必要なら
      ]);

    // Assert: 期待メッセージ
    $response->assertOk();
    $response->assertSee('休憩時間もしくは退勤時間が不適切な値です');

  }

  #[Test]
  public function 備考欄が未入力の場合_エラーメッセージが表示される(): void
  {
    // Arrange: 管理者 + 対象勤怠
    $admin = Admin::factory()->create();
    $user  = User::factory()->create(['name' => '対象ユーザー']);
    $date  = Carbon::create(2025, 5, 10);

    $attendance = Attendance::factory()->for($user)->create([
      'work_date'    => $date->copy()->startOfDay(),
      'clock_in_at'  => $date->copy()->setTime(9, 0),
      'clock_out_at' => $date->copy()->setTime(18, 0),
      'note'         => '初期メモ',
    ]);

    // 管理者ログイン
    $this->actingAs($admin, 'admin');

    // 詳細URL / 更新URL（プロジェクトのルーティングに合わせて調整）
    $detailUrl = '/admin/attendances/' . $attendance->id;
    // 例: 名前付きなら -> $updateUrl = route('admin.attendances.update', $attendance);
    $updateUrl = $detailUrl; // REST想定: PUT /admin/attendances/{id}

    // Act: 他の値は妥当・備考のみ未入力で送信
    $response = $this->from($detailUrl)
      ->followingRedirects()
      ->put($updateUrl, [
        'clock_in'  => '09:00',
        'clock_out' => '18:00',
        'breaks'    => [
          ['start' => '12:00', 'end' => '12:30'],
        ],
        'note'      => '',        // 未入力
        '_method'   => 'PUT',     // ルート都合で必要なら
      ]);

    // Assert: 期待エラーメッセージが表示される
    $response->assertOk();
    $response->assertSee('備考を記入してください');

  }
}
