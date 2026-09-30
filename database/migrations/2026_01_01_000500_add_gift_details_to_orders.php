<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 本番環境の注文画面に合わせて、注文に3つの項目を足す
//   order_code     … お客さんに見せる注文番号（例 K7Q2M9XW4T）。連番の id をそのまま見せない
//   sender_name    … 送信者名（お客さんが入力。初期値は LINE の表示名）
//   recipient_name … 贈る人（空欄ならお店宛。例：キャストの名前）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('order_code', 10)->nullable()->unique();
            $t->string('sender_name')->nullable();
            $t->string('recipient_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropUnique(['order_code']);
            $t->dropColumn(['order_code', 'sender_name', 'recipient_name']);
        });
    }
};
