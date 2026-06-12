<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_change_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_arrangement_id')->comment('考试安排ID');
            $table->unsignedBigInteger('exam_paper_id')->comment('试卷ID');
            $table->unsignedBigInteger('user_id')->comment('学生ID');
            $table->unsignedBigInteger('old_seat_id')->comment('原座位ID');
            $table->unsignedBigInteger('new_seat_id')->comment('新座位ID');
            $table->text('reason')->comment('换座原因');
            $table->unsignedBigInteger('operator_id')->comment('操作人ID');
            $table->timestamps();

            $table->foreign('exam_arrangement_id')->references('id')->on('exam_arrangements')->onDelete('cascade');
            $table->foreign('exam_paper_id')->references('id')->on('exam_papers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('old_seat_id')->references('id')->on('exam_seats')->onDelete('cascade');
            $table->foreign('new_seat_id')->references('id')->on('exam_seats')->onDelete('cascade');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('exam_paper_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_change_records');
    }
};
