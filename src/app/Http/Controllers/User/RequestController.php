<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\CorrectionRequest;
use App\Http\Requests\User\AttendanceDetailRequest;

class RequestController extends Controller
{
    /**
     * 申請一覧（承認待ち / 承認済み）
     * GET correction_request/list  → name: request.list
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'pending'); // 'pending' | 'approved'

        $requests = CorrectionRequest::with(['attendance', 'user'])
            ->where('user_id', $request->user()->id)
            ->when($tab === 'pending', function ($q) {
                $q->where('status', 'pending');
            })
            ->when($tab === 'approved', function ($q) {
                $q->where('status', 'approved');
            })
            ->orderByDesc('created_at')
            ->get();

        // Blade が期待する $rows に整形
        $rows = $requests->map(function ($r) {
            $date = optional($r->attendance)->work_date
                ? Carbon::parse($r->attendance->work_date)->format('Y/m/d')
                : '';

            return (object) [
                'status_label'  => $r->status === 'approved' ? '承認済み' : '承認待ち',
                'user_name'     => optional($r->user)->name ?? '',
                'target_date'   => $date,
                'reason'        => $r->reason ?? '',
                'requested_at'  => optional($r->created_at)?->format('Y/m/d') ?? '',
                'attendance_id' => $r->attendance_id,
            ];
        });

        return view('user.request.list', [
            'tab'  => $tab,
            'rows' => $rows,
        ]);
    }

    /**
     * 修正申請の登録
     * POST /attendance/{id}/request → name: request.store
     */
    public function store(AttendanceDetailRequest $request, $attendanceId)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->findOrFail($attendanceId);

        // 既に承認待ちがある場合はブロック
        $hasPending = CorrectionRequest::where('user_id', $request->user()->id)
            ->where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();
        if ($hasPending) {
            return redirect()
                ->route('attendance.detail', $attendance->id)
                ->with('pending', true);
        }

        // 入力バリデーション（最低限）
        $request->validate([
            'clock_in'       => ['nullable', 'date_format:H:i'],
            'clock_out'      => ['nullable', 'date_format:H:i'],
            'breaks'         => ['nullable', 'array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i'],
            'breaks.*.end'   => ['nullable', 'date_format:H:i'],
            'note'           => ['nullable', 'string', 'max:255'],
        ]);

        // 相関チェック（要件のメッセージに合わせる）
        $in     = $request->input('clock_in');
        $out    = $request->input('clock_out');
        $breaks = (array) $request->input('breaks', []);

        if ($in && $out && $in > $out) {
            return back()->withErrors([
                'clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
            ])->withInput();
        }
        foreach ($breaks as $b) {
            $bs = $b['start'] ?? null;
            $be = $b['end']   ?? null;
            if ($bs && $in && $bs < $in) {
                return back()->withErrors(['breaks' => '休憩時間が不適切な値です'])->withInput();
            }
            if ($be && $out && $be > $out) {
                return back()->withErrors(['breaks' => '休憩時間もしくは退勤時間が不適切な値です'])->withInput();
            }
        }

        // H:i を当日の datetime に変換（attendances に日付カラムがある前提）
        $workDate = $attendance->work_date ?? $attendance->date ?? now();
        $ymd = \Carbon\Carbon::parse($workDate)->format('Y-m-d');
        $toDateTime = function (?string $hm) use ($ymd) {
            if (!$hm) return null;
            return \Carbon\Carbon::parse("{$ymd} {$hm}:00");
        };

        $proposedClockInAt  = $toDateTime($in);
        $proposedClockOutAt = $toDateTime($out);

        // breaks[0|1][start|end] → proposed_breaks へ正規化（JSON保存用に文字列化）
        $proposedBreaks = [];
        foreach ($breaks as $br) {
            $s = $br['start'] ?? null;
            $e = $br['end']   ?? null;
            if ($s && $e) {
                $proposedBreaks[] = [
                    'start' => $toDateTime($s)->format('Y-m-d H:i:s'),
                    'end'   => $toDateTime($e)->format('Y-m-d H:i:s'),
                ];
            }
        }

        // （任意）差分を payload にも残す
        $payload = [
            'clock_in'  => $in,
            'clock_out' => $out,
            'breaks'    => $breaks,
            'note'      => $request->input('note'),
        ];

        DB::transaction(function () use ($request, $attendance, $proposedClockInAt, $proposedClockOutAt, $proposedBreaks, $payload) {
            CorrectionRequest::create([
                'user_id'                => $request->user()->id,
                'attendance_id'          => $attendance->id,
                'status'                 => 'pending',
                'reason'                 => $request->input('note', ''),
                'proposed_clock_in_at'   => $proposedClockInAt,      // ★ 追加
                'proposed_clock_out_at'  => $proposedClockOutAt,     // ★ 追加
                'proposed_breaks'        => $proposedBreaks,         // ★ 追加（JSON配列）
                'payload'                => $payload,
            ]);
        });

        return redirect()
            ->route('attendance.detail', $attendance->id)
            ->with('pending', true)
            ->with('status', '修正申請を送信しました');
    }
}