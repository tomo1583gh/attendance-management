<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(
            function () use ($attendance) {
                $onBreak = $attendance->breaks()
                    ->whereNull('end_at')
                    ->lockForUpdate()
                    ->exists();
                abort_if($onBreak, 400, '既に休憩中です');

                $nextOrder = (int) $attendance->breaks()->max('order_no') + 1;

                $attendance->breaks()->create([
                    'start_at' => now(),
                    'order_no' => $nextOrder,
                ]);
            }
        );

        /* BreakTime::create([
            'attendance_id' => $attendance->id,
            'start_at'      => now(),
        ]); */

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
        $break = $attendance->breaks()
            ->whereNull('end_at')
            ->latest('start_at')
            ->firstOrFail();

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

    public function update(Request $request, $id)
    {
        // 関連も読むと後段の breaks 保存でN+1を避けられます
        $attendance = Attendance::with('breaks')->findOrFail($id);

        $date = Carbon::parse($attendance->work_date); // 勤務日（Y-m-d）

        // 出勤・退勤
        $attendance->clock_in_at = $request->filled('clock_in')
            ? $date->copy()->setTimeFromTimeString($request->input('clock_in'))  // 例: 09:00
            : null;

        $attendance->clock_out_at = $request->filled('clock_out')
            ? $date->copy()->setTimeFromTimeString($request->input('clock_out')) // 例: 18:00
            : null;

        // 備考
        $attendance->note = $request->input('note');

        $attendance->save();

        // 休憩（最大2件想定）
        $breakInputs = $request->input('breaks', []);  // [ ['start'=>'12:00','end'=>'13:00'], ... ]
        $existing    = $attendance->breaks()->get();   // 既存コレクション

        for ($i = 0; $i < 2; $i++) {
            $payload = $breakInputs[$i] ?? ['start' => null, 'end' => null];

            $start = !empty($payload['start'])
                ? $date->copy()->setTimeFromTimeString($payload['start'])
                : null;

            $end = !empty($payload['end'])
                ? $date->copy()->setTimeFromTimeString($payload['end'])
                : null;

            $model = $existing->get($i) ?? $attendance->breaks()->make();
            $model->start_at = $start;
            $model->end_at   = $end;
            $attendance->breaks()->save($model);
        }

        // 返り先（一覧など）が渡っていれば優先
        $to = $request->input('redirect_to');
        if ($to && Str::startsWith($to, url('/'))) {
            return redirect()->to($to)->with('status', '勤怠を更新しました');
        }

        // 次点：該当日の管理者日次一覧へ
        if ($attendance->work_date) {
            return redirect()
                ->route('admin.attendances.daily', ['date' => $attendance->work_date->format('Y-m-d')])
                ->with('status', '勤怠を更新しました');
        }

        // 最後の保険：詳細に留まる（※ ルート名は複数形）
        return redirect()
            ->route('admin.attendances.show', ['id' => $attendance->id])
            ->with('status', '勤怠を更新しました');
    } 
}
