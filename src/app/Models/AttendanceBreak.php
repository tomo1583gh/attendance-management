<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceBreak extends Model
{
    use HasFactory;
    
    protected $fillable = ['attendance_id', 'break_start', 'break_end', 'order_no'];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
