<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Webhook の「検証」に成功した時刻（構築ナビが「検証まで済んだか」を見るのに使う。URL を変えると消える）
return new class extends Migration
{
    protected $connection = 'mockline';

    public function up(): void
    {
        Schema::connection('mockline')->table('channels', fn (Blueprint $t) => $t->timestamp('webhook_verified_at')->nullable());
    }

    public function down(): void
    {
        Schema::connection('mockline')->table('channels', fn (Blueprint $t) => $t->dropColumn('webhook_verified_at'));
    }
};
