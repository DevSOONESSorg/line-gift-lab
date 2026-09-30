#!/bin/sh
# =====================================================
# コンテナが起動したときに最初に動く準備
#   1. .env がなければ .env.example からコピー
#   2. vendor（Laravel 本体など）がなければ composer install
#   3. APP_KEY がなければ作る（シークレットやトークンの暗号化に使う鍵）
#   4. データベースが空なら、表を作って初期データを入れる
#   5. スケジューラー（期限切れの自動処理）を裏で動かす
#   6. Webサーバーを起動（同時に8つまでリクエストを受けられるようにする）
# =====================================================
set -e
cd /app

[ -f .env ] || cp .env.example .env
# Laravel が一時ファイルを置くフォルダ（空のフォルダは git に残らないので、ここで必ず作る）
mkdir -p storage/framework/views storage/framework/sessions storage/framework/cache/data storage/logs bootstrap/cache
[ -f vendor/autoload.php ] || composer install --no-interaction --prefer-dist
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

touch database/database.sqlite database/mockline.sqlite database/inside.sqlite
if php artisan migrate:status >/dev/null 2>&1; then
  php artisan migrate --force          # 表はある → 新しいマイグレーションだけ実行
else
  php artisan migrate:fresh --seed --force   # 初回 → 表を作って初期データを入れる
fi
[ -e public/storage ] || php artisan storage:link
# 画面（Blade）を先にまとめて変換しておく（疑似スマホ2台を同時に開いたとき、変換が重なってエラーになるのを防ぐ）
php artisan view:cache > /dev/null

php artisan schedule:work > storage/logs/schedule.log 2>&1 &

echo ""
echo "  $(grep '^APP_NAME=' .env | cut -d= -f2)（教材）を起動しました → http://localhost:${PORT:-3000}"
echo "  管理画面ログイン: admin@example.com / Taiken-2026"
echo ""
cd public
# PHP の組み込みサーバー。自分自身に Webhook を送る（疑似LINE → 自社サーバー）ので、1つだけだと詰まる → 8つにする
PHP_CLI_SERVER_WORKERS=8 exec php -S 0.0.0.0:3000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
