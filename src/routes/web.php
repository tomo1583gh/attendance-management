<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\AttendanceController as UAttendance;
use App\Http\Controllers\User\AttendanceListController as UList;
use App\Http\Controllers\User\AttendanceDetailController as UDetail;
use App\Http\Controllers\User\RequestController as URequest;
use App\Http\Controllers\Admin\AttendanceController as AAttendance;
use App\Http\Controllers\Admin\UserController as AUser;
use App\Http\Controllers\Admin\RequestController as ARequest;
use App\Http\Controllers\Auth\RegisterController;

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

Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('guest')
    ->name('register');

// 管理者　認証（ログイン/ログアウト）
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'login'])
        ->name('login.attempt');
    Route::post('/logout', [\App\Http\Controllers\Admin\Auth\LoginController::class, 'logout'])
        ->name('logout');
});

// 一般ユーザー（要ログイン＆メール認証）
Route::middleware(['auth', 'verified'])->group(function () {

    // 打刻画面　+　アクション
    Route::get('/attendance', [UAttendance::class, 'index'])
        ->name('attendance.index');
    Route::post('/attendance/clock-in', [UAttendance::class, 'clockIn'])
        ->name('attendance.clockIn');
    Route::post('/attendance/break-in', [UAttendance::class, 'breakIn'])
        ->name('attendance.breakIn');
    Route::post('/attendance/break-out', [UAttendance::class, 'breakOut'])
        ->name('attendance.breakOut');
    Route::post('/attendance/clock-out', [UAttendance::class, 'clockOut'])
        ->name('attendance.clockOut');

    // 勤怠一覧 / 詳細
    Route::get('/attendance/list', [UList::class, 'index'])
        ->name('attendance.list');
    Route::get('/attendance/detail/{id}', [UDetail::class, 'show'])
        ->name('attendance.detail');

    // 申請一覧（一般ユーザー）/ 修正申請登録
    Route::get('/stamp_correction_request/list', [URequest::class, 'index'])
        ->name('request.list');
    Route::post('/attendance/{id}/request', [URequest::class, 'store'])
        ->name('request.store');
});

// 管理者（adminガード）
Route::prefix('admin')->name('admin.')->middleware('auth:admin')->group(function () {

    // 日次勤怠一覧 / 詳細
    Route::get('/attendances', [AAttendance::class, 'daily'])
        ->name('attendances.daily');
    Route::get('/attendances/{id}', [AAttendance::class, 'show'])
        ->name('attendances.show');
    Route::put('/attendances/{id}', [AAttendance::class, 'update'])
        ->name('attendances.update');   

    // スタッフ一覧 / スタッフ別月次 / CSV出力
    Route::get('/users', [AUser::class, 'index'])
        ->name('users.index');
    Route::get('/users/{user}/attendances', [AUser::class, 'monthly'])
        ->name('users.attendances.monthly');
    Route::get('/users/{user}/attendances/csv', [AUser::class, 'exportCsv'])
        ->name('users.attendances.csv');

    // 申請一覧 / 詳細 / 承認
    Route::get('/requests', [ARequest::class, 'index'])
        ->name('requests.index');
    Route::get('/requests/{id}', [ARequest::class, 'show'])
        ->name('requests.show');
    Route::post('/requests/{id}/approve', [ARequest::class, 'approve'])
        ->name('requests.approve');
});