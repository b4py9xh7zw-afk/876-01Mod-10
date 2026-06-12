<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_seats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_room_id')->comment('机房ID');
            $table->string('seat_number', 20)->comment('座位号');
            $table->string('computer_code', 50)->comment('电脑编号');
            $table->string('row_no', 10)->nullable()->comment('行号');
            $table->string('col_no', 10)->nullable()->comment('列号');
            $table->text('qr_token')->nullable()->comment('二维码Token');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamps();

            $table->foreign('exam_room_id')->references('id')->on('exam_rooms')->onDelete('cascade');
            $table->unique(['exam_room_id', 'seat_number']);
            $table->unique(['exam_room_id', 'computer_code']);
            $table->unique('qr_token');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_seats');
    }
};
