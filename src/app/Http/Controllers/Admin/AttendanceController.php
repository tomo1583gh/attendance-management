<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\User;
use App\Models\Attendance;

class AttendanceController extends Controller
{
    public function daily(Request $request)
    {
        // 対象日（未指定は本日）
        $dateStr = $request->query('date');
        $day     = $dateStr ? Carbon::createFromFormat('Y-m-d', $dateStr) : Carbon::today();
        $start   = $day->copy()->startOfDay();
        $end     = $day->copy()->endOfDay();

        // すべての一般ユーザー（必要に応じて並び順を調整）
        $users = User::orderBy('name')->get(['id', 'name']);

        // 対象日の勤怠を一括取得（休憩も）
        // work_date がある実装に対応しつつ、念のため clock_in_at 日付でも拾う
        $attendances = Attendance::with(['user', 'breaks'])
            ->where(function ($q) use ($day, $start, $end) {
                $q->whereDate('work_date', $day->toDateString())
                    ->orWhereBetween('clock_in_at', [$start, $end]);
            })
            ->get()
            ->keyBy(function ($a) {
                return $a->user_id; // ユーザーIDで引けるように
            });

        // 表示行を作成（ユーザー一覧に対して当日データをはめ込む）
        $rows = [];
        foreach ($users as $u) {
            $a   = $attendances->get($u->id);
            $in  = $a?->clock_in_at  ? Carbon::parse($a->clock_in_at)->format('H:i')  : null;
            $out = $a?->clock_out_at ? Carbon::parse($a->clock_out_at)->format('H:i') : null;

            // 休憩合計（終了済のみ）
            $breakMinutes = 0;
            if ($a) {
                foreach ($a->breaks as $b) {
                    if ($b->start_at && $b->end_at) {
                        $breakMinutes += Carbon::parse($b->start_at)
                            ->diffInMinutes(Carbon::parse($b->end_at));
                    }
                }
            }
            $breakText = $breakMinutes ? $this->minutesToHhmm($breakMinutes) : null;

            // 実働
            $totalText = null;
            if ($a && $a->clock_in_at && $a->clock_out_at) {
                $workMinutes = Carbon::parse($a->clock_in_at)->diffInMinutes(Carbon::parse($a->clock_out_at));
                $totalText   = $this->minutesToHhmm(max(0, $workMinutes - $breakMinutes));
            }

            $rows[] = (object) [
                'user_name'     => $u->name,
                'attendance_id' => $a?->id,
                'in_time'       => $in,
                'out_time'      => $out,
                'break_text'    => $breakText,
                'total_text'    => $totalText,
            ];
        }

        $date     = $day->toDateString();                       // 'Y-m-d'
        $prevDate = $day->copy()->subDay()->toDateString();
        $nextDate = $day->copy()->addDay()->toDateString();

        // ビューは daily.blade.php に変更済み
        return view('admin.attendances.daily', compact('date', 'prevDate', 'nextDate', 'rows'));
    }

    /**
     * 勤怠詳細（管理者）
     */
    public function show($id)
    {
        $attendance = Attendance::with(['user', 'breaks'])->findOrFail($id);
        return view('admin.attendances.detail', compact('attendance'));
    }

    private function minutesToHhmm(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
