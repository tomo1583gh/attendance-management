php artisan tinker
exit
php artisan tinker
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
php artisan route:clear
php artisan optimize:clear
php artisan route:list | grep admin
exit
php artisan optimize:clear
php artisan route:list | grep admin.attendance
php artisan optimize:clear
php artisan route:list | grep admin.attendance
grep -RIn "admin/attendances" resources routes
grep -RIn "admin.attendances." resources routes
exit
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan optimize:clear
exit
php artisan optimize:clear
exit
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan optimize:clear
exit
php artisan optimize:clear
exit
php artisan optimize:clear
php artisan make:model StampCorrectionRequest
php artisan migrate:flesh:seed
php artisan migrate:fresh --seed
exit
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan optimize:clear
php artisan view:clear
php artisan view:clear
php artisan optimize:clear
exit
php artisan make:request LoginRequest
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan lang:publish
hp artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan make:request Auth/RegisterRequest
php artisan make:request Auth/RegisterController.php
php artisan make:controller Auth/RegidterControlloer.php
php artisan optimize:clear
exit
composer dump-autoload
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:list | grep register
php artisan route:list | grep register
php -r "var_dump(class_exists('App\\\\Http\\\\Controllers\\\\Auth\\\\RegisterController'));"
php artisan make:controller Auth/RegisterController
php artisan route:list | grep register
php artisan optimize:clear
exit
php artisan route:list | grep email/verify
php artisan optimize:clear
php artisan make:migration create_attendance_breaks_table
php artisan make:model AttendanceBreak
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan route:list | grep request.store
php artisan route:list | grep attendance.detail.update
exit
php artisan make:request User AttendanceDetailRequest
php artisan make:request User/AttendanceDetailRequest
php artisan optimize:clear
php artisan optimize:clear
php artisan route:list | grep request.store
php artisan optimize:clear
exit
php artisan route:list | grep admin/attendances
php artisan make:request Admin/AttendanceDetailUpdateRequest
php artisan optimize:clear
php artisan optimize:clear
php artisan optimize:clear
php artisan route:list | grep admin.users.attendances.monthly
php artisan route:list | grep admin.users.attendances.monthly
php artisan optimize:clear
php artisan optimize:clear
exit
php artisan test --filter=名前が未入力の場合_バリデーションメッセージが表示される

php artisan test --filter=名前が未入力の場合_メールアドレスが未入力の場合_バリデーションメッセージが表示される
php artisan test --filter=名前が未入力の場合_メールア=メールアドレスが未入力の場合_バリデーションメッセージが表示される
exit
php artisan migrate:fresh --seed --env=testing
exit
php artisan test --filter=名前が未入力の場合_メールア=メ�パスワードが8文字未満の場合_バリデーションメッセージが表示される
php artisan test --filter=名前が未入力の場合_メー�
php artisan test --filter=名前が未RegisterTest
php artisan test --filter=RegisterTest
php artisan test --filter=RegisterTest
php artisan test --filter=RegisterTest
php artisan test --filter=RegisterTest
php artisan test --filter=RegisterTest
php artisan test --filter=RegisterTest
exit
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=ClockOutTest
php artisan test --filter=ClockOutTest
exit
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test --filter=AttendanceListTest
php artisan test
exit
php artisan test --filter=AttendanceMonthDisplayTest
php artisan test --filter=AttendanceMonthDisplayTest
php artisan test --filter=AttendanceMonthDisplayTest
php artisan test --filter=AttendanceDetailNavigationTest
php artisan test --filter=AttendanceDetailNavigationTest
php artisan test --filter=AttendanceDetailNameTest
php artisan test --filter=AttendanceDetailDateTest
php artisan test --filter=AttendanceDetailDateTest
php artisan test --filter=AttendanceDetailDateTest
php artisan route:list | grep attendance.detail
php artisan test --filter=AttendanceDetailDateTest
exit
php artisan test --filter=AttendanceDetailDateTest
php artisan test --filter=AttendanceUpdateValidationTest
php artisan test --filter=AttendanceUpdateValidationTest
php artisan test --filter=AttendanceUpdateValidationTest
php artisan test --filter=AttendanceUpdateValidationTest
exit
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=StampCorrectionRequestFeatureTest
php artisan test --filter=EmailVerificationFeatureTest
php artisan test --filter=EmailVerificationFeatureTest
php artisan test --filter=EmailVerificationFeatureTest
php artisan test --filter=EmailVerificationFeatureTest
#[Test]
public function メール認証完了で勤怠登録画面に遷移する(): void
{     // 認証未完了ユーザーを作成してログイン;     $user = User::factory()->unverified()->create([
        'email'    => 'verifyme@example.com',
        'password' => bcrypt('password123'),
    ]);
    $this->actingAs($user, 'web');
    // 署名付き検証URLを生成（Fortify/Laravel既定: verification.verify）
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );
    // 検証リンクにアクセス
    $res = $this->get($verificationUrl);
    // リダイレクト先に /attendance が含まれること（実装でクエリ等が付いても許容）
    $res->assertRedirect();
    $location = $res->headers->get('Location');
    $this->assertNotNull($location, 'リダイレクト先が取得できません。');
    $this->assertStringContainsString('/attendance', $location, '勤怠登録画面へ遷移していません。');
    // 実際に勤怠登録画面へ到達できる（200 or 302を許容）
    $follow = $this->get($location);
    $this->assertTrue(in_array($follow->getStatusCode(), [200, 302], true), '勤怠登録画面の表示に失敗しました。');
    // ユーザーのメールが検証済みになっている
    $user->refresh();
    $this->assertNotNull($user->email_verified_at, 'email_verified_at が設定されていません。');
}
php artisan test --filter=EmailVerificationFeatureTest
php artisan test
exit
