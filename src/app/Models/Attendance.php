<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CorrectionRequest;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'note',
    ];

    protected $casts = [
        'work_date'    => 'date',
        'clock_in_at'  => 'datetime',
        'clock_out_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function breaks()
    {
        return $this->hasMany(\App\Models\BreakTime::class, 'attendance_id');
    }

    public function getStatusAttribute(): string
    {
        if (is_null($this->clock_in_at)) return '勤務外';
        $hasOpenBreak = $this->breaks()->whereNull('end_at')->exists();
        if (!is_null($this->clock_in_at) && is_null($this->clock_out_at) && $hasOpenBreak) return '休憩中';
        if (!is_null($this->clock_in_at) && is_null($this->clock_out_at)) return '出勤中';
        return '退勤済';
    }

    public function correctionRequests()
    {
        return $this->hasMany(CorrectionRequest::class);
    }
}
