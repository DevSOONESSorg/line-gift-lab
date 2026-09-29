<?php

// =====================================================
// 疑似LINE（LINE社の役）のテーブル（database/mockline.sqlite）
// 本物では LINE社のサーバーの中にあり、私たちからは見えない部分です。
// =====================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mockline';

    public function up(): void
    {
        $s = Schema::connection('mockline');
        $s->dropAllTables();   // 疑似LINE専用のファイルなので、作り直すときは中身を全部消してから作る

        $s->create('accounts', function (Blueprint $t) {          // ログインする人（ビジネスアカウント）
            $t->string('id')->primary();                          // personal / company
            $t->string('name');
            $t->string('email');
        });
        $s->create('providers', function (Blueprint $t) {         // プロバイダー＝会社の箱
            $t->id();
            $t->string('name');
        });
        $s->create('provider_members', function (Blueprint $t) {
            $t->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $t->string('account_id');
            $t->string('role')->default('admin');
            $t->primary(['provider_id', 'account_id']);
        });
        $s->create('official_accounts', function (Blueprint $t) {  // LINE公式アカウント
            $t->id();
            $t->string('name');
            $t->string('basic_id')->unique();
            $t->string('bot_user_id');
            $t->string('industry')->default('');
            $t->string('response_mode')->default('bot');           // bot / chat
            $t->boolean('auto_reply_on')->default(true);           // 応答メッセージ
            $t->text('auto_reply_text');
            $t->boolean('greeting_on')->default(true);             // あいさつメッセージ
            $t->text('greeting_text');
            $t->boolean('is_platform')->default(false);
            $t->timestamps();
        });
        $s->create('oa_members', function (Blueprint $t) {
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('account_id');
            $t->string('role');                                    // admin / operator
            $t->primary(['official_account_id', 'account_id']);
        });
        $s->create('invites', function (Blueprint $t) {
            $t->string('token')->primary();
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('role');
            $t->timestamp('expires_at');
            $t->string('used_by')->nullable();
        });
        $s->create('channels', function (Blueprint $t) {           // チャネル
            $t->id();
            $t->string('channel_id')->unique();                    // 10桁
            $t->string('type');                                    // messaging / login
            $t->string('name');
            $t->string('description')->default('');
            $t->foreignId('provider_id')->constrained();
            $t->foreignId('official_account_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('secret');
            $t->text('access_token')->nullable();
            $t->string('webhook_url')->default('');
            $t->boolean('use_webhook')->default(false);
            $t->boolean('is_published')->default(false);           // LINEログインチャネルの「公開」
            $t->string('created_by');
            $t->timestamps();
        });
        $s->create('channel_roles', function (Blueprint $t) {
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->string('account_id');
            $t->string('role')->default('admin');
            $t->primary(['channel_id', 'account_id']);
        });
        $s->create('liff_apps', function (Blueprint $t) {
            $t->string('liff_id')->primary();
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('size');
            $t->string('endpoint_url');
            $t->string('scopes');
            $t->boolean('bot_prompt')->default(false);             // 友だち追加オプション
        });
        $s->create('rich_menus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('template');
            $t->string('image_path');
            $t->unsignedInteger('width');
            $t->unsignedInteger('height');
            $t->string('bar_text')->default('メニュー');
            $t->json('actions');
            $t->boolean('is_default')->default(true);
            $t->timestamps();
        });
        $s->create('line_users', function (Blueprint $t) {         // スマホ側のLINEユーザー（体験ではあなた1人）
            $t->string('user_id')->primary();
            $t->string('display_name');
        });
        $s->create('friends', function (Blueprint $t) {
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('user_id');
            $t->boolean('blocked')->default(false);
            $t->primary(['official_account_id', 'user_id']);
        });
        $s->create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('user_id');
            $t->string('direction');   // in / out
            $t->string('via');         // user / bot / auto / greeting / liff
            $t->text('text');
            $t->timestamp('created_at')->useCurrent();
        });
        $s->create('reply_tokens', function (Blueprint $t) {
            $t->string('token')->primary();
            $t->unsignedBigInteger('channel_id');
            $t->string('user_id');
            $t->timestamp('expires_at');
            $t->boolean('used')->default(false);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('mockline');
        foreach (['reply_tokens', 'messages', 'friends', 'line_users', 'rich_menus', 'liff_apps', 'channel_roles', 'channels', 'invites', 'oa_members', 'official_accounts', 'provider_members', 'providers', 'accounts'] as $table) {
            $s->dropIfExists($table);
        }
    }
};
