<?php

namespace Tests\Feature\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class AttendanceStatusTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 勤務外の場合はステータスが正しく（勤務外）と表示される()
  {
    // 1) 現在を固定（挙動安定のため）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 9, 0, 0, 'Asia/Tokyo'));

    // 2) ステータスが「勤務外」のユーザーでログイン
    //    → 今日は出勤していない（当日の勤怠レコードを作らない）
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 勤怠打刻画面へアクセス
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // 4) ステータス表示「勤務外」を確認
    //    Blade側: <div class="status-badge"> 勤務外 </div>（status=before）
    $response->assertSee('勤務外');

    // （任意の追加確認）
    // 出勤前UIなので「出勤」ボタンが見えていること
    $response->assertSee('出勤');

    // 出勤前なので「退勤」「休憩入」「休憩戻」は表示されないこと（UI仕様に合わせて）
    $response->assertDontSee('退勤');
    $response->assertDontSee('休憩入');
    $response->assertDontSee('休憩戻');
  }

  #[Test]
  public function 出勤中の場合はステータスが正しく（出勤中）と表示される()
  {
    // 1) 現在日時を固定
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 9, 0, 0, 'Asia/Tokyo'));

    // 2) ユーザーを作成してログイン
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 当日の出勤レコードを作成（退勤していない状態）
    DB::table('attendances')->insert([
      'user_id'     => $user->id,
      'work_date'   => Carbon::today('Asia/Tokyo')->toDateString(),
      'clock_in_at' => Carbon::now('Asia/Tokyo')->subHour()->toDateTimeString(),
      'clock_out_at' => null,
      'note'        => null,
      'created_at'  => Carbon::now('Asia/Tokyo'),
      'updated_at'  => Carbon::now('Asia/Tokyo'),
    ]);

    // 4) 勤怠打刻画面にアクセス
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // 5) ステータス「出勤中」が表示されていること
    $response->assertSee('出勤中');

    // （任意確認）出勤中UIなので「休憩入」「退勤」ボタンが表示されていること
    $response->assertSee('退勤');
    $response->assertSee('休憩入');

    // （任意確認）勤務外の文言は表示されない
    $response->assertDontSee('勤務外');
  }

  #[Test]
  public function 休憩中の場合はステータスが正しく（休憩中）と表示される()
  {
    // 1) 現在を固定（JST）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 12, 10, 0, 'Asia/Tokyo'));

    // 2) ユーザーでログイン
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 実アプリのフローを踏む：出勤 → 休憩入
    //    （画面から押すのと同じPOST。FormRequestやドメインロジックをそのまま通します）
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302); // 失敗時はここで落ちるので早期に気づける

    $this->from('/attendance')
      ->post(route('attendance.breakIn'))
      ->assertStatus(302);

    // 4) 画面確認
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // ステータスとUI（Bladeの @case('break') 出力に一致）
    $response->assertSee('休憩中');
    $response->assertSee('休憩戻');     // 休憩中は「休憩戻」ボタンのみ
    $response->assertDontSee('休憩入'); // 表示されない
    $response->assertDontSee('退勤');   // 表示されない
    $response->assertDontSee('勤務外'); // 表示されない
  }

    /**
     * 指定テーブルに存在する最初のカラム名を返す（見つからなければ null/例外）
     */
    private function pickFirstExistingColumn(string $table, array $candidates, bool $required = true): ?string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }
        return $required ? null : null;
  }

  /**
   * 休憩テーブルと主なカラム名を実DBから検出する
   * @return array{string,string,string,?string} [$table, $fkCol, $startCol, $endCol]
   */
  private function detectBreakTableAndColumns(): array
  {
    // テーブル候補（環境により名称が違う想定）
    $tableCandidates = ['attendance_breaks', 'break_times', 'breaks', 'rest_times'];
    $table = null;
    foreach ($tableCandidates as $t) {
      if (Schema::hasTable($t)) {
        $table = $t;
        break;
      }
    }
    $this->assertNotNull($table, '休憩テーブルが見つかりません（候補: ' . implode(',', $tableCandidates) . '）');

    $cols = Schema::getColumnListing($table);

    // 外部キー候補
    $fkCandidates = ['attendance_id', 'attendances_id', 'attendance_record_id', 'attendanceId', 'att_record_id'];
    $fkCol = $this->pickFirstExisting($cols, $fkCandidates);
    $this->assertNotNull($fkCol, '休憩テーブルの出退勤FKが見つかりません（候補: ' . implode(',', $fkCandidates) . '）');

    // 休憩開始・終了カラムは正規表現で検出（多様な命名に対応）
    $startCol = $this->findByRegex($cols, [
      '/^(break_?)?in(_at|_time)?$/i',
      '/^break_?start(_at|_time)?$/i',
      '/^start(_at|_time)?$/i',
      '/^in(_at|_time)?$/i',
    ]);
    $this->assertNotNull($startCol, '休憩開始のカラムが見つかりません（例: break_in_at, start_at, in_at, start_time 等）');

    $endCol = $this->findByRegex($cols, [
      '/^(break_?)?out(_at|_time)?$/i',
      '/^break_?end(_at|_time)?$/i',
      '/^end(_at|_time)?$/i',
      '/^out(_at|_time)?$/i',
    ], false); // 無くてもOK（未終了で休憩中判定）

    return [$table, $fkCol, $startCol, $endCol];
  }

  /** 最初に一致する候補名を返す（なければ null） */
  private function pickFirstExisting(array $cols, array $candidates): ?string
  {
    foreach ($candidates as $c) {
      if (in_array($c, $cols, true)) return $c;
    }
    return null;
  }

  /** 正規表現群のどれかにマッチする最初のカラムを返す（$required=falseなら見つからなくてもnull） */
  private function findByRegex(array $cols, array $regexes, bool $required = true): ?string
  {
    foreach ($regexes as $re) {
      foreach ($cols as $c) {
        if (preg_match($re, $c)) return $c;
      }
    }
    return $required ? null : null;
  }

  #[Test]
  public function 退勤済の場合はステータスが正しく（退勤済）と表示される()
  {
    // 1) 時刻を固定（出勤→8時間後に退勤）
    $start = Carbon::create(2025, 9, 22, 9, 0, 0, 'Asia/Tokyo');
    Carbon::setTestNow($start);

    // 2) ログイン
    $user = User::factory()->create();
    $this->actingAs($user);

    // 3) 出勤
    $this->from('/attendance')
      ->post(route('attendance.clockIn'))
      ->assertStatus(302);

    // 4) 時刻を退勤時刻に進めて退勤
    Carbon::setTestNow($start->copy()->addHours(8)); // 17:00
    $this->from('/attendance')
      ->post(route('attendance.clockOut'))
      ->assertStatus(302);

    // 5) 画面確認
    $response = $this->get('/attendance');
    $response->assertStatus(200);

    // ステータス表示（Bladeの @case('after') 退勤済）
    $response->assertSee('退勤済');
    // 退勤後メッセージ
    $response->assertSee('本日の業務は終了です。');

    // UI（退勤後はボタンが出ない想定）
    $response->assertDontSee('出勤');
    $response->assertDontSee('<button type="submit" class="btn btn--primary">退勤</button>', false);
    $response->assertDontSee('休憩入');
    $response->assertDontSee('休憩戻');
  }
}