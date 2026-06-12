<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamArrangement;
use App\Models\ExamRoom;
use App\Models\ExamSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExamRoomController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $query = ExamRoom::with('creator');

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->input('keyword') . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('code', 'like', $keyword)
                    ->orWhere('location', 'like', $keyword);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        $rooms = $query->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'rooms' => $rooms,
        ]);
    }

    public function all(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $rooms = ExamRoom::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'location', 'seat_count']);

        return response()->json([
            'rooms' => $rooms,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:exam_rooms,code',
            'location' => 'nullable|string|max:200',
            'seat_count' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = ExamRoom::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'location' => $request->input('location'),
            'seat_count' => $request->input('seat_count', 0),
            'description' => $request->input('description'),
            'created_by' => $request->user()->id,
            'status' => $request->input('status', true),
        ]);

        return response()->json([
            'message' => '机房创建成功',
            'room' => $room,
        ], 201);
    }

    public function show(Request $request, ExamRoom $room)
    {
        $this->authorizeAdminOrTeacher($request);

        $room->load(['seats' => function ($q) {
            $q->orderBy('row_no')->orderBy('col_no')->orderBy('seat_number');
        }]);

        return response()->json([
            'room' => $room,
        ]);
    }

    public function update(Request $request, ExamRoom $room)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'code' => 'sometimes|required|string|max:50|unique:exam_rooms,code,' . $room->id,
            'location' => 'nullable|string|max:200',
            'seat_count' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room->update($request->only([
            'name', 'code', 'location', 'seat_count', 'description', 'status'
        ]));

        return response()->json([
            'message' => '机房更新成功',
            'room' => $room,
        ]);
    }

    public function destroy(Request $request, ExamRoom $room)
    {
        $this->authorizeAdminOrTeacher($request);

        if ($room->arrangements()->count() > 0) {
            return response()->json(['message' => '该机房已有考试安排，无法删除'], 422);
        }

        $room->delete();

        return response()->json([
            'message' => '机房删除成功',
        ]);
    }

    public function seats(Request $request, ExamRoom $room)
    {
        $this->authorizeAdminOrTeacher($request);

        $seats = $room->seats()
            ->orderBy('row_no')
            ->orderBy('col_no')
            ->orderBy('seat_number')
            ->get();

        return response()->json([
            'seats' => $seats,
        ]);
    }

    public function addSeats(Request $request, ExamRoom $room)
    {
        $this->authorizeAdminOrTeacher($request);

        $validator = Validator::make($request->all(), [
            'seats' => 'required|array',
            'seats.*.seat_number' => 'required|string|max:20',
            'seats.*.computer_code' => 'required|string|max:50',
            'seats.*.row_no' => 'nullable|string|max:10',
            'seats.*.col_no' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($request->input('seats') as $index => $seatData) {
            $seatNumber = trim($seatData['seat_number']);
            $computerCode = trim($seatData['computer_code']);

            $exists = ExamSeat::where('exam_room_id', $room->id)
                ->where(function ($q) use ($seatNumber, $computerCode) {
                    $q->where('seat_number', $seatNumber)
                        ->orWhere('computer_code', $computerCode);
                })
                ->exists();

            if ($exists) {
                $skipped++;
                $errors[] = "第{$index}行：座位号或电脑编号已存在";
                continue;
            }

            ExamSeat::create([
                'exam_room_id' => $room->id,
                'seat_number' => $seatNumber,
                'computer_code' => $computerCode,
                'row_no' => $seatData['row_no'] ?? null,
                'col_no' => $seatData['col_no'] ?? null,
                'qr_token' => Str::random(32),
                'status' => true,
            ]);
            $created++;
        }

        $room->update(['seat_count' => $room->seats()->count()]);

        return response()->json([
            'message' => "座位导入完成，成功{$created}条，跳过{$skipped}条",
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function removeSeat(Request $request, ExamRoom $room, ExamSeat $seat)
    {
        $this->authorizeAdminOrTeacher($request);

        if ($seat->exam_room_id !== $room->id) {
            return response()->json(['message' => '座位不属于该机房'], 422);
        }

        $hasArrangement = $seat->arrangements()->whereIn('status', [
            ExamArrangement::STATUS_ASSIGNED,
            ExamArrangement::STATUS_CHECKED_IN,
            ExamArrangement::STATUS_EXAMINING,
        ])->exists();

        if ($hasArrangement) {
            return response()->json(['message' => '该座位已有进行中的考试安排，无法删除'], 422);
        }

        $seat->delete();
        $room->update(['seat_count' => $room->seats()->count()]);

        return response()->json([
            'message' => '座位删除成功',
        ]);
    }

    protected function authorizeAdminOrTeacher(Request $request)
    {
        if (!$request->user()->isAdmin() && !$request->user()->isTeacher()) {
            abort(403, '无权执行此操作');
        }
    }
}
