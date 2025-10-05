<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'pending'); // pending | approved

        $query = CorrectionRequest::with(['user', 'attendance'])
            ->orderByDesc('created_at');

        if ($tab === 'approved') {
            $query->approved(); // scopeApproved()
        } else {
            $query->pending();  // scopePending()
        }

        $rows = $query->get()->map(function ($r) {
            return (object)[
                'id'           => $r->id,
                'status_text'  => $r->status_text, // アクセサを使用
                'user_name'    => $r->user->name ?? '',
                // 対象日は Attendance 側に date や work_date 等がある想定。なければ created_at などに置換
                'target_at'    => optional(optional($r->attendance)->work_date ?? $r->created_at)->format('Y/m/d'),
                'reason'       => $r->reason ?? '',
                'requested_at' => optional($r->created_at)->format('Y/m/d'),
            ];
        });

        return view('admin.requests.index', compact('rows', 'tab')); 
  }

    public function show(string $id)
    {
        $req = CorrectionRequest::with(['user', 'attendance', 'attendance.breaks'])->findOrFail($id);
        $att = $req->attendance;

        $fmt = function ($v, $f = 'H:i') {
            if (empty($v)) return null;
            return Carbon::parse($v)->format($f);
        };

        // 現在の勤怠
        $currentIn  = $fmt($att->clock_in_at  ?? $att->start_time);
        $currentOut = $fmt($att->clock_out_at ?? $att->end_time);

        // 申請（提案）値：proposed_*がなければpaylodeを使う
        $clockInRaw  = $req->proposed_clock_in_at  ?? ($req->payload['clock_in']  ?? null);
        $clockOutRaw = $req->proposed_clock_out_at ?? ($req->payload['clock_out'] ?? null);

        $propIn  = $fmt($req->proposed_clock_in_at);
        $propOut = $fmt($req->proposed_clock_out_at);

        // ★ 休憩：proposed_breaks → 無ければ payload.breaks
        $rawBreaks = is_array($req->proposed_breaks) ? $req->proposed_breaks : ($req->payload['breaks'] ?? []);
        $proposedBreaks = [];
        foreach ((array)$rawBreaks as $br) {
            $startRaw = $br['start'] ?? ($br['break_start'] ?? null);
            $endRaw   = $br['end']   ?? ($br['break_end']   ?? null);
            $start = $startRaw ? Carbon::parse($startRaw)->format('H:i') : null;
            $end   = $endRaw   ? Carbon::parse($endRaw)->format('H:i')   : null;
            if ($start || $end) {
                $proposedBreaks[] = (object)['break_start' => $start, 'break_end' => $end];
            }
        }

        $currentBreaks = [];
        if ($att && $att->relationLoaded('breaks')) {
            foreach ($att->breaks as $row) {
                $currentBreaks[] = (object)[
                    'break_start' => optional($row->start_at ?? $row->break_start)->format('H:i'),
                    'break_end'   => optional($row->end_at   ?? $row->break_end)->format('H:i'),
                ];
            }
        }

        // トップレベル（Bladeが直参照している場合に備えて、申請値を優先）
        $p1 = $proposedBreaks[0] ?? null;
        $p2 = $proposedBreaks[1] ?? null;
        $c1 = $currentBreaks[0] ?? null;
        $c2 = $currentBreaks[1] ?? null;

        $vm = (object)[
            'id'        => $req->id,
            'user_name' => $req->user->name ?? '',
            'date'      => $fmt($att->work_date ?? $att->date, 'Y-m-d'),

            // 出退勤：申請があれば申請値を表示、なければ現在値
            'clock_in'  => $propIn  ?? $currentIn,
            'clock_out' => $propOut ?? $currentOut,

            // 休憩（配列）
            'proposed_breaks' => $proposedBreaks, // 申請
            'current_breaks'  => $currentBreaks,  // 現在

            // 休憩（トップレベル：Bladeが break_start 直参照する場合用。申請値優先、無ければ現在値）
            'break_start'  => ($p1->break_start ?? null) ?? ($c1->break_start ?? null),
            'break_end'    => ($p1->break_end   ?? null) ?? ($c1->break_end   ?? null),
            'break2_start' => ($p2->break_start ?? null) ?? ($c2->break_start ?? null),
            'break2_end'   => ($p2->break_end   ?? null) ?? ($c2->break_end   ?? null),

            'note'      => $req->reason ?? '',
            'approved'  => $req->status === 'approved',

            // 参考表示用
            'current_clock_in'   => $currentIn,
            'current_clock_out'  => $currentOut,
            'proposed_clock_in'  => $propIn,
            'proposed_clock_out' => $propOut,
        ];

        return view('admin.requests.show', ['requestItem' => $vm]);
    }

    public function approve(string $id)
    {
        $requestItem = CorrectionRequest::with('attendance')->findOrFail($id);

        if ($requestItem->status === 'approved') {
            return redirect()->route('admin.requests.show', ['id' => $requestItem->id])
                ->with('status', 'already-approved');
        }

        DB::transaction(function () use ($requestItem) {
            $attendance = $requestItem->attendance()->lockForUpdate()->firstOrFail();

            // 勤務日（H:i形式で来た場合に日付を補う）
            $ymd = optional($attendance->work_date ?? $attendance->date)->format('Y-m-d') ?? now()->format('Y-m-d');

            // 汎用：raw値 → Carbon（H:i なら Y-m-d を補完）
            $toCarbon = function ($v) use ($ymd) {
                if ($v === null || $v === '') return null;
                if ($v instanceof \Carbon\Carbon) return $v;
                $s = (string)$v;
                if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
                    $s = "{$ymd} {$s}:00";
                }
                return \Carbon\Carbon::parse($s);
            };

            // ★ 出退勤：proposed_* が無ければ payload からフォールバック
            $inRaw  = $requestItem->proposed_clock_in_at  ?? ($requestItem->payload['clock_in']  ?? null);
            $outRaw = $requestItem->proposed_clock_out_at ?? ($requestItem->payload['clock_out'] ?? null);

            if ($in = $toCarbon($inRaw)) {
                $attendance->clock_in_at = $in;
            }
            if ($out = $toCarbon($outRaw)) {
                $attendance->clock_out_at = $out;
            }
            if (!empty($requestItem->reason)) {
                $attendance->note = $requestItem->reason;
            }
            $attendance->save();

            // ★ 休憩：proposed_breaks が無ければ payload.breaks を使う
            $rawBreaks = is_array($requestItem->proposed_breaks) && count($requestItem->proposed_breaks)
                ? $requestItem->proposed_breaks
                : (array)($requestItem->payload['breaks'] ?? []);

            // 既存休憩を入れ替え
            \App\Models\BreakTime::where('attendance_id', $attendance->id)->delete();

            foreach ($rawBreaks as $br) {
                $s = $toCarbon($br['start'] ?? ($br['break_start'] ?? null));
                $e = $toCarbon($br['end']   ?? ($br['break_end']   ?? null));
                if (!$s || !$e) continue;

                \App\Models\BreakTime::create([
                    'attendance_id' => $attendance->id,
                    // ★ テーブル定義に合わせて start_at / end_at を使用
                    'start_at'      => $s,
                    'end_at'        => $e,
                ]);
            }

            // 承認済みへ
            $requestItem->status      = 'approved';
            $requestItem->approved_by = \Illuminate\Support\Facades\Auth::guard('admin')->id();
            $requestItem->approved_at = now();
            $requestItem->save();
        });

        return redirect()->route('admin.requests.show', ['id' => $requestItem->id])
            ->with('status', 'approved');
    }
}