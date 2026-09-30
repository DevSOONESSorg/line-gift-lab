<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 疑似スマホを2台（お客さん・オーナー）にするため、LINEユーザーに「どちらのスマホか」を持たせる
return new class extends Migration
{
    protected $connection = 'mockline';

    public function up(): void
    {
        Schema::connection('mockline')->table('line_users', function (Blueprint $t) {
            $t->string('phone')->nullable();   // customer（お客さんのスマホ）／ owner（オーナーのスマホ）
        });
    }

    public function down(): void
    {
        Schema::connection('mockline')->table('line_users', function (Blueprint $t) {
            $t->dropColumn('phone');
        });
    }
};
