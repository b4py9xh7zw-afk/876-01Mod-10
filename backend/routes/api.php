<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamArrangementController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\ExamPaperController;
use App\Http\Controllers\Api\ExamRoomController;
use App\Http\Controllers\Api\ProctorController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ScoreController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['api', 'auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/students', [AuthController::class, 'students']);
    });

    Route::prefix('questions')->group(function () {
        Route::get('/', [QuestionController::class, 'index']);
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/categories', [QuestionController::class, 'categories']);
        Route::post('/categories', [QuestionController::class, 'storeCategory']);
        Route::get('/{question}', [QuestionController::class, 'show']);
        Route::put('/{question}', [QuestionController::class, 'update']);
        Route::delete('/{question}', [QuestionController::class, 'destroy']);
    });

    Route::prefix('exam-papers')->group(function () {
        Route::get('/', [ExamPaperController::class, 'index']);
        Route::post('/', [ExamPaperController::class, 'store']);
        Route::get('/{examPaper}', [ExamPaperController::class, 'show']);
        Route::put('/{examPaper}', [ExamPaperController::class, 'update']);
        Route::delete('/{examPaper}', [ExamPaperController::class, 'destroy']);
        Route::post('/{examPaper}/questions', [ExamPaperController::class, 'addQuestions']);
        Route::delete('/{examPaper}/questions/{question}', [ExamPaperController::class, 'removeQuestion']);
    });

    Route::prefix('exams')->group(function () {
        Route::get('/', [ExamController::class, 'index']);
        Route::post('/{examPaper}/start', [ExamController::class, 'start']);
        Route::get('/{examPaper}/questions', [ExamController::class, 'getQuestions']);
        Route::post('/{examPaper}/submit', [ExamController::class, 'submit']);
        Route::get('/records', [ExamController::class, 'myRecords']);
        Route::get('/records/{record}', [ExamController::class, 'showRecord']);
    });

    Route::prefix('scores')->group(function () {
        Route::get('/statistics', [ScoreController::class, 'statistics']);
        Route::get('/ranking/{examPaper}', [ScoreController::class, 'ranking']);
        Route::get('/analysis/{examPaper}', [ScoreController::class, 'analysis']);
    });

    Route::prefix('exam-rooms')->group(function () {
        Route::get('/', [ExamRoomController::class, 'index']);
        Route::get('/all', [ExamRoomController::class, 'all']);
        Route::post('/', [ExamRoomController::class, 'store']);
        Route::get('/{room}', [ExamRoomController::class, 'show']);
        Route::put('/{room}', [ExamRoomController::class, 'update']);
        Route::delete('/{room}', [ExamRoomController::class, 'destroy']);
        Route::get('/{room}/seats', [ExamRoomController::class, 'seats']);
        Route::post('/{room}/seats', [ExamRoomController::class, 'addSeats']);
        Route::delete('/{room}/seats/{seat}', [ExamRoomController::class, 'removeSeat']);
    });

    Route::prefix('exam-arrangements')->group(function () {
        Route::get('/', [ExamArrangementController::class, 'index']);
        Route::get('/my', [ExamArrangementController::class, 'myArrangements']);
        Route::post('/', [ExamArrangementController::class, 'store']);
        Route::post('/import', [ExamArrangementController::class, 'import']);
        Route::post('/checkin', [ExamArrangementController::class, 'checkin']);
        Route::post('/self-checkin', [ExamArrangementController::class, 'selfCheckin']);
        Route::post('/change-seat', [ExamArrangementController::class, 'changeSeat']);
        Route::delete('/{arrangement}', [ExamArrangementController::class, 'destroy']);
    });

    Route::prefix('proctor')->group(function () {
        Route::post('/scan-seat', [ProctorController::class, 'scanSeat']);
        Route::get('/logs', [ProctorController::class, 'logs']);
        Route::post('/logs', [ProctorController::class, 'addLog']);
        Route::get('/overview', [ProctorController::class, 'overview']);
    });
});
