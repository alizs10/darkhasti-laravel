<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\TempFileController;
use App\Http\Controllers\VoteController;
use App\Models\AttachedFile;
use App\Models\Comment;
use App\Models\Request;
use App\Models\TempFile;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::post('/check-username', [AuthController::class, 'checkUsername']);
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    // Route::post('/refresh', [AuthController::class, 'refresh']);
});

// Requests routes
Route::prefix('requests')->group(function () {

    // public
    Route::get('/', [RequestController::class, 'index']);
    Route::get('/all', [RequestController::class, 'getAll']);
    Route::get('/{request}', [RequestController::class, 'show']);
    Route::get('/{request}/related', [RequestController::class, 'related']);
    Route::get('/{request_model}/comments', [CommentController::class, 'requestComments']);

    // protected
    Route::middleware('auth:api')->group(function () {
        Route::post('/', [RequestController::class, 'store']);
        Route::put('/{request_model}', [RequestController::class, 'update']);
        Route::delete('/{request}', [RequestController::class, 'destroy']);
        Route::put('/choose-answer/toggle', [RequestController::class, 'toggleAnswer']);
    });
});

// Comments routes
Route::prefix('comments')->group(function () {
    // public
    Route::get('/all', [CommentController::class, 'getAll']);
    Route::get('/{comment}/replies', [CommentController::class, 'index']);
    Route::get('/{comment}', [CommentController::class, 'show']);

    // protected
    Route::middleware('auth:api')->group(function () {
        Route::post('/', [CommentController::class, 'store']);
        Route::put('/{comment}', [CommentController::class, 'update']);
        Route::delete('/{comment}', [CommentController::class, 'destroy']);
    });
});

// Profile
Route::prefix('profile')->middleware('auth:api')->group(function () {
    Route::get('/stats', [ProfileController::class, 'stats']);
    Route::put('/change-username', [ProfileController::class, 'changeUsername']);
    Route::post('/delete-account', [ProfileController::class, 'deleteAccount']);
});

// My
Route::prefix('my')->middleware('auth:api')->group(function () {
    Route::get('/requests', [RequestController::class, 'myRequests']);
    Route::get('/requests/{request}', [RequestController::class, 'showMy']);
    Route::get('/comments/{comment}', [CommentController::class, 'showMy']);
});

// Temp Files
Route::prefix('temp-files')->middleware('auth:api')->group(function () {
    Route::post('/upload', [TempFileController::class, 'upload']);
    Route::get('/my', [TempFileController::class, 'myTempFiles']);
    Route::post('/for-attachable', [TempFileController::class, 'forAttachable']);
    Route::delete('/{tempFile}', [TempFileController::class, 'delete']);
});

// Vote
Route::post('/vote', [VoteController::class, 'toggle'])->middleware('auth:api');

// app stats
Route::get('/app-stats', function () {
    return response()->json([
        'requests_count' => Request::count(),
        'comments_count' => Comment::count(),
        'users_count' => User::count(),
        'temp_files_count' => TempFile::count(),
        'perm_files_count' => AttachedFile::count(),
    ], 200);
});
