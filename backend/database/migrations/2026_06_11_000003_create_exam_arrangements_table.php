<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_arrangements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_paper_id')->comment('试卷ID');
            $table->unsignedBigInteger('exam_seat_id')->comment('座位ID');
            $table->unsignedBigInteger('user_id')->comment('学生ID');
            $table->string('checkin_code', 32)->nullable()->unique()->comment('签到码');
            $table->timestamp('checkin_time')->nullable()->comment('签到时间');
            $table->string('checkin_ip', 45)->nullable()->comment('签到IP');
            $table->string('checkin_mac', 20)->nullable()->comment('签到MAC地址');
            $table->unsignedBigInteger('checkin_operator_id')->nullable()->comment('签到操作人');
            $table->string('status', 20)->default('assigned')->comment('状态：assigned/checked_in/examining/submitted/absent');
            $table->text('remark')->nullable()->comment('备注');
            $table->timestamps();

            $table->foreign('exam_paper_id')->references('id')->on('exam_papers')->onDelete('cascade');
            $table->foreign('exam_seat_id')->references('id')->on('exam_seats')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('checkin_operator_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['exam_paper_id', 'user_id']);
            $table->unique(['exam_paper_id', 'exam_seat_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_arrangements');
    }
};
