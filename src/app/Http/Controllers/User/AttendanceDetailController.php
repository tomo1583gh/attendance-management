<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\Attendance;


class AttendanceDetailController extends Controller
{
    public function show($id)
    {
        // 自分の勤怠のみ & 休憩は「空行除外」+ 並び順でロード
        $attendance = Attendance::with(['breaks' => function ($q) {
            // ★両方NULLの休憩レコードは除外
            $q->where(function ($q) {
                $q->whereNotNull('start_at')->orWhereNotNull('end_at');
            });
            // ★order_no を使っているなら最優先
            $q->orderBy('order_no')->orderBy('start_at')->orderBy('id');
        }, 'user'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // 承認待ちがあるか（編集不可表示に使う）
        $hasPending = CorrectionRequest::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        // ★念のためコレクション側でも空行を除外→0,1,2... に詰め直す
        $prefillBreaks = $attendance->breaks
            ->filter(fn($b) => $b->start_at || $b->end_at)  // どちらか入っているものだけ
            ->values()                                       // インデックス詰め
            ->map(function ($b) {
                return [
                    'id'    => $b->id,
                    'start' => optional($b->start_at)->format('H:i'),
                    'end'   => optional($b->end_at)->format('H:i'),
                ];
            })
            ->toArray();

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
