# 勤怠管理アプリ（Laravel 10）

## 動作環境
- Docker, docker compose
- PHP 8.2 / Laravel 10
- MySQL 8
- Mailhog

## セットアップ
```bash
git clone <this-repo>
cd attendance-management
docker compose up -d --build
docker compose exec php bash -lc "cp .env.example .env && php artisan key:generate"
# .env の DB / MAIL を確認（Mailhog）
docker compose exec php bash -lc "php artisan migrate --seed"

ログイン情報（必須）

一般ユーザー（メール認証済）

メール：user@example.com

パスワード：password

ログインURL：/login

管理者

メール：admin@example.com

パスワード：password

ログインURL：/admin/login

主要画面URL

会員登録（一般）：/register

ログイン（一般）：/login

出勤登録：/attendance

勤怠一覧：/attendance/list

勤怠詳細：/attendance/detail/{id}

申請一覧（一般）：/stamp_correction_request/list

管理ログイン：/admin/login

管理 日次勤怠一覧：/admin/attendances

管理 勤怠詳細：/admin/attendances/{id}

スタッフ一覧：/admin/users

スタッフ別月次：/admin/users/{user}/attendances

申請一覧（管理）：/admin/requests

申請承認：/admin/requests/{id}

テストデータ

php artisan migrate:fresh --seed で投入（管理者/一般ユーザー、勤怠・休憩ダミー）