<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctor_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_paper_id')->comment('试卷ID');
            $table->unsignedBigInteger('exam_seat_id')->nullable()->comment('座位ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('学生ID');
            $table->string('log_type', 30)->comment('日志类型：checkin/seat_change/suspicious/verification/other');
            $table->text('content')->comment('日志内容');
            $table->string('severity', 20)->default('normal')->comment('级别：normal/warning/danger');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->string('operator_ip', 45)->nullable()->comment('操作人IP');
            $table->timestamps();

            $table->foreign('exam_paper_id')->references('id')->on('exam_papers')->onDelete('cascade');
            $table->foreign('exam_seat_id')->references('id')->on('exam_seats')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('set null');
            $table->index('log_type');
            $table->index('severity');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctor_logs');
    }
};
