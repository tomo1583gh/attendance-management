<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\AttendanceController as UAttendance;
use App\Http\Controllers\User\AttendanceListController as UList;
use App\Http\Controllers\User\AttendanceDetailController as UDetail;
use App\Http\Controllers\User\RequestController as URequest;
use App\Http\Controllers\Admin\AttendanceController as AAttendance;
use App\Http\Controllers\Admin\UserController as AUser;
use App\Http\Controllers\Admin\RequestController as ARequest;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/home', '/attendance');
Route::redirect('/', '/login');
Route::redirect('/admin', '/admin/login');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'login']);
    Route::post('/logout', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'logout'])->name('logout');
});

// 一般ユーザー（要ログイン）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/attendance', [UAttendance::class, 'index']);
    Route::post('/attendance/clock-in', [UAttendance::class, 'clockIn']);
    Route::post('/attendance/break-in', [UAttendance::class, 'breakIn']);
    Route::post('/attendance/break-out', [UAttendance::class, 'breakOut']);
    Route::post('/attendance/clock-out', [UAttendance::class, 'clockOut']);

    Route::get('/attendance/list', [UList::class, 'index']);
    Route::get('/attendance/detail/{id}', [UDetail::class, 'show']);

    Route::get('/stamp_correction_request/list', [URequest::class, 'index']);
    Route::post('/attendance/{id}/request', [URequest::class, 'store']); // 修正申請
});

// 管理者（adminガード）
Route::prefix('admin')->middleware('auth:admin')->group(function () {
    Route::get('/attendances', [AAttendance::class, 'daily']);                 // 日次一覧
    Route::get('/attendances/{id}', [AAttendance::class, 'show']);             // 詳細（修正可）
    Route::get('/users', [AUser::class, 'index']);                              // スタッフ一覧
    Route::get('/users/{user}/attendances', [AUser::class, 'monthly']);        // スタッフ別月次
    Route::get('/requests', [ARequest::class, 'index']);                        // 申請一覧(承認待ち/承認済み)
    Route::get('/requests/{id}', [ARequest::class, 'show']);                    // 申請詳細
    Route::post('/requests/{id}/approve', [ARequest::class, 'approve']);        // 承認
    Route::get('/users/{user}/attendances/csv', [AUser::class, 'exportCsv']);   // CSV出力
});
