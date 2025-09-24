<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CorrectionRequest;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;


class AttendanceDetailController extends Controller
{
    public function show($id, Request $request)
    {
        if ((int)$id === 0) { // ★ 打刻なしリンク: /attendance/detail/0?date=YYYY-MM-DD
            // クエリの date をバリデートして Carbon 化
            $validated   = $request->validate(['date' => ['required', 'date']]);
            $displayDate = Carbon::parse($validated['date'])->startOfDay(); // ★ 画面表示用の“選択日”

            // ログインユーザー＆当日で既存勤怠を探す（休憩は空行除外＆整列）
            $attendance = Attendance::with(['breaks' => function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('start_at')->orWhereNotNull('end_at');
                });
                $q->orderBy('order_no')->orderBy('start_at')->orderBy('id');
            }, 'user'])
                ->where('user_id', auth()->id())
                ->whereDate('work_date', $displayDate)
                ->first();

            if (!$attendance) {
                // ★ 見つからなければ表示用の“空”モデルを作る（関係も空でセット）
                $attendance = new Attendance([
                    'user_id'   => auth()->id(),
                    'work_date' => $displayDate,
                ]);
                $attendance->setRelation('breaks', collect());
                $attendance->setRelation('user', Auth::user());
            }

            // ★ 承認待ちは存在しない（新規/未保存なので false）
            $hasPending = false;
        } else {
            // ★ 既存の id 指定（あなたの元コードをベースに踏襲）
            $attendance = Attendance::with(['breaks' => function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('start_at')->orWhereNotNull('end_at');
                });
                $q->orderBy('order_no')->orderBy('start_at')->orderBy('id');
            }, 'user'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            // ★ 表示日を勤怠の work_date から決定
            $displayDate = Carbon::parse($attendance->work_date)->startOfDay();

            // 承認待ちがあるか（編集不可表示に使う）
            $hasPending = CorrectionRequest::where('attendance_id', $attendance->id)
                ->where('status', 'pending')
                ->exists();
        }

        // ★ 既存処理（空行除外→配列化）はそのまま利用
        $prefillBreaks = $attendance->breaks
            ->filter(fn($b) => $b->start_at || $b->end_at)
            ->values()
            ->map(function ($b) {
                return [
                    'id'    => $b->id,
                    'start' => optional($b->start_at)->format('H:i'),
                    'end'   => optional($b->end_at)->format('H:i'),
                ];
            })
            ->toArray();

        $nextIndex = count($prefillBreaks);

        // ★ ビューに displayDate と user を渡す（テストで “日付/名前” を拾えるように）
        $user = Auth::user();

        return view('user.attendance.detail', compact(
            'attendance',
            'hasPending',
            'prefillBreaks',
            'nextIndex',
            'displayDate',  // ★ 追加
            'user'          // ★ 追加
        ));
    }
}
