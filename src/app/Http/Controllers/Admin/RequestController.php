<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CorrectionRequest;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'pending'); // pending | approved

        $query = CorrectionRequest::with('user')
            ->orderByDesc('created_at');

        if ($tab === 'approved') {
            $query->where('status', 'approved');
        } else {
            $query->where('status', 'pending');
        }

        $rows = $query->get()->map(function ($r) {
            return (object)[
                'id'           => $r->id,
                'status_text'  => $r->status === 'approved' ? '承認済み' : '承認待ち',
                'user_name'    => $r->user->name ?? '',
                'target_at'    => optional($r->target_at)->format('Y/m/d') ?? '', // 対象日付（カラム名は合わせて）
                'reason'       => $r->reason ?? '',
                'requested_at' => optional($r->created_at)->format('Y/m/d'),
            ];
        });

        return view('admin.requests.index', compact('rows'));
  }

    public function show(string $id)
    {
        $requestItem = CorrectionRequest::with('user')
            ->findOrFail($id);

        // ビューに渡す形は上のBladeに合わせるか、Blade側をここに合わせて変更してください
        $vm = (object)[
            'id'           => $requestItem->id,
            'user_name'    => $requestItem->user->name,
            'date'         => $requestItem->target_date,
            'clock_in'     => $requestItem->clock_in,
            'clock_out'    => $requestItem->clock_out,
            'break_start'  => $requestItem->break_start,
            'break_end'    => $requestItem->break_end,
            'break2_start' => $requestItem->break2_start,
            'break2_end'   => $requestItem->break2_end,
            'note'         => $requestItem->note,
            'approved'     => $requestItem->approved, // boolean
        ];

        return view('admin.requests.show', ['requestItem' => $vm]);
    }

    public function approve(string $id)
    {
        $requestItem = CorrectionRequest::findOrFail($id);

        if ($requestItem->status !== 'approved') {
            // 1) 対象勤怠を更新（必要ならここで $requestItem->attendance を更新）

            // 2) 申請を承認済みに更新
            $requestItem->status = 'approved';
            $requestItem->approved_by = auth('admin')->id() ?? null;
            $requestItem->approved_at = now();
            $requestItem->save();
        }

        return redirect()
            ->route('admin.requests.show', ['id' => $requestItem->id])
            ->with('status', 'approved');
    }
}