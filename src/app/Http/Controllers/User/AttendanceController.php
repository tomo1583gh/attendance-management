<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\BreakTime;

class AttendanceController extends Controller
{
    public function index(Request $req)
    {
        $today = Carbon::today();
        $weekday = ['日', '月', '火', '水', '木', '金', '土'][$today->dayOfWeek];
        $dateText = $today->format("Y年n月j日") . "({$weekday})";
        $timeText = now()->format('H:i');

        // 今日の勤怠（無ければ作らない：firstOrCreate だと開いただけでレコードが増えるため）
        $attendance = Attendance::where('user_id', $req->user()->id)
            ->where('work_date', $today->toDateString())
            ->with(['breaks' => function ($q) {
                $q->orderBy('start_at');
            }])
            ->first();

        // 状態判定
        $status = 'before';
        if ($attendance && $attendance->clock_in_at) {
            $onBreak = $attendance->breaks()->whereNull('end_at')->exists();
            if ($attendance->clock_out_at) {
                $status = 'after';
            } elseif ($onBreak) {
                $status = 'break';
            } else {
                $status = 'working';
            }
        }

        return view('user.attendance.index', compact('attendance', 'status', 'dateText', 'timeText'));
    }

    public function clockIn(Request $req)
    {
        $today = Carbon::today()->toDateString();

        // その日の勤怠行を取得 or 作成
        $attendance = Attendance::firstOrCreate([
            'user_id'   => $req->user()->id,
            'work_date' => $today,
        ]);

        abort_if($attendance->clock_in_at, 400, '既に出勤済みです');

        $attendance->update(['clock_in_at' => now()]);

        return redirect()->route('attendance.index')->with('status', '出勤しました');
    }

    public function breakIn(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)
            ->where('work_date', Carbon::today()->toDateString())
            ->firstOrFail();

        // 出勤していない or 退勤済みはNG
        abort_if(!$attendance->clock_in_at, 400, '出勤前は休憩できません');
        abort_if($attendance->clock_out_at, 400, '退勤後は休憩できません');

        // 既に休憩中はNG
        $onBreak = $attendance->breaks()->whereNull('end_at')->exists();
        abort_if($onBreak, 400, '既に休憩中です');

        BreakTime::create([
            'attendance_id' => $attendance->id,
            'start_at'      => now(),
        ]);

        return redirect()->route('attendance.index')->with('status', '休憩に入りました');
    }

    public function breakOut(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)
            ->where('work_date', Carbon::today()->toDateString())
            ->firstOrFail();

        // 未出勤 or 退勤済みはNG
        abort_if(!$attendance->clock_in_at, 400, '出勤前は休憩戻できません');
        abort_if($attendance->clock_out_at, 400, '退勤後は休憩戻できません');

        // 未終了の休憩をクローズ
        $break = $attendance->breaks()->whereNull('end_at')->latest('start_at')->firstOrFail();
        $break->update(['end_at' => now()]);

        return redirect()->route('attendance.index')->with('status', '休憩から戻りました');
    }

    public function clockOut(Request $req)
    {
        $attendance = Attendance::where('user_id', $req->user()->id)
            ->where('work_date', Carbon::today()->toDateString())
            ->firstOrFail();

        abort_if(!$attendance->clock_in_at, 400, '出勤前は退勤できません');
        abort_if($attendance->clock_out_at, 400, '既に退勤済みです');

        // 休憩中はNG（要件上、休憩を閉じてから退勤）
        $onBreak = $attendance->breaks()->whereNull('end_at')->exists();
        abort_if($onBreak, 400, '休憩中は退勤できません（休憩戻を押してください）');

        $attendance->update(['clock_out_at' => now()]);

        return redirect()->route('attendance.index')->with('status', 'お疲れ様でした。');
    } 
}
