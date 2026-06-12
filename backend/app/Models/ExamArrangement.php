<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ExamArrangement extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_paper_id',
        'exam_seat_id',
        'user_id',
        'checkin_code',
        'checkin_time',
        'checkin_ip',
        'checkin_mac',
        'checkin_operator_id',
        'status',
        'remark',
    ];

    protected $casts = [
        'exam_paper_id' => 'integer',
        'exam_seat_id' => 'integer',
        'user_id' => 'integer',
        'checkin_time' => 'datetime',
        'checkin_operator_id' => 'integer',
    ];

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_EXAMINING = 'examining';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ABSENT = 'absent';

    public const STATUSES = [
        self::STATUS_ASSIGNED => '已安排',
        self::STATUS_CHECKED_IN => '已签到',
        self::STATUS_EXAMINING => '考试中',
        self::STATUS_SUBMITTED => '已交卷',
        self::STATUS_ABSENT => '缺考',
    ];

    protected static function booted()
    {
        static::creating(function ($arrangement) {
            if (empty($arrangement->checkin_code)) {
                $arrangement->checkin_code = Str::random(16);
            }
            if (empty($arrangement->status)) {
                $arrangement->status = self::STATUS_ASSIGNED;
            }
        });
    }

    public function examPaper()
    {
        return $this->belongsTo(ExamPaper::class, 'exam_paper_id');
    }

    public function examSeat()
    {
        return $this->belongsTo(ExamSeat::class, 'exam_seat_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function checkinOperator()
    {
        return $this->belongsTo(User::class, 'checkin_operator_id');
    }

    public function examRecord()
    {
        return $this->hasOne(ExamRecord::class, 'exam_arrangement_id');
    }

    public function seatChangeRecords()
    {
        return $this->hasMany(SeatChangeRecord::class, 'exam_arrangement_id');
    }

    public function proctorLogs()
    {
        return $this->hasMany(ProctorLog::class, 'user_id', 'user_id')
            ->where('exam_paper_id', $this->exam_paper_id);
    }

    public function getStatusLabelAttribute()
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isCheckedIn()
    {
        return in_array($this->status, [self::STATUS_CHECKED_IN, self::STATUS_EXAMINING, self::STATUS_SUBMITTED]);
    }

    public function canStartExam()
    {
        return in_array($this->status, [self::STATUS_CHECKED_IN]);
    }
}
