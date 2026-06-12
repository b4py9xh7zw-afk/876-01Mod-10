<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamArrangement;
use App\Models\ExamPaper;
use App\Models\ExamRoom;
use App\Models\ExamSeat;
use App\Models\ProctorLog;
use App\Models\SeatChangeRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExamArrangementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $query = ExamArrangement::with([
            'examPaper:id,title',
            'examSeat' => function ($q) {
                $q->with('examRoom:id,name,code,location');
            },
            'user:id,username,real_name,email',
        ]);

        if ($request->filled('exam_paper_id')) {
            $query->where('exam_paper_id', $request->input('exam_paper_id'));
        }

        if ($request->filled('exam_room_id')) {
            $query->whereHas('examSeat', function ($q) use ($request) {
                $q->where('exam_room_id', $request->input('exam_room_id'));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('user', function ($subQ) use ($keyword) {
                    $subQ->where('username', 'like', $keyword)
                        ->orWhere('real_name', 'like', $keyword);
                })
                    ->orWhereHas('examSeat', function ($subQ) use ($keyword) {
                        $subQ->where('seat_number', 'like', $keyword)
                            ->orWhere('computer_code', 'like', $keyword);
                    });
            });
        }

        $arrangements = $query->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'arrangements' => $arrangements,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'exam_paper_id' => 'required|exists:exam_papers,id',
            'exam_seat_id' => 'required|exists:exam_seats,id',
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::findOrFail($request->input('user_id'));
        if (!$user->isStudent()) {
            return response()->json(['message' => '只能为学生安排考试座位'], 422);
        }

        $seatUsed = ExamArrangement::where('exam_paper_id', $request->input('exam_paper_id'))
            ->where('exam_seat_id', $request->input('exam_seat_id'))
            ->whereIn('status', [
                ExamArrangement::STATUS_ASSIGNED,
                ExamArrangement::STATUS_CHECKED_IN,
                ExamArrangement::STATUS_EXAMINING,
            ])
            ->exists();

        if ($seatUsed) {
            return response()->json(['message' => '该座位在此考试中已被占用'], 422);
        }

        $userHasSeat = ExamArrangement::where('exam_paper_id', $request->input('exam_paper_id'))
            ->where('user_id', $request->input('user_id'))
            ->whereIn('status', [
                ExamArrangement::STATUS_ASSIGNED,
                ExamArrangement::STATUS_CHECKED_IN,
                ExamArrangement::STATUS_EXAMINING,
            ])
            ->exists();

        if ($userHasSeat) {
            return response()->json(['message' => '该学生在此考试中已有座位安排'], 422);
        }

        $arrangement = ExamArrangement::create([
            'exam_paper_id' => $request->input('exam_paper_id'),
            'exam_seat_id' => $request->input('exam_seat_id'),
            'user_id' => $request->input('user_id'),
            'status' => ExamArrangement::STATUS_ASSIGNED,
        ]);

        return response()->json([
            'message' => '座位安排成功',
            'arrangement' => $arrangement,
        ], 201);
    }

    public function import(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'exam_paper_id' => 'required|exists:exam_papers,id',
            'exam_room_id' => 'required|exists:exam_rooms,id',
            'arrangements' => 'required|array',
            'arrangements.*.seat_number' => 'required|string',
            'arrangements.*.user_identifier' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $examPaperId = $request->input('exam_paper_id');
        $examRoomId = $request->input('exam_room_id');

        $roomSeats = ExamSeat::where('exam_room_id', $examRoomId)
            ->get()
            ->keyBy('seat_number');

        DB::beginTransaction();
        try {
            $created = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->input('arrangements') as $index => $item) {
                $seatNumber = trim($item['seat_number']);
                $userIdentifier = trim($item['user_identifier']);

                $seat = $roomSeats->get($seatNumber);
                if (!$seat) {
                    $skipped++;
                    $errors[] = "第{$index}行：座位号 {$seatNumber} 不存在";
                    continue;
                }

                $user = User::where(function ($q) use ($userIdentifier) {
                    $q->where('username', $userIdentifier)
                        ->orWhere('email', $userIdentifier);
                })->first();

                if (!$user) {
                    $skipped++;
                    $errors[] = "第{$index}行：用户 {$userIdentifier} 不存在";
                    continue;
                }

                if (!$user->isStudent()) {
                    $skipped++;
                    $errors[] = "第{$index}行：用户 {$userIdentifier} 不是学生";
                    continue;
                }

                $seatUsed = ExamArrangement::where('exam_paper_id', $examPaperId)
                    ->where('exam_seat_id', $seat->id)
                    ->whereIn('status', [
                        ExamArrangement::STATUS_ASSIGNED,
                        ExamArrangement::STATUS_CHECKED_IN,
                        ExamArrangement::STATUS_EXAMINING,
                    ])
                    ->exists();

                if ($seatUsed) {
                    $skipped++;
                    $errors[] = "第{$index}行：座位 {$seatNumber} 已被占用";
                    continue;
                }

                $userHasSeat = ExamArrangement::where('exam_paper_id', $examPaperId)
                    ->where('user_id', $user->id)
                    ->whereIn('status', [
                        ExamArrangement::STATUS_ASSIGNED,
                        ExamArrangement::STATUS_CHECKED_IN,
                        ExamArrangement::STATUS_EXAMINING,
                    ])
                    ->exists();

                if ($userHasSeat) {
                    $skipped++;
                    $errors[] = "第{$index}行：学生 {$user->real_name} 已有安排";
                    continue;
                }

                ExamArrangement::create([
                    'exam_paper_id' => $examPaperId,
                    'exam_seat_id' => $seat->id,
                    'user_id' => $user->id,
                    'status' => ExamArrangement::STATUS_ASSIGNED,
                ]);
                $created++;
            }

            DB::commit();

            return response()->json([
                'message' => "导入完成，成功{$created}条，跳过{$skipped}条",
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '导入失败：' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, ExamArrangement $arrangement)
    {
        $this->authorizeAdminOrTeacher($request);

        if (in_array($arrangement->status, [
            ExamArrangement::STATUS_CHECKED_IN,
            ExamArrangement::STATUS_EXAMINING,
        ])) {
            return response()->json(['message' => '学生已签到或考试中，无法取消安排'], 422);
        }

        $arrangement->delete();

        return response()->json([
            'message' => '座位安排已取消',
        ]);
    }

    public function checkin(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'checkin_code' => 'required_without:arrangement_id|string',
            'arrangement_id' => 'required_without:checkin_code|exists:exam_arrangements,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->filled('checkin_code')) {
            $arrangement = ExamArrangement::where('checkin_code', $request->input('checkin_code'))
                ->first();
            if (!$arrangement) {
                return response()->json(['message' => '签到码无效'], 404);
            }
        } else {
            $arrangement = ExamArrangement::findOrFail($request->input('arrangement_id'));
        }

        if ($arrangement->status === ExamArrangement::STATUS_ASSIGNED) {
            $arrangement->update([
                'status' => ExamArrangement::STATUS_CHECKED_IN,
                'checkin_time' => now(),
                'checkin_ip' => $request->ip(),
                'checkin_operator_id' => $request->user()->id,
            ]);

            ProctorLog::create([
                'exam_paper_id' => $arrangement->exam_paper_id,
                'exam_seat_id' => $arrangement->exam_seat_id,
                'user_id' => $arrangement->user_id,
                'log_type' => ProctorLog::TYPE_CHECKIN,
                'content' => "学生 {$arrangement->user->real_name} 在座位 {$arrangement->examSeat->seat_number} 签到成功，电脑编号：{$arrangement->examSeat->computer_code}",
                'severity' => ProctorLog::SEVERITY_NORMAL,
                'operator_id' => $request->user()->id,
                'operator_ip' => $request->ip(),
            ]);
        }

        $arrangement->load([
            'examPaper:id,title,total_time',
            'examSeat' => function ($q) {
                $q->with('examRoom:id,name,code,location');
            },
            'user:id,username,real_name,email',
        ]);

        return response()->json([
            'message' => '签到成功',
            'arrangement' => $arrangement,
        ]);
    }

    public function selfCheckin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'checkin_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $arrangement = ExamArrangement::where('checkin_code', $request->input('checkin_code'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$arrangement) {
            return response()->json(['message' => '签到码无效或不属于您'], 404);
        }

        if ($arrangement->status === ExamArrangement::STATUS_ASSIGNED) {
            $arrangement->update([
                'status' => ExamArrangement::STATUS_CHECKED_IN,
                'checkin_time' => now(),
                'checkin_ip' => $request->ip(),
            ]);

            ProctorLog::create([
                'exam_paper_id' => $arrangement->exam_paper_id,
                'exam_seat_id' => $arrangement->exam_seat_id,
                'user_id' => $arrangement->user_id,
                'log_type' => ProctorLog::TYPE_CHECKIN,
                'content' => "学生 {$arrangement->user->real_name} 自助签到成功，座位 {$arrangement->examSeat->seat_number}",
                'severity' => ProctorLog::SEVERITY_NORMAL,
                'operator_id' => null,
                'operator_ip' => $request->ip(),
            ]);
        }

        $arrangement->load([
            'examPaper:id,title,total_time',
            'examSeat' => function ($q) {
                $q->with('examRoom:id,name,code,location');
            },
        ]);

        return response()->json([
            'message' => '签到成功',
            'arrangement' => $arrangement,
        ]);
    }

    public function changeSeat(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'arrangement_id' => 'required|exists:exam_arrangements,id',
            'new_seat_id' => 'required|exists:exam_seats,id',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $arrangement = ExamArrangement::findOrFail($request->input('arrangement_id'));
        $newSeat = ExamSeat::findOrFail($request->input('new_seat_id'));
        $oldSeatId = $arrangement->exam_seat_id;

        if ($oldSeatId === $newSeat->id) {
            return response()->json(['message' => '新旧座位不能相同'], 422);
        }

        $seatUsed = ExamArrangement::where('exam_paper_id', $arrangement->exam_paper_id)
            ->where('exam_seat_id', $newSeat->id)
            ->where('id', '!=', $arrangement->id)
            ->whereIn('status', [
                ExamArrangement::STATUS_ASSIGNED,
                ExamArrangement::STATUS_CHECKED_IN,
                ExamArrangement::STATUS_EXAMINING,
            ])
            ->exists();

        if ($seatUsed) {
            return response()->json(['message' => '目标座位已被占用'], 422);
        }

        DB::beginTransaction();
        try {
            $oldSeat = ExamSeat::findOrFail($oldSeatId);

            SeatChangeRecord::create([
                'exam_arrangement_id' => $arrangement->id,
                'exam_paper_id' => $arrangement->exam_paper_id,
                'user_id' => $arrangement->user_id,
                'old_seat_id' => $oldSeatId,
                'new_seat_id' => $newSeat->id,
                'reason' => $request->input('reason'),
                'operator_id' => $request->user()->id,
            ]);

            $arrangement->update([
                'exam_seat_id' => $newSeat->id,
            ]);

            ProctorLog::create([
                'exam_paper_id' => $arrangement->exam_paper_id,
                'exam_seat_id' => $newSeat->id,
                'user_id' => $arrangement->user_id,
                'log_type' => ProctorLog::TYPE_SEAT_CHANGE,
                'content' => "学生 {$arrangement->user->real_name} 换座：{$oldSeat->seat_number}({$oldSeat->computer_code}) → {$newSeat->seat_number}({$newSeat->computer_code})，原因：{$request->input('reason')}",
                'severity' => ProctorLog::SEVERITY_WARNING,
                'operator_id' => $request->user()->id,
                'operator_ip' => $request->ip(),
            ]);

            DB::commit();

            return response()->json([
                'message' => '换座成功',
                'arrangement' => $arrangement->load(['examSeat.examRoom']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '换座失败：' . $e->getMessage()], 500);
        }
    }

    public function myArrangements(Request $request)
    {
        $arrangements = ExamArrangement::with([
            'examPaper:id,title,total_time,total_score,description',
            'examSeat' => function ($q) {
                $q->with('examRoom:id,name,code,location');
            },
        ])
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'arrangements' => $arrangements,
        ]);
    }

    protected function authorizeAdminOrTeacher(Request $request)
    {
        if (!$request->user()->isAdmin() && !$request->user()->isTeacher()) {
            abort(403, '无权执行此操作');
        }
    }
}
