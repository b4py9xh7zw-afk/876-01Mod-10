<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location',
        'seat_count',
        'description',
        'created_by',
        'status',
    ];

    protected $casts = [
        'seat_count' => 'integer',
        'created_by' => 'integer',
        'status' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function seats()
    {
        return $this->hasMany(ExamSeat::class, 'exam_room_id');
    }

    public function arrangements()
    {
        return $this->hasManyThrough(
            ExamArrangement::class,
            ExamSeat::class,
            'exam_room_id',
            'exam_seat_id'
        );
    }
}
