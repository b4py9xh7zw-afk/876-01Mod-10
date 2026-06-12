<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamArrangement;
use App\Models\ExamRecord;
use App\Models\ExamSeat;
use App\Models\ProctorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProctorController extends Controller
{
    public function scanSeat(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'qr_token' => 'required|string',
            'exam_paper_id' => 'sometimes|exists:exam_papers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $seat = ExamSeat::where('qr_token', $request->input('qr_token'))
            ->with(['examRoom:id,name,code,location'])
            ->first();

        if (!$seat) {
            return response()->json(['message' => '座位二维码无效'], 404);
        }

        $arrangementQuery = ExamArrangement::with([
            'user:id,username,real_name,email',
            'examPaper:id,title,total_time',
            'examRecord' => function ($q) {
                $q->select('id', 'exam_paper_id', 'user_id', 'exam_seat_id', 'exam_arrangement_id', 'start_time', 'end_time', 'score', 'status');
            },
        ])
            ->where('exam_seat_id', $seat->id);

        if ($request->filled('exam_paper_id')) {
            $arrangementQuery->where('exam_paper_id', $request->input('exam_paper_id'));
        } else {
            $arrangementQuery->whereIn('status', [
                ExamArrangement::STATUS_ASSIGNED,
                ExamArrangement::STATUS_CHECKED_IN,
                ExamArrangement::STATUS_EXAMINING,
            ]);
        }

        $arrangement = $arrangementQuery->orderBy('id', 'desc')->first();

        $examProgress = null;
        $anomalyCount = 0;
        $seatChangeHistory = [];

        if ($arrangement) {
            if ($arrangement->examRecord && $arrangement->examRecord->status === ExamRecord::STATUS_IN_PROGRESS) {
                $paper = $arrangement->examPaper;
                $startTime = $arrangement->examRecord->start_time;
                $elapsed = now()->diffInSeconds($startTime);
                $totalSeconds = $paper->total_time * 60;
                $progress = min(100, round(($elapsed / $totalSeconds) * 100, 1));
                $remaining = max(0, $totalSeconds - $elapsed);

                $examProgress = [
                    'status' => $arrangement->examRecord->status,
                    'status_label' => ExamRecord::STATUSES[$arrangement->examRecord->status] ?? $arrangement->examRecord->status,
                    'progress_percent' => $progress,
                    'elapsed_seconds' => $elapsed,
                    'remaining_seconds' => $remaining,
                    'total_seconds' => $totalSeconds,
                    'start_time' => $startTime,
                ];
            } elseif ($arrangement->examRecord) {
                $examProgress = [
                    'status' => $arrangement->examRecord->status,
                    'status_label' => ExamRecord::STATUSES[$arrangement->examRecord->status] ?? $arrangement->examRecord->status,
                    'score' => $arrangement->examRecord->score,
                    'end_time' => $arrangement->examRecord->end_time,
                ];
            }

            $anomalyCount = ProctorLog::where('exam_paper_id', $arrangement->exam_paper_id)
                ->where('user_id', $arrangement->user_id)
                ->whereIn('log_type', [ProctorLog::TYPE_SUSPICIOUS, ProctorLog::TYPE_SEAT_CHANGE])
                ->count();

            $seatChangeHistory = $arrangement->seatChangeRecords()
                ->with([
                    'oldSeat:id,seat_number,computer_code',
                    'newSeat:id,seat_number,computer_code',
                    'operator:id,real_name',
                ])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'old_seat' => $record->oldSeat ? "{$record->oldSeat->seat_number} ({$record->oldSeat->computer_code})" : null,
                        'new_seat' => $record->newSeat ? "{$record->newSeat->seat_number} ({$record->newSeat->computer_code})" : null,
                        'reason' => $record->reason,
                        'operator' => $record->operator->real_name ?? null,
                        'created_at' => $record->created_at,
                    ];
                });
        }

        $anomalyLogs = ProctorLog::where('exam_seat_id', $seat->id)
            ->when($arrangement, function ($q) use ($arrangement) {
                $q->orWhere('user_id', $arrangement->user_id);
            })
            ->when($request->filled('exam_paper_id'), function ($q) use ($request) {
                $q->where('exam_paper_id', $request->input('exam_paper_id'));
            })
            ->with(['operator:id,real_name'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'log_type' => $log->log_type,
                    'log_type_label' => $log->type_label,
                    'severity' => $log->severity,
                    'severity_label' => $log->severity_label,
                    'content' => $log->content,
                    'operator' => $log->operator->real_name ?? '系统',
                    'created_at' => $log->created_at,
                ];
            });

        return response()->json([
            'seat' => [
                'id' => $seat->id,
                'seat_number' => $seat->seat_number,
                'computer_code' => $seat->computer_code,
                'row_no' => $seat->row_no,
                'col_no' => $seat->col_no,
                'exam_room' => $seat->examRoom ? [
                    'id' => $seat->examRoom->id,
                    'name' => $seat->examRoom->name,
                    'code' => $seat->examRoom->code,
                    'location' => $seat->examRoom->location,
                ] : null,
            ],
            'arrangement' => $arrangement ? [
                'id' => $arrangement->id,
                'status' => $arrangement->status,
                'status_label' => $arrangement->status_label,
                'checkin_time' => $arrangement->checkin_time,
                'checkin_code' => $arrangement->checkin_code,
                'student' => $arrangement->user ? [
                    'id' => $arrangement->user->id,
                    'username' => $arrangement->user->username,
                    'real_name' => $arrangement->user->real_name,
                    'email' => $arrangement->user->email,
                ] : null,
                'exam_paper' => $arrangement->examPaper ? [
                    'id' => $arrangement->examPaper->id,
                    'title' => $arrangement->examPaper->title,
                ] : null,
            ] : null,
            'exam_progress' => $examProgress,
            'anomaly_count' => $anomalyCount,
            'seat_change_history' => $seatChangeHistory,
            'anomaly_logs' => $anomalyLogs,
        ]);
    }

    public function logs(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $query = ProctorLog::with([
            'examPaper:id,title',
            'examSeat' => function ($q) {
                $q->with('examRoom:id,name,code');
            },
            'user:id,username,real_name',
            'operator:id,username,real_name',
        ]);

        if ($request->filled('exam_paper_id')) {
            $query->where('exam_paper_id', $request->input('exam_paper_id'));
        }

        if ($request->filled('log_type')) {
            $query->where('log_type', $request->input('log_type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($subQ) use ($keyword) {
                    $subQ->where('real_name', 'like', $keyword);
                })
                    ->orWhereHas('examSeat', function ($subQ) use ($keyword) {
                        $subQ->where('seat_number', 'like', $keyword);
                    })
                    ->orWhere('content', 'like', $keyword);
            });
        }

        $logs = $query->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 20));

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'exam_paper' => $log->examPaper ? [
                    'id' => $log->examPaper->id,
                    'title' => $log->examPaper->title,
                ] : null,
                'exam_seat' => $log->examSeat ? [
                    'id' => $log->examSeat->id,
                    'seat_number' => $log->examSeat->seat_number,
                    'computer_code' => $log->examSeat->computer_code,
                    'exam_room' => $log->examSeat->examRoom ? [
                        'name' => $log->examSeat->examRoom->name,
                    ] : null,
                ] : null,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'real_name' => $log->user->real_name,
                ] : null,
                'log_type' => $log->log_type,
                'log_type_label' => $log->type_label,
                'severity' => $log->severity,
                'severity_label' => $log->severity_label,
                'content' => $log->content,
                'operator' => $log->operator ? [
                    'id' => $log->operator->id,
                    'real_name' => $log->operator->real_name,
                ] : null,
                'operator_ip' => $log->operator_ip,
                'created_at' => $log->created_at,
            ];
        });

        return response()->json([
            'logs' => $logs,
            'stats' => [
                'total' => $query->count(),
                'normal' => (clone $query)->where('severity', ProctorLog::SEVERITY_NORMAL)->count(),
                'warning' => (clone $query)->where('severity', ProctorLog::SEVERITY_WARNING)->count(),
                'danger' => (clone $query)->where('severity', ProctorLog::SEVERITY_DANGER)->count(),
            ],
        ]);
    }

    public function addLog(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'exam_paper_id' => 'required|exists:exam_papers,id',
            'exam_seat_id' => 'nullable|exists:exam_seats,id',
            'user_id' => 'nullable|exists:users,id',
            'log_type' => 'required|string|in:checkin,seat_change,suspicious,verification,other',
            'content' => 'required|string|max:1000',
            'severity' => 'nullable|string|in:normal,warning,danger',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $log = ProctorLog::create([
            'exam_paper_id' => $request->input('exam_paper_id'),
            'exam_seat_id' => $request->input('exam_seat_id'),
            'user_id' => $request->input('user_id'),
            'log_type' => $request->input('log_type'),
            'content' => $request->input('content'),
            'severity' => $request->input('severity', ProctorLog::SEVERITY_NORMAL),
            'operator_id' => $request->user()->id,
            'operator_ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => '监考日志记录成功',
            'log' => $log,
        ], 201);
    }

    public function overview(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'exam_paper_id' => 'required|exists:exam_papers,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examPaperId = $request->input('exam_paper_id');

        $arrangements = ExamArrangement::where('exam_paper_id', $examPaperId)->count();
        $checkedIn = ExamArrangement::where('exam_paper_id', $examPaperId)
            ->where('status', ExamArrangement::STATUS_CHECKED_IN)
            ->count();
        $examining = ExamArrangement::where('exam_paper_id', $examPaperId)
            ->where('status', ExamArrangement::STATUS_EXAMINING)
            ->count();
        $submitted = ExamArrangement::where('exam_paper_id', $examPaperId)
            ->where('status', ExamArrangement::STATUS_SUBMITTED)
            ->count();
        $absent = ExamArrangement::where('exam_paper_id', $examPaperId)
            ->where('status', ExamArrangement::STATUS_ABSENT)
            ->count();
        $assigned = ExamArrangement::where('exam_paper_id', $examPaperId)
            ->where('status', ExamArrangement::STATUS_ASSIGNED)
            ->count();

        $anomalyLogs = ProctorLog::where('exam_paper_id', $examPaperId)
            ->whereIn('log_type', [ProctorLog::TYPE_SUSPICIOUS, ProctorLog::TYPE_SEAT_CHANGE])
            ->count();

        $seatChanges = ProctorLog::where('exam_paper_id', $examPaperId)
            ->where('log_type', ProctorLog::TYPE_SEAT_CHANGE)
            ->count();

        return response()->json([
            'overview' => [
                'total_arrangements' => $arrangements,
                'assigned' => $assigned,
                'checked_in' => $checkedIn,
                'examining' => $examining,
                'submitted' => $submitted,
                'absent' => $absent,
                'checkin_rate' => $arrangements > 0 ? round((($checkedIn + $examining + $submitted) / $arrangements) * 100, 1) : 0,
                'anomaly_logs' => $anomalyLogs,
                'seat_changes' => $seatChanges,
            ],
        ]);
    }

    protected function authorizeAdminOrTeacher(Request $request)
    {
        if (!$request->user()->isAdmin() && !$request->user()->isTeacher()) {
            abort(403, '无权执行此操作');
        }
    }
}
