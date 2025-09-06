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
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();

            // 対象の勤怠（履歴保持のため nullOnDelete 推奨。厳密に消したいなら cascadeOnDelete に）
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained('attendances')
                ->nullOnDelete();

            // 申請者
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 提案（修正案）
            $table->timestampTz('proposed_clock_in_at')->nullable();
            $table->timestampTz('proposed_clock_out_at')->nullable();
            $table->json('proposed_breaks')->nullable(); // [{start,end}, …]

            // 申請理由
            $table->text('reason')->nullable();

            // ステータス（enumは環境差が出るのでstringに）
            $table->string('status', 20)->default('pending'); // 'pending' | 'approved'

            // 承認情報（admins テーブルが無ければ一旦コメントアウトでもOK）
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();

            $table->timestamps();

            // よく使う検索キー
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['attendance_id', 'status']);
            $table->index('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
