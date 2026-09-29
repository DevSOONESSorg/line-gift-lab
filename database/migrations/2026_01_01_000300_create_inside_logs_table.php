<?php

// 裏側ビュー（観察カメラ）の記録（database/inside.sqlite）

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'inside';

    public function up(): void
    {
        Schema::connection('inside')->dropAllTables();
        Schema::connection('inside')->create('logs', function (Blueprint $t) {
            $t->id();
            $t->string('side');     // phone / line / app / admin
            $t->string('level');    // info / ok / ng
            $t->string('title');
            $t->text('detail')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('inside')->dropIfExists('logs');
    }
};
