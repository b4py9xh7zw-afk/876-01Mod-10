<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamSeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_room_id',
        'seat_number',
        'computer_code',
        'row_no',
        'col_no',
        'qr_token',
        'status',
    ];

    protected $casts = [
        'exam_room_id' => 'integer',
        'status' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($seat) {
            if (empty($seat->qr_token)) {
                $seat->qr_token = Str::random(32);
            }
        });
    }

    public function examRoom()
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function arrangements()
    {
        return $this->hasMany(ExamArrangement::class, 'exam_seat_id');
    }

    public function examRecords()
    {
        return $this->hasMany(ExamRecord::class, 'exam_seat_id');
    }

    public function proctorLogs()
    {
        return $this->hasMany(ProctorLog::class, 'exam_seat_id');
    }

    public function currentArrangement($examPaperId)
    {
        return $this->arrangements()->where('exam_paper_id', $examPaperId)->first();
    }
}
