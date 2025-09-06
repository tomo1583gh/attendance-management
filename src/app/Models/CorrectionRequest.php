<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorrectionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        // 基本
        'attendance_id',
        'user_id',

        // 申請理由／ステータス
        'reason',
        'status',           // 'pending' | 'approved'

        // 提案（修正案）
        'proposed_clock_in_at',
        'proposed_clock_out_at',
        'proposed_breaks',  // JSON: [{start:'HH:MM', end:'HH:MM'}, …]

        // 承認情報
        'approved_by',      // admins.id（nullable）
        'approved_at',
    ];

    protected $casts = [
        // 日時
        'proposed_clock_in_at'  => 'datetime',
        'proposed_clock_out_at' => 'datetime',
        'approved_at'           => 'datetime',

        // JSON
        'proposed_breaks'       => 'array',
    ];

    /**
     * 関連
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function approver()
    {
        // 承認者（管理者）
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    /**
     * アクセサ：一覧表示用の日本語ステータス
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status === 'approved' ? '承認済み' : '承認待ち';
    }

    /**
     * スコープ：承認待ち／承認済み
     */
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
