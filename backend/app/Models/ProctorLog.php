<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProctorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_paper_id',
        'exam_seat_id',
        'user_id',
        'log_type',
        'content',
        'severity',
        'operator_id',
        'operator_ip',
    ];

    protected $casts = [
        'exam_paper_id' => 'integer',
        'exam_seat_id' => 'integer',
        'user_id' => 'integer',
        'operator_id' => 'integer',
    ];

    public const TYPE_CHECKIN = 'checkin';
    public const TYPE_SEAT_CHANGE = 'seat_change';
    public const TYPE_SUSPICIOUS = 'suspicious';
    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CHECKIN => '签到',
        self::TYPE_SEAT_CHANGE => '换座',
        self::TYPE_SUSPICIOUS => '异常',
        self::TYPE_VERIFICATION => '身份核验',
        self::TYPE_OTHER => '其他',
    ];

    public const SEVERITY_NORMAL = 'normal';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_DANGER = 'danger';

    public const SEVERITIES = [
        self::SEVERITY_NORMAL => '普通',
        self::SEVERITY_WARNING => '警告',
        self::SEVERITY_DANGER => '严重',
    ];

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

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function getTypeLabelAttribute()
    {
        return self::TYPES[$this->log_type] ?? $this->log_type;
    }

    public function getSeverityLabelAttribute()
    {
        return self::SEVERITIES[$this->severity] ?? $this->severity;
    }
}
