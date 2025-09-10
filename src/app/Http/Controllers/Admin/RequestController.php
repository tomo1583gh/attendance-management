<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorrectionRequest;
use App\Models\Attendance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BreakTime;
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
        $req = CorrectionRequest::with(['user', 'attendance'/*, 'attendance.breaks'*/])->findOrFail($id);
        $att = $req->attendance;

        $fmt = function ($v, $f = 'H:i') {
            if (empty($v)) return null;
            return Carbon::parse($v)->format($f);
        };

        // 現在の勤怠
        $currentIn  = $fmt($att->clock_in_at  ?? $att->start_time);
        $currentOut = $fmt($att->clock_out_at ?? $att->end_time);

        // 申請（提案）値
        $propIn  = $fmt($req->proposed_clock_in_at);
        $propOut = $fmt($req->proposed_clock_out_at);

        // 申請側：休憩（proposed_breaks -> H:i に整形）
        $proposedBreaks = [];
        if (is_array($req->proposed_breaks)) {
            foreach ($req->proposed_breaks as $br) {
                $startRaw = $br['start'] ?? ($br['break_start'] ?? null);
                $endRaw   = $br['end']   ?? ($br['break_end']   ?? null);
                $start = $startRaw ? Carbon::parse($startRaw)->format('H:i') : null;
                $end   = $endRaw   ? Carbon::parse($endRaw)->format('H:i')   : null;
                if (!$start && !$end) continue;
                $proposedBreaks[] = (object)[
                    'break_start' => $start,
                    'break_end'   => $end,
                ];
            }
        }

        // ★現在側：休憩（attendance->breaks を H:i に整形）
        $currentBreaks = [];
        if ($att) {
            // カラム名の揺れに対応（start_at/end_at か break_start/break_end）
            $rows = method_exists($att, 'breaks') ? $att->breaks()->get() : collect();
            foreach ($rows as $row) {
                $sRaw = $row->start_at ?? $row->break_start ?? null;
                $eRaw = $row->end_at   ?? $row->break_end   ?? null;
                $s = $sRaw ? Carbon::parse($sRaw)->format('H:i') : null;
                $e = $eRaw ? Carbon::parse($eRaw)->format('H:i') : null;
                if (!$s && !$e) continue;
                $currentBreaks[] = (object)[
                    'break_start' => $s,
                    'break_end'   => $e,
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

            // 出退勤の反映（nullはスキップ）
            if (!is_null($requestItem->proposed_clock_in_at)) {
                $attendance->clock_in_at = $requestItem->proposed_clock_in_at;
            }
            if (!is_null($requestItem->proposed_clock_out_at)) {
                $attendance->clock_out_at = $requestItem->proposed_clock_out_at;
            }
            if (!empty($requestItem->reason)) {
                $attendance->note = $requestItem->reason;
            }
            $attendance->save();

            // 休憩テーブルの入れ替え
            if (is_array($requestItem->proposed_breaks)) {
                BreakTime::where('attendance_id', $attendance->id)->delete();

                // work_date があるので日付+時刻に組み立て（DBがdatetime型なら推奨）
                $date = $attendance->work_date ? $attendance->work_date->format('Y-m-d') : null;

                foreach ($requestItem->proposed_breaks as $br) {
                    if (empty($br['start']) || empty($br['end'])) continue;

                    BreakTime::create([
                        'attendance_id' => $attendance->id,
                        'break_start'   => $date ? "{$date} {$br['start']}:00" : $br['start'],
                        'break_end'     => $date ? "{$date} {$br['end']}:00"   : $br['end'],
                    ]);
                }
            }

            // 申請側を承認済みに
            $requestItem->status      = 'approved';
            $requestItem->approved_by = Auth::guard('admin')->id();
            $requestItem->approved_at = now();
            $requestItem->save();
        });

        return redirect()->route('admin.requests.show', ['id' => $requestItem->id])
            ->with('status', 'approved');
    }
}