<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\CorrectionRequest;

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
    public function store(Request $request, $attendanceId)
    {
        $attendance = Attendance::where('user_id', $request->user()->id)
            ->findOrFail($attendanceId);

        // 既に承認待ちがある場合はブロック（detail.blade.php 側のメッセージと連携）
        $hasPending = CorrectionRequest::where('user_id', $request->user()->id)
            ->where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();
        if ($hasPending) {
            return back()->with('pending', true);
        }

        // 入力バリデーション（最低限）
        $request->validate([
            'clock_in'            => ['nullable', 'date_format:H:i'],
            'clock_out'           => ['nullable', 'date_format:H:i'],
            'breaks.*.start'      => ['nullable', 'date_format:H:i'],
            'breaks.*.end'        => ['nullable', 'date_format:H:i'],
            'note'                => ['nullable', 'string', 'max:255'],
        ]);

        // 相関チェック（要件に近いメッセージに）
        $in  = $request->input('clock_in');
        $out = $request->input('clock_out');
        $breaks = $request->input('breaks', []);

        // 出勤/退勤の整合
        if ($in && $out && $in > $out) {
            return back()->withErrors([
                'clock_out' => '出勤時間もしくは退勤時間が不適切な値です',
            ])->withInput();
        }

        // 休憩の整合
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

        // 申請データ作成（差分を payload(JSON) に格納）
        $payload = [
            'clock_in'  => $in,
            'clock_out' => $out,
            'breaks'    => $breaks,
            'note'      => $request->input('note'),
        ];

        CorrectionRequest::create([
            'user_id'       => $request->user()->id,
            'attendance_id' => $attendance->id,
            'status'        => 'pending',            // 初期は承認待ち
            'reason'        => $request->input('note', ''), // スクショ上は備考=理由
            'payload'       => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return redirect()->route('request.list', ['tab' => 'pending'])
            ->with('status', '修正申請を送信しました');
    }
}
