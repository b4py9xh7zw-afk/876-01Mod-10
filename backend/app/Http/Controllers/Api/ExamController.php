<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamArrangement;
use App\Models\ExamPaper;
use App\Models\ExamRecord;
use App\Models\ExamRecordAnswer;
use App\Models\ProctorLog;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamController extends Controller
{
    public function index(Request $request)
    {
        $examPapers = ExamPaper::with('creator')
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'exam_papers' => $examPapers,
        ]);
    }

    public function start(Request $request, ExamPaper $examPaper)
    {
        $existingRecord = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingRecord) {
            return response()->json([
                'message' => '您已经开始这场考试',
                'exam_record' => $existingRecord,
            ]);
        }

        $arrangement = ExamArrangement::where('exam_paper_id', $examPaper->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', [ExamArrangement::STATUS_CHECKED_IN, ExamArrangement::STATUS_EXAMINING])
            ->first();

        if (!$arrangement) {
            $hasArrangement = ExamArrangement::where('exam_paper_id', $examPaper->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$hasArrangement) {
                return response()->json([
                    'message' => '您未被安排在此场考试，请联系教务安排座位',
                ], 403);
            }

            if ($hasArrangement->status === ExamArrangement::STATUS_ASSIGNED) {
                return response()->json([
                    'message' => '您尚未签到，请先到指定座位签到后再开始考试',
                    'arrangement' => [
                        'checkin_code' => $hasArrangement->checkin_code,
                        'seat_info' => $hasArrangement->examSeat ? [
                            'seat_number' => $hasArrangement->examSeat->seat_number,
                            'computer_code' => $hasArrangement->examSeat->computer_code,
                            'room_name' => $hasArrangement->examSeat->examRoom->name ?? null,
                            'room_location' => $hasArrangement->examSeat->examRoom->location ?? null,
                        ] : null,
                    ],
                ], 403);
            }

            return response()->json([
                'message' => '您当前状态无法开始考试，状态：' . ExamArrangement::STATUSES[$hasArrangement->status] ?? $hasArrangement->status,
            ], 403);
        }

        $record = ExamRecord::create([
            'user_id' => $request->user()->id,
            'exam_paper_id' => $examPaper->id,
            'exam_seat_id' => $arrangement->exam_seat_id,
            'exam_arrangement_id' => $arrangement->id,
            'start_time' => now(),
            'status' => 'in_progress',
            'exam_ip' => $request->ip(),
        ]);

        $arrangement->update([
            'status' => ExamArrangement::STATUS_EXAMINING,
        ]);

        ProctorLog::create([
            'exam_paper_id' => $examPaper->id,
            'exam_seat_id' => $arrangement->exam_seat_id,
            'user_id' => $request->user()->id,
            'log_type' => ProctorLog::TYPE_VERIFICATION,
            'content' => "学生 {$request->user()->real_name} 在座位 {$arrangement->examSeat->seat_number} 开始考试，电脑编号：{$arrangement->examSeat->computer_code}",
            'severity' => ProctorLog::SEVERITY_NORMAL,
            'operator_id' => null,
            'operator_ip' => $request->ip(),
        ]);

        $questions = $examPaper->questions()->get();

        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });

        return response()->json([
            'message' => '考试开始',
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
            ],
            'seat_info' => [
                'seat_number' => $arrangement->examSeat->seat_number,
                'computer_code' => $arrangement->examSeat->computer_code,
            ],
            'questions' => $questionsData,
        ]);
    }

    public function getQuestions(Request $request, ExamPaper $examPaper)
    {
        $record = ExamRecord::where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $questions = $examPaper->questions()->get();

        $questionsData = $questions->map(function ($q) {
            return [
                'id' => $q->id,
                'type' => $q->type,
                'title' => $q->title,
                'options' => $q->options,
                'score' => $q->pivot->score,
            ];
        });

        return response()->json([
            'exam_record' => $record,
            'exam_paper' => [
                'id' => $examPaper->id,
                'title' => $examPaper->title,
                'total_time' => $examPaper->total_time,
                'total_score' => $examPaper->total_score,
            ],
            'questions' => $questionsData,
        ]);
    }

    public function submit(Request $request, ExamPaper $examPaper)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|exists:exam_records,id',
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:questions,id',
            'answers.*.answer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $record = ExamRecord::where('id', $request->exam_record_id)
            ->where('user_id', $request->user()->id)
            ->where('exam_paper_id', $examPaper->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $totalScore = 0;
        $questionMap = $examPaper->questions->keyBy('id');

        foreach ($request->answers as $answerData) {
            $question = $questionMap->get($answerData['question_id']);
            if (!$question) {
                continue;
            }

            $isCorrect = $this->checkAnswer($question, $answerData['answer']);
            $score = $isCorrect ? $question->pivot->score : 0;

            ExamRecordAnswer::create([
                'exam_record_id' => $record->id,
                'question_id' => $answerData['question_id'],
                'answer' => $answerData['answer'],
                'is_correct' => $isCorrect,
                'score' => $score,
            ]);

            $totalScore += $score;
        }

        $record->update([
            'end_time' => now(),
            'score' => $totalScore,
            'status' => 'graded',
        ]);

        if ($record->exam_arrangement_id) {
            $arrangement = ExamArrangement::find($record->exam_arrangement_id);
            if ($arrangement) {
                $arrangement->update([
                    'status' => ExamArrangement::STATUS_SUBMITTED,
                ]);
            }
        }

        return response()->json([
            'message' => '提交成功',
            'score' => $totalScore,
            'exam_record' => $record->load('answers'),
        ]);
    }

    public function myRecords(Request $request)
    {
        $records = ExamRecord::with('examPaper')
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage = $request->input('per_page', 15));

        return response()->json([
            'records' => $records,
        ]);
    }

    public function showRecord(Request $request, ExamRecord $record)
    {
        if ($record->user_id !== $request->user()->id) {
            return response()->json(['message' => '无权查看此记录'], 403);
        }

        $record->load(['examPaper.questions', 'answers.question']);

        return response()->json([
            'record' => $record,
        ]);
    }

    protected function checkAnswer(Question $question, string $userAnswer): bool
    {
        $correctAnswer = $question->answer;

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            case 'multiple_choice':
                $userAnswers = explode(',', strtoupper(trim($userAnswer)));
                $correctAnswers = explode(',', strtoupper(trim($correctAnswer)));
                sort($userAnswers);
                sort($correctAnswers);
                return $userAnswers === $correctAnswers;
            case 'fill_blank':
                return strtoupper(trim($userAnswer)) === strtoupper(trim($correctAnswer));
            default:
                return false;
        }
    }
}
