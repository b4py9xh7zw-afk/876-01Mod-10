<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatChangeRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_arrangement_id',
        'exam_paper_id',
        'user_id',
        'old_seat_id',
        'new_seat_id',
        'reason',
        'operator_id',
    ];

    protected $casts = [
        'exam_arrangement_id' => 'integer',
        'exam_paper_id' => 'integer',
        'user_id' => 'integer',
        'old_seat_id' => 'integer',
        'new_seat_id' => 'integer',
        'operator_id' => 'integer',
    ];

    public function examArrangement()
    {
        return $this->belongsTo(ExamArrangement::class, 'exam_arrangement_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function oldSeat()
    {
        return $this->belongsTo(ExamSeat::class, 'old_seat_id');
    }

    public function newSeat()
    {
        return $this->belongsTo(ExamSeat::class, 'new_seat_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
