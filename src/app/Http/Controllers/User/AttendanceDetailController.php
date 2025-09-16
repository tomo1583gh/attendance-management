<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\Attendance;


class AttendanceDetailController extends Controller
{
    public function show($id)
    {
        // 自分の勤怠のみ & 休憩は並び順でロード
        $attendance = Attendance::with(['breaks' => function ($q) {
            // order_no を使っているなら ->orderBy('order_no')
            $q->orderBy('start_at')->orderBy('id');
        }, 'user'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // 承認待ちがあるか（編集不可表示に使う）
        $hasPending = CorrectionRequest::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        // Blade の old('breaks') と合う形に整形（既存件数ぶん）
        $prefillBreaks = $attendance->breaks->map(function ($b) {
            return [
                'id'    => $b->id,
                'start' => optional($b->start_at)->format('H:i'),
                'end'   => optional($b->end_at)->format('H:i'),
            ];
        })->toArray();

        // “空1行”のインデックス（= 既存件数）
        $nextIndex = count($prefillBreaks);

        return view('user.attendance.detail', compact(
            'attendance',
            'hasPending',
            'prefillBreaks',
            'nextIndex'
        ));
    }
}
