<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('机房名称');
            $table->string('code', 50)->unique()->comment('机房编号');
            $table->string('location', 200)->nullable()->comment('位置');
            $table->integer('seat_count')->default(0)->comment('座位数');
            $table->text('description')->nullable()->comment('描述');
            $table->unsignedBigInteger('created_by')->comment('创建人');
            $table->boolean('status')->default(true)->comment('状态');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_rooms');
    }
};
