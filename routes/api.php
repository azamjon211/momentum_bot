<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyPlanTaskController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WeeklyPlanController;
use App\Http\Controllers\WeeklyPlanTaskController;
use App\Http\Middleware\CheckUser;
use Illuminate\Support\Facades\Route;
use App\Facades\Payment;
Route::get('/', function () {
    //dd(\App\Facades\Payment::process(['transactionId' => 123]));
   return view('welcome');
});
Route::middleware('role:admin')->prefix('check')->group(function(){
    Route::get('/', function(){
        return 'you are an admin';
    });
    Route::get('/other', function(){
        return 'you are another admin';
    })->withoutMiddleware(CheckUser::class);
});
Route::patch('daily-tasks/{task}/complete', [DailyPlanTaskController::class, 'complete']);

Route::middleware('auth:sanctum')->group(function(){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications/export', [ApplicationController::class, 'export']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);

    Route::post('plans', [WeeklyPlanController::class, 'store']);
    Route::post('plans/{plan}/activate', [WeeklyPlanController::class, 'activate']);
    Route::post('plans/{plan}/tasks', [WeeklyPlanTaskController::class, 'store']);
});
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/show/{transactionId}', [TransactionController::class, 'show']);
Route::post('/telegram/webhook', [TelegramController::class, 'handle']);   // <- shu qo'shildi
