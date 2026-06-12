<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_paper_id',
        'exam_seat_id',
        'exam_arrangement_id',
        'start_time',
        'end_time',
        'score',
        'status',
        'exam_ip',
        'exam_mac',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'exam_paper_id' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'score' => 'decimal:2',
        'status' => 'string',
    ];

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    public const STATUSES = [
        self::STATUS_IN_PROGRESS => '进行中',
        self::STATUS_SUBMITTED => '已提交',
        self::STATUS_GRADED => '已评分',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function answers()
    {
        return $this->hasMany(ExamRecordAnswer::class, 'exam_record_id');
    }

    public function examSeat()
    {
        return $this->belongsTo(ExamSeat::class, 'exam_seat_id');
    }

    public function examArrangement()
    {
        return $this->belongsTo(ExamArrangement::class, 'exam_arrangement_id');
    }
}
