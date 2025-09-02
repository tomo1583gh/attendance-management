<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stamp_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // 申請者
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete(); // 対象の勤怠
            $table->enum('status', ['pending', 'approved'])->default('pending'); // 承認状態：pending(承認待ち) / approved(承認済み)
            $table->string('reason', 255)->nullable(); // 申請理由（スクショの「申請理由」列）
            $table->json('payload')->nullable(); // 申請内容（出勤・退勤・休憩・備考などの差分をJSONで保持）
            $table->timestamps();
            // よく使う検索キー
            $table->index(['user_id', 'status']);
            $table->index(['attendance_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stamp_correction_requests');
    }
};
