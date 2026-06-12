<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_seat_id')->nullable()->after('exam_paper_id')->comment('座位ID');
            $table->unsignedBigInteger('exam_arrangement_id')->nullable()->after('exam_seat_id')->comment('考试安排ID');
            $table->string('exam_ip', 45)->nullable()->after('status')->comment('考试IP');
            $table->string('exam_mac', 20)->nullable()->after('exam_ip')->comment('考试MAC地址');

            $table->foreign('exam_seat_id')->references('id')->on('exam_seats')->onDelete('set null');
            $table->foreign('exam_arrangement_id')->references('id')->on('exam_arrangements')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->dropForeign(['exam_seat_id']);
            $table->dropForeign(['exam_arrangement_id']);
            $table->dropColumn(['exam_seat_id', 'exam_arrangement_id', 'exam_ip', 'exam_mac']);
        });
    }
};
