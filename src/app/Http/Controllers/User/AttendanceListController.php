<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Attendance;

class AttendanceListController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        // ?month=YYYY-MM（無ければ今月）
        $ym   = $request->query('month');
        $base = $ym ? \Carbon\Carbon::createFromFormat('Y-m', $ym)->startOfMonth()
            : \Carbon\Carbon::today()->startOfMonth();

        $start = $base->copy();
        $end   = $base->copy()->endOfMonth();
        $year  = (int)$base->format('Y');
        $month = (int)$base->format('n');

        $currentYm = $base->format('Y/m');
        $prevMonth = $base->copy()->subMonth()->format('Y-m');
        $nextMonth = $base->copy()->addMonth()->format('Y-m');

        // 存在する日付カラムを自動判定（work_date 優先）
        $dateColumn = \Illuminate\Support\Facades\Schema::hasColumn('attendances', 'work_date') ? 'work_date'
            : (\Illuminate\Support\Facades\Schema::hasColumn('attendances', 'date') ? 'date' : null);

        $attendances = \App\Models\Attendance::with('breaks')
            ->where('user_id', $userId)
            ->when($dateColumn, function ($q) use ($dateColumn, $year, $month) {
                $q->whereYear($dateColumn, $year)->whereMonth($dateColumn, $month);
            }, function ($q) use ($year, $month) {
                // 日付カラムが無い/空なら clock_in_at の月で絞る
                $q->whereYear('clock_in_at', $year)->whereMonth('clock_in_at', $month);
            })
            ->get();

        // 'Y-m-d' をキー化（work_date→clock_in_at の順で決める）
        $byDate = $attendances->keyBy(function ($a) use ($dateColumn) {
            if ($dateColumn && !empty($a->{$dateColumn})) {
                return \Carbon\Carbon::parse($a->{$dateColumn})->toDateString();
            }
            if (!empty($a->clock_in_at)) {
                return \Carbon\Carbon::parse($a->clock_in_at)->toDateString();
            }
            return null;
        });

        // 月の全日を生成して表示行を作成
        $rows = [];
        foreach (\Carbon\CarbonPeriod::create($start, $end) as $date) {
            $key = $date->toDateString();
            $a   = $byDate->get($key);

            $in  = $a?->clock_in_at  ? \Carbon\Carbon::parse($a->clock_in_at)->format('H:i')  : null;
            $out = $a?->clock_out_at ? \Carbon\Carbon::parse($a->clock_out_at)->format('H:i') : null;

            // 休憩合計（終了済みのみ）
            $breakMinutes = 0;
            if ($a) {
                foreach ($a->breaks as $b) {
                    if ($b->start_at && $b->end_at) {
                        $breakMinutes += \Carbon\Carbon::parse($b->start_at)
                            ->diffInMinutes(\Carbon\Carbon::parse($b->end_at));
                    }
                }
            }
            $breakText = $breakMinutes ? $this->minutesToHhmm($breakMinutes) : null;

            // 実働
            $totalText = null;
            if ($a && $a->clock_in_at && $a->clock_out_at) {
                $workMinutes = \Carbon\Carbon::parse($a->clock_in_at)->diffInMinutes(\Carbon\Carbon::parse($a->clock_out_at));
                $totalText   = $this->minutesToHhmm(max(0, $workMinutes - $breakMinutes));
            }

            $rows[] = (object)[
                'date'           => $date->copy(),
                'attendance_id'  => $a->id ?? null,
                'in_time'        => $in,
                'out_time'       => $out,
                'break_text'     => $breakText,
                'total_text'     => $totalText,
            ];
        }

        return view('user.attendance.list', compact('rows', 'currentYm', 'prevMonth', 'nextMonth'));
    }

    private function minutesToHhmm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
