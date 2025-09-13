<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use Carbon\Carbon;

class UserController extends Controller
{
    public function index()
    {
        // 一般ユーザーのみを表示（管理者は別テーブル運用が多いが、念のため必要な列だけ取得）
        $users = User::orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.users.index', compact('users'));
    }

    public function monthly(User $user)
    {
        // 対象月
        $ym   = request('month'); // 'YYYY-MM'
        $base = $ym ? Carbon::createFromFormat('Y-m', $ym) : Carbon::today();
        $monthStart = $base->copy()->startOfMonth();
        $monthEnd   = $base->copy()->endOfMonth();

        // 当月勤怠（休憩含む）
        $attendances = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->keyBy(fn($a) => $a->work_date->toDateString());

        // 一般側 list.blade.php と同じ形の rows を作る
        $rows = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $a   = $attendances->get($key);

            $in  = $a?->clock_in_at ? $a->clock_in_at->format('H:i') : null;
            $out = $a?->clock_out_at ? $a->clock_out_at->format('H:i') : null;

            // 休憩合計（終了済のみ）
            $breakMinutes = 0;
            if ($a) {
                foreach ($a->breaks as $b) {
                    if ($b->start_at && $b->end_at) {
                        $breakMinutes += $b->start_at->diffInMinutes($b->end_at);
                    }
                }
            }
            $breakText = $breakMinutes ? $this->minutesToHhmm($breakMinutes) : null;

            // 実働
            $totalText = null;
            if ($a?->clock_in_at && $a?->clock_out_at) {
                $workMinutes = $a->clock_in_at->diffInMinutes($a->clock_out_at);
                $totalText   = $this->minutesToHhmm(max(0, $workMinutes - $breakMinutes));
            }

            $rows[] = (object) [
                'date'          => $cursor->copy(),     // ← Carbon をそのまま渡す（曜日は Blade で和訳）
                'in_time'       => $in,
                'out_time'      => $out,
                'break_text'    => $breakText,
                'total_text'    => $totalText,
                'attendance_id' => $a?->id,
            ];

            $cursor->addDay();
        }

        // タイトルに使う表示名（name 前提）
        $titleName = $user->name ?? 'ユーザー';

        // 一般側と同じ変数名でビューへ
        return view('admin.users.monthly', [
            'user'       => $user,
            'titleName'  => $titleName,
            'currentYm'  => $monthStart->format('Y/m'),
            'month'      => $monthStart->format('Y-m'),               // ルート生成用に残す
            'prevMonth'  => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth'  => $monthStart->copy()->addMonth()->format('Y-m'),
            'rows'       => $rows,

            'csvUrl'     => route('admin.users.attendances.csv', [
                'user'  => $user->id,
                'month' => $monthStart->format('Y-m'),
            ]),
        ]);
    }
    private function minutesToHhmm(int $m): string
    {
        $h = intdiv($m, 60);
        $r = $m % 60;
        return sprintf('%d:%02d', $h, $r);
    }

    public function exportCsv(Request $request, User $user)
    {
        // 月の決定（YYYY-MM）、未指定は当月
        $monthStr = $request->query('month');
        $month    = $monthStr
            ? Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth()
            : now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        // 取得（休憩も一緒に）
        $attendances = Attendance::with('breaks')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        // ヘルパ：分→HH:MM
        $hm = function (?int $minutes): string {
            if ($minutes === null) return '';
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return sprintf('%d:%02d', $h, $m);
        };

        // CSV行を作るクロージャ
        $makeRows = function () use ($attendances, $hm) {
            $rows = [];

            foreach ($attendances as $a) {
                // 出退勤の文字列（nullガード）
                $in  = optional($a->clock_in_at)->format('H:i');
                $out = optional($a->clock_out_at)->format('H:i');

                // 休憩合計（分）
                $breakMinutes = 0;
                foreach ($a->breaks as $b) {
                    $bs = $b->start_at ?? $b->break_start ?? null;
                    $be = $b->end_at   ?? $b->break_end   ?? null;
                    if ($bs && $be) {
                        // キャストがあれば format 済み、無ければ Carbon 化
                        $bsC = $bs instanceof Carbon ? $bs : Carbon::parse($bs);
                        $beC = $be instanceof Carbon ? $be : Carbon::parse($be);
                        $breakMinutes += $bsC->diffInMinutes($beC);
                    }
                }

                // 総労働（分）= 出退勤差 - 休憩
                $totalMinutes = null;
                if ($a->clock_in_at && $a->clock_out_at) {
                    $totalMinutes = $a->clock_in_at->diffInMinutes($a->clock_out_at) - $breakMinutes;
                    if ($totalMinutes < 0) {
                        // 万一マイナスは空にしておく（異常データ対策）
                        $totalMinutes = null;
                    }
                }

                $rows[] = [
                    // Google スプレッドシートで扱いやすい列構成
                    '日付'          => optional($a->work_date)->format('Y-m-d'),
                    '出勤'          => $in ?? '',
                    '退勤'          => $out ?? '',
                    '休憩（合計）'  => $hm($breakMinutes), // "H:MM" 表示
                    '合計（実働）'  => $hm($totalMinutes),
                    '備考'          => $a->note ?? '',
                ];
            }


            return $rows;
        };

        $filename = sprintf('attendance_%s_%s.csv', $user->id, $month->format('Y-m'));

        // ストリーム出力（BOMなしUTF-8）
        return response()->streamDownload(function () use ($makeRows) {
            $out = fopen('php://output', 'w');

            // ヘッダ行
            fputcsv($out, ['日付', '出勤', '退勤', '休憩（合計）', '合計（実働）', '備考']);

            // データ行
            foreach ($makeRows() as $row) {
                fputcsv($out, [
                    $row['日付'],
                    $row['出勤'],
                    $row['退勤'],
                    $row['休憩（合計）'],
                    $row['合計（実働）'],
                    $row['備考'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

