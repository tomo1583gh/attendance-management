<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AttendanceDetailController extends Controller
{
    public function show($id)
    {
        $attendance = \App\Models\Attendance::with(['breaks', 'user'])->findOrFail($id);

        // 例：status = 'pending' が承認待ち
        $hasPending = \App\Models\CorrectionRequest::where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->exists();

        return view('user.attendance.detail', compact('attendance', 'hasPending'));
    }
}
