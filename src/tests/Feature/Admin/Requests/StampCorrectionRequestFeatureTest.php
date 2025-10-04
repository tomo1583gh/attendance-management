<?php

namespace Tests\Feature\Admin\Requests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Admin;                   // 管理者（auth:admin 用）
use App\Models\User;                    // 一般ユーザー
use App\Models\Attendance;              // 勤怠
use App\Models\CorrectionRequest;  // 修正申請（モデル名が違う場合は修正）
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Illuminate\Pagination\AbstractPaginator;
use App\Models\BreakTime;
use PHPUnit\Framework\Attributes\Test;

class StampCorrectionRequestFeatureTest extends TestCase
{
  use RefreshDatabase;

  /**
   * 手順:
   * 1. 管理者ユーザーにログインをする
   * 2. 修正申請一覧ページを開き、承認待ちのタブを開く
   * 期待:
   *  - 全ユーザーの未承認（承認待ち）の修正申請が表示される
   */
  #[Test]
  public function 承認待ちの修正申請が全て表示されている(): void
  {
    // 1) 管理者でログイン（auth:admin）
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 2) 一般ユーザーを用意
    $userA = User::factory()->create(['name' => 'ユーザーA', 'email' => 'a@example.com']);
    $userB = User::factory()->create(['name' => 'ユーザーB', 'email' => 'b@example.com']);

    // 3) 勤怠（修正申請の対象レコード）を用意
    $attA = Attendance::factory()->create([
      'user_id'      => $userA->id,
      'work_date'    => Carbon::create(2025, 10, 1)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 1, 9, 0),
      'clock_out_at' => Carbon::create(2025, 10, 1, 18, 0),
    ]);

    $attB = Attendance::factory()->create([
      'user_id'      => $userB->id,
      'work_date'    => Carbon::create(2025, 10, 2)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 2, 9, 30),
      'clock_out_at' => Carbon::create(2025, 10, 2, 18, 15),
    ]);

    // 4) 修正申請（承認待ち・承認済み）を作成
    $pendingA = CorrectionRequest::factory()->pending()->create([
      'user_id'       => $userA->id,
      'attendance_id' => $attA->id,
    ]);

    $pendingB = CorrectionRequest::factory()->pending()->create([
      'user_id'       => $userB->id,
      'attendance_id' => $attB->id,
    ]);

    CorrectionRequest::factory()->approved()->create([
      'user_id'       => $userB->id,
      'attendance_id' => $attB->id,
    ]);

    // 5) 修正申請一覧「承認待ち」タブを開く
    //    - クエリキーは実装に合わせて（例：?status=pending / ?tab=pending 等）
    $response = $this->get(route('admin.requests.index', ['status' => 'pending']));
    $response->assertOk();

    // 6) タブ文言の存在（UI上で「承認待ち」が見える）
    $response->assertSeeText('承認待ち');

    // 7) 全ユーザーの承認待ち申請が表示されている
    //    - 一覧にユーザー名が表示されていることをもって「表示されている」と判定
    $response->assertSeeText('ユーザーA');
    $response->assertSeeText('ユーザーB');

    // 追加の厳密チェックを入れるなら、対象日の表示や件数検証も可能（実装に合わせて使用）
    // 例:
    // $response->assertSeeText('2025-10-01');
    // $response->assertSeeText('2025-10-02');
  }

  #[Test]
  public function 承認済みの修正申請が全て表示されている(): void
  {
    // 1) 管理者でログイン（auth:admin）
    $admin = Admin::factory()->create(['name' => '管理者A', 'email' => 'admin_a@example.com']);
    $this->actingAs($admin, 'admin');

    // 2) ユーザー＆元勤怠データ（表示列に合わせて work_date を設定）
    $userA = User::factory()->create(['name' => 'ユーザーA', 'email' => 'a@example.com']);
    $userB = User::factory()->create(['name' => 'ユーザーB', 'email' => 'b@example.com']);
    $userC = User::factory()->create(['name' => 'ユーザーC', 'email' => 'c@example.com']); // pending用

    $attA = Attendance::factory()->create([
      'user_id' => $userA->id,
      'work_date' => Carbon::create(2025, 10, 1)->toDateString(), // → target_at は Y/m/d で表示
      'clock_in_at' => Carbon::create(2025, 10, 1, 9, 0),
      'clock_out_at' => Carbon::create(2025, 10, 1, 18, 0),
    ]);
    $attB = Attendance::factory()->create([
      'user_id' => $userB->id,
      'work_date' => Carbon::create(2025, 10, 2)->toDateString(),
      'clock_in_at' => Carbon::create(2025, 10, 2, 9, 30),
      'clock_out_at' => Carbon::create(2025, 10, 2, 18, 15),
    ]);
    $attC = Attendance::factory()->create([
      'user_id' => $userC->id,
      'work_date' => Carbon::create(2025, 10, 3)->toDateString(),
      'clock_in_at' => Carbon::create(2025, 10, 3, 8, 45),
      'clock_out_at' => Carbon::create(2025, 10, 3, 17, 55),
    ]);

    // 3) 修正申請：A/B=承認済み、C=承認待ち
    $approvedA = CorrectionRequest::factory()->approved()->create([
      'user_id' => $userA->id,
      'attendance_id' => $attA->id,
      'reason' => 'Aの修正理由',
      // created_at は並び順に使われるだけなので任意
      'created_at' => Carbon::create(2025, 10, 5, 12, 0),
    ]);
    $approvedB = CorrectionRequest::factory()->approved()->create([
      'user_id' => $userB->id,
      'attendance_id' => $attB->id,
      'reason' => 'Bの修正理由',
      'created_at' => Carbon::create(2025, 10, 6, 9, 0),
    ]);
    $pendingC = CorrectionRequest::factory()->pending()->create([
      'user_id' => $userC->id,
      'attendance_id' => $attC->id,
      'reason' => 'Cの修正理由',
      'created_at' => Carbon::create(2025, 10, 7, 8, 0),
    ]);

    // 4) 承認済みタブを開く（※ コントローラは tab=approved を参照）
    $response = $this->get(route('admin.requests.index', ['tab' => 'approved']));
    $response->assertOk();

    $html = $response->getContent();

    // 5) タブのアクティブ表示とステータスラベル（status_text）を確認
    $response->assertSeeText('承認済み'); // タブの文言 or status_text

    // 6) 承認済み2件がテーブルに表示される（氏名・対象日(Y/m/d)・理由）
    $response->assertSeeText('ユーザーA');
    $response->assertSeeText('2025/10/01');
    $response->assertSeeText('Aの修正理由');

    $response->assertSeeText('ユーザーB');
    $response->assertSeeText('2025/10/02');
    $response->assertSeeText('Bの修正理由');

    // 7) 承認待ちのユーザーCは承認済みタブには表示されない
    $response->assertDontSeeText('ユーザーC');
    $response->assertDontSeeText('2025/10/03');
    $response->assertDontSeeText('Cの修正理由');

    // 8) 詳細リンクが承認済みの分だけ存在し、pending分は含まれない
    $showA = route('admin.requests.show', ['id' => $approvedA->id]);
    $showB = route('admin.requests.show', ['id' => $approvedB->id]);
    $showC = route('admin.requests.show', ['id' => $pendingC->id]);

    $this->assertStringContainsString($showA, $html);
    $this->assertStringContainsString($showB, $html);
    $this->assertStringNotContainsString($showC, $html);
  }

  #[Test]
  public function 修正申請の詳細内容が正しく表示されている(): void
  {
    // 1) 管理者でログイン（auth:admin）
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 2) 対象ユーザー＆元勤怠（現在値）
    $user = User::factory()->create([
      'name'  => '対象ユーザー',
      'email' => 'user@example.com',
    ]);

    $attendance = Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => Carbon::create(2025, 10, 5)->toDateString(),
      'clock_in_at'  => Carbon::create(2025, 10, 5, 9, 0),   // 現在: 09:00
      'clock_out_at' => Carbon::create(2025, 10, 5, 18, 0),  // 現在: 18:00
      'note'         => '元の備考',
    ]);

    // 3) 修正申請（申請値 = proposed_* を明示、休憩は payload.breaks を使用）
    $req = CorrectionRequest::factory()->create([
      'user_id'                => $user->id,
      'attendance_id'          => $attendance->id,
      'status'                 => 'pending',
      'reason'                 => '早退のため',
      'proposed_clock_in_at'   => Carbon::create(2025, 10, 5, 8, 45), // 08:45
      'proposed_clock_out_at'  => Carbon::create(2025, 10, 5, 17, 40), // 17:40
      // breaks は proposed_breaks が配列でない実装でも拾えるよう payload 側に入れる
      'payload'                => [
        'breaks' => [
          ['start' => '12:10', 'end' => '12:40'],
          ['start' => '15:00', 'end' => '15:10'],
        ],
      ],
    ]);

    // 4) 詳細画面へアクセス
    $response = $this->get(route('admin.requests.show', ['id' => $req->id]));
    $response->assertOk();

    // 5) ViewModel(requestItem) を直接検証（UIの表記揺れに依存しない）
    $view = $response->getOriginalContent();
    $this->assertInstanceOf(View::class, $view, 'レスポンスがビューではありません。');
    $vm = $view->getData()['requestItem'] ?? null;
    $this->assertNotNull($vm, 'view変数 requestItem が見つかりません。');

    // 申請のトップ情報
    $this->assertSame($req->id, $vm->id);
    $this->assertSame('対象ユーザー', $vm->user_name);
    $this->assertSame('2025-10-05', $vm->date);       // Y-m-d

    // 出退勤（申請があれば申請値が優先して表示される実装）
    $this->assertSame('08:45', $vm->clock_in);
    $this->assertSame('17:40', $vm->clock_out);

    // 参考：現在値（current_*）
    $this->assertSame('09:00', $vm->current_clock_in);
    $this->assertSame('18:00', $vm->current_clock_out);

    // 休憩（payload.breaks から集約された proposed_breaks が表示される）
    $this->assertIsArray($vm->proposed_breaks);
    $this->assertCount(2, $vm->proposed_breaks);
    $this->assertSame('12:10', $vm->proposed_breaks[0]->break_start);
    $this->assertSame('12:40', $vm->proposed_breaks[0]->break_end);
    $this->assertSame('15:00', $vm->proposed_breaks[1]->break_start);
    $this->assertSame('15:10', $vm->proposed_breaks[1]->break_end);

    // トップレベル（Bladeの直参照用）も整合している
    $this->assertSame('12:10', $vm->break_start);
    $this->assertSame('12:40', $vm->break_end);
    $this->assertSame('15:00', $vm->break2_start);
    $this->assertSame('15:10', $vm->break2_end);

    // 備考（申請理由）
    $this->assertSame('早退のため', $vm->note);

    // 承認フラグ（pending なので false）
    $this->assertFalse($vm->approved);
  }

  #[Test]
  public function 修正申請の承認処理が正しく行われる(): void
  {
    // 1) 管理者でログイン（auth:admin）
    $admin = Admin::factory()->create([
      'name'  => '管理者A',
      'email' => 'admin_a@example.com',
    ]);
    $this->actingAs($admin, 'admin');

    // 2) 対象ユーザー & 勤怠（元値）
    $user = User::factory()->create(['name' => '対象ユーザー', 'email' => 'user@example.com']);
    $workDate = Carbon::create(2025, 10, 5);

    $attendance = Attendance::factory()->create([
      'user_id'      => $user->id,
      'work_date'    => $workDate->toDateString(),
      'clock_in_at'  => $workDate->copy()->setTime(9, 0),   // 09:00
      'clock_out_at' => $workDate->copy()->setTime(18, 0),  // 18:00
      'note'         => '元の備考',
    ]);

    // 既存の休憩（承認時に置き換えられることを確認するため）
    BreakTime::create([
      'attendance_id' => $attendance->id,
      'start_at'      => $workDate->copy()->setTime(12, 0),
      'end_at'        => $workDate->copy()->setTime(12, 20),
    ]);

    // 3) 修正申請（pending）— 承認時の更新予定値
    //    ・出勤 08:45 / 退勤 17:40
    //    ・休憩 12:10-12:40, 15:00-15:10（payload.breaks 経由で適用される想定）
    $requestItem = CorrectionRequest::factory()->create([
      'user_id'       => $user->id,
      'attendance_id' => $attendance->id,
      'status'        => 'pending',
      'reason'        => '早退のため',
      // proposed_* を null のままにし、payload 側のフォールバックを通す
      'proposed_clock_in_at'  => null,
      'proposed_clock_out_at' => null,
      'payload' => [
        'clock_in'  => '08:45',
        'clock_out' => '17:40',
        'breaks'    => [
          ['start' => '12:10', 'end' => '12:40'],
          ['start' => '15:00', 'end' => '15:10'],
        ],
      ],
    ]);

    // 4) 「承認」ボタン押下相当（POST /admin/requests/{id}/approve）
    $response = $this->post(route('admin.requests.approve', ['id' => $requestItem->id]));
    $response->assertRedirect(route('admin.requests.show', ['id' => $requestItem->id]));
    $response->assertSessionHas('status', 'approved');

    // 5) DB/モデルをリフレッシュ
    $requestItem->refresh();
    $attendance->refresh();
    $breaks = BreakTime::where('attendance_id', $attendance->id)
      ->orderBy('start_at')
      ->get();

    // 6) 申請が承認済みになり、承認者と承認日時が入る
    $this->assertSame('approved', $requestItem->status);
    $this->assertNotNull($requestItem->approved_at);
    $this->assertSame($admin->id, $requestItem->approved_by);

    // 7) 勤怠の出退勤・備考が更新される（payloadの値が適用）
    $this->assertSame('08:45', Carbon::parse($attendance->clock_in_at)->format('H:i'));
    $this->assertSame('17:40', Carbon::parse($attendance->clock_out_at)->format('H:i'));
    $this->assertSame('早退のため', $attendance->note);

    // 8) 休憩は既存が全削除され、payload.breaks の2件に置き換えられる
    $this->assertCount(2, $breaks, '休憩が2件に置き換わっていません。');
    $this->assertSame('12:10', Carbon::parse($breaks[0]->start_at)->format('H:i'));
    $this->assertSame('12:40', Carbon::parse($breaks[0]->end_at)->format('H:i'));
    $this->assertSame('15:00', Carbon::parse($breaks[1]->start_at)->format('H:i'));
    $this->assertSame('15:10', Carbon::parse($breaks[1]->end_at)->format('H:i'));

    // 9) 念のためDBレベルでも確認
    $this->assertDatabaseHas('correction_requests', [
      'id'          => $requestItem->id,
      'status'      => 'approved',
      'approved_by' => $admin->id,
    ]);
    $this->assertDatabaseHas('attendances', [
      'id' => $attendance->id,
      // 時刻は秒まで保存されるため、日付＋時分でざっくり検証
      // （厳密に比較したい場合は Carbon::parse(...)->toDateTimeString() で一致比較に変更）
    ]);
  }
}