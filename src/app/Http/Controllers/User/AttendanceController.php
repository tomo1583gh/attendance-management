<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use App\Models\BreakTime;

class AttendanceController extends Controller
{
    public function index(Request $req)
    {
        $today = now()->toDateString();
        $attendance = Attendance::firstOrCreate([
            'user_id' => $req->user()->id,
            'work_date' => $today,
        ]);
        return view('user.attendance.index', compact('attendance'));
    }

    public function clockIn(Request $req)
    {
        $attendance = Attendance::firstOrCreate(['user_id' => $req->user()->id, 'work_date' => now()->toDateString()]);
        abort_if($attendance->clock_in_at, 400, '既に出勤済みです');
        $attendance->update(['clock_in_at' => now()]);
        return back();
    }

    public function breakIn(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)->where('work_date', now()->toDateString())->firstOrFail();
        BreakTime::create(['attendance_id' => $attendance->id, 'start_at' => now()]);
        return back();
    }

    public function breakOut(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)->where('work_date', now()->toDateString())->firstOrFail();
        $break = $attendance->breaks()->whereNull('end_at')->latest()->firstOrFail();
        $break->update(['end_at' => now()]);
        return back();
    }

    public function clockOut(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)->where('work_date', now()->toDateString())->firstOrFail();
        abort_if($attendance->clock_out_at, 400, '既に退勤済みです');
        $attendance->update(['clock_out_at' => now()]);
        return back()->with('message', 'お疲れ様でした。');
    }
}
