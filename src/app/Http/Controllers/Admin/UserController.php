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

        return view('admin.user.index', compact('users'));
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
        return view('admin.user.monthly', [
            'user'       => $user,
            'titleName'  => $titleName,
            'currentYm'  => $monthStart->format('Y/m'),
            'month'      => $monthStart->format('Y-m'),               // ルート生成用に残す
            'prevMonth'  => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth'  => $monthStart->copy()->addMonth()->format('Y-m'),
            'rows'       => $rows,
        ]);
    }

    private function minutesToHhmm(int $m): string
    {
        $h = intdiv($m, 60);
        $r = $m % 60;
        return sprintf('%d:%02d', $h, $r);
    }
}
