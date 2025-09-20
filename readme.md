# 勤怠管理アプリ（Laravel 10）

## 動作環境
- Docker, docker compose
- PHP 8.2 / Laravel 10
- MySQL 8
- Mailhog

---

## セットアップ（開発環境）
```bash
git clone <this-repo>
cd attendance-management
docker compose up -d --build
docker compose exec php bash -lc "cp .env.example .env && php artisan key:generate"
# .env の DB / MAIL を確認（Mailhog）
docker compose exec php bash -lc "php artisan migrate --seed"
セットアップ（テスト環境）
bash
コードをコピーする
# .env.testing.example をコピー
cp .env.testing.example .env.testing

# APP_KEY を発行して .env.testing に反映
php artisan key:generate --show
# → 表示されたキーを .env.testing の APP_KEY に貼り付ける

# マイグレーション実行（テストDB用）
php artisan migrate:fresh --seed --env=testing
テスト実行：

bash
コードをコピーする
php artisan test
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
以下でダミーデータが投入されます（管理者/一般ユーザー、勤怠・休憩データ含む）：

bash
コードをコピーする
php artisan migrate:fresh --seed