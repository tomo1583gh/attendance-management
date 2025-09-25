<?php

namespace Tests\Feature\User\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class DateTimeDisplayTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function 現在の日時情報がUIと同じ形式で出力されている()
  {
    // 1) 現在時刻を固定（JST）
    Carbon::setTestNow(Carbon::create(2025, 9, 22, 15, 34, 0, 'Asia/Tokyo'));
    $now     = Carbon::now('Asia/Tokyo');
    $expectD = $now->format('Ymd'); // 例: 20250922
    $expectT = $now->format('Hi');  // 例: 1534

    // 2) ログインしてアクセス
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/attendance');
    $response->assertStatus(200);

    $html = $response->getContent();

    // 3) date/time ブロックが存在すること
    $this->assertStringContainsString('<div class="date">', $html);
    $this->assertStringContainsString('<div class="time">', $html);

    // 4) 中身を抽出
    $this->assertTrue(
      preg_match('/<div class="date">\s*(.*?)\s*<\/div>/u', $html, $mDate) === 1,
      'date の中身が取得できませんでした'
    );
    $this->assertTrue(
      preg_match('/<div class="time">\s*(.*?)\s*<\/div>/u', $html, $mTime) === 1,
      'time の中身が取得できませんでした'
    );

    $dateText = trim($mDate[1]); // 例: "2025/09/22" / "2025年9月22日(月)"
    $timeText = trim($mTime[1]); // 例: "15:34" / "15時34分" / "15:34:00"

    // 5) 日付を「YYYYMMDD」に正規化して比較（ゼロ埋め対応）
    $normYmd = $this->normalizeYmd($dateText);
    $this->assertNotNull($normYmd, "日付の解析に失敗しました: {$dateText}");
    $this->assertSame(
      $expectD,          // 例: 20250922
      $normYmd,
      "日付が現在日付(YYYYMMDD={$expectD})と一致しません: {$dateText}"
    );

    // 6) 時刻は数字抽出→先頭4桁(HHMM)で比較（秒や「時/分」表記を許容）
    $normTime = $this->digitsOnly($timeText); // 15:34 / 15時34分 / 15:34:00 → 1534 / 153400
    $this->assertSame(
      $expectT,          // 例: 1534
      substr($normTime, 0, 4),
      "時刻(HHMM={$expectT})が現在時刻と一致しません: {$timeText}"
    );
  }

  /**
   * 日付文字列を YYYYMMDD に正規化（ゼロ埋め・曜日カッコ許容・全角対応）
   */
  private function normalizeYmd(string $text): ?string
  {
    $src = trim($text);

    // パターン1: 2025/9/22, 2025-09-22, 2025.9.22
    if (preg_match('/^\s*(\d{4})[\/\.\-]\s*(\d{1,2})[\/\.\-]\s*(\d{1,2})\s*$/u', $src, $m)) {
      [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
      return sprintf('%04d%02d%02d', $y, $mo, $d);
    }

    // パターン2: 2025年9月22日、2025年09月22日(月) など（曜日カッコは任意）
    if (preg_match('/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日(?:\s*\(.*?\))?\s*$/u', $src, $m)) {
      [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
      return sprintf('%04d%02d%02d', $y, $mo, $d);
    }

    // フォールバック: 全角→半角にして数字だけ取り出し、年+月+日を推定
    $half = mb_convert_kana($src, 'as', 'UTF-8');
    preg_match_all('/\d+/', $half, $nums);
    $digits = implode('', $nums[0]); // 例: "2025922" など

    if (strlen($digits) >= 7) {
      $y = (int)substr($digits, 0, 4);
      $rest = substr($digits, 4);

      // 残り3桁: MDD（例: 922） → M=1桁, D=2桁
      if (strlen($rest) === 3) {
        $mo = (int)substr($rest, 0, 1);
        $d  = (int)substr($rest, 1, 2);
        return sprintf('%04d%02d%02d', $y, $mo, $d);
      }

      // 残り4桁以上: MMDD（例: 0922/1222…先頭4桁を使う）
      if (strlen($rest) >= 4) {
        $mo = (int)substr($rest, 0, 2);
        $d  = (int)substr($rest, 2, 2);
        return sprintf('%04d%02d%02d', $y, $mo, $d);
      }
    }

    return null;
  }

  /**
   * 全角数字を半角にし、数字のみを連結して返す（時刻用）
   */
  private function digitsOnly(string $text): string
  {
    $half = mb_convert_kana($text, 'as', 'UTF-8');
    preg_match_all('/\d+/', $half, $m);
    return implode('', $m[0]);
  }
}
