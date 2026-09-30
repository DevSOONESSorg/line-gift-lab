<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// =====================================================
// 疑似LINEを本番の画面に近づけるための追加
//   ・応答メッセージを「キーワードごとの一覧」にする（本物の Manager の「応答メッセージ」と同じ）
//       キーワードが空の行は「一律応答」＝どのメッセージにも返す
//   ・チャネルの基本設定：メールアドレス・プライバシーポリシーURL・所在国・2要素認証の必須化
//   ・LINEログインチャネルの「リンクされたLINE公式アカウント」（友だち追加オプションで使う）
// =====================================================
return new class extends Migration
{
    protected $connection = 'mockline';

    public function up(): void
    {
        $s = Schema::connection('mockline');
        $s->create('auto_replies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('official_account_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('keywords')->default('');     // 改行区切り。空なら「一律応答」
            $t->text('text');
            $t->boolean('enabled')->default(true);  // 「利用」のオン・オフ
            $t->timestamps();
        });
        $s->table('channels', function (Blueprint $t) {
            $t->string('email')->default('');
            $t->string('privacy_url')->default('');
            $t->string('terms_url')->default('');
            $t->string('country')->default('');
            $t->boolean('two_factor')->default(false);
            $t->unsignedBigInteger('linked_oa_id')->nullable();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('mockline');
        $s->dropIfExists('auto_replies');
        $s->table('channels', fn (Blueprint $t) => $t->dropColumn(['email', 'privacy_url', 'terms_url', 'country', 'two_factor', 'linked_oa_id']));
    }
};
