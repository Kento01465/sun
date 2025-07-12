<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

/*
|--------------------------------------------------------------------------
| 認証が必要ないルート
|--------------------------------------------------------------------------
*/

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return redirect()->route('attendance.index');
});

/*
|--------------------------------------------------------------------------
| 認証が必要なルート
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('attendance.index');
});


Route::middleware(['auth'])->group(function () {

    // 勤怠リソースのCRUDルートをまとめて定義
    Route::resource('attendance', AttendanceController::class)
        ->except(['create', 'store','show']);

    // 既存の打刻機能は別で個別定義
    Route::post('/attendance/clockin', [AttendanceController::class, 'clockIn'])->name('attendance.clockin');
    Route::post('/attendance/clockout', [AttendanceController::class, 'clockOut'])->name('attendance.clockout');

    // ダッシュボード、履歴、申請、CSVなど
    Route::get('/attendance/dashboard', [AttendanceController::class, 'dashboard'])->name('attendance.dashboard');
    Route::get('/attendance/history', [AttendanceController::class, 'history'])->name('attendance.history');
    Route::get('/attendance/request', [AttendanceController::class, 'request'])->name('attendance.request');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
});

/*
|--------------------------------------------------------------------------
| 認証ルートの読み込み
|--------------------------------------------------------------------------
*/

require __DIR__.'/auth.php';
