<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// =====================================================
// 裏側ビュー用の記録係（観察カメラ）
//   Inside::ok('line', 'Webhook送信 → 200', '詳細…');
// side: phone（スマホ） / line（LINE社） / app（自社サーバー） / admin（管理画面）
// =====================================================
class Inside
{
    public const SIDES = [
        'phone' => 'スマホ（お客さん・オーナー）',
        'line' => 'LINE社（疑似）',
        'app' => '自社サーバー',
        'admin' => '管理画面',
    ];

    public static function info(string $side, string $title, mixed $detail = null): void { self::add($side, 'info', $title, $detail); }
    public static function ok(string $side, string $title, mixed $detail = null): void { self::add($side, 'ok', $title, $detail); }
    public static function ng(string $side, string $title, mixed $detail = null): void { self::add($side, 'ng', $title, $detail); }

    public static function add(string $side, string $level, string $title, mixed $detail = null): void
    {
        if ($detail !== null && ! is_string($detail)) {
            $detail = json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        try {
            DB::connection('inside')->table('logs')->insert([
                'side' => $side, 'level' => $level, 'title' => mb_substr($title, 0, 250),
                'detail' => $detail !== null ? mb_substr($detail, 0, 6000) : null, 'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // 記録に失敗しても本体の処理は止めない
        }
    }

    public static function mask(?string $s, int $keep = 4): string
    {
        if (! $s) return '(空)';
        return mb_strlen($s) <= $keep * 2 ? $s : mb_substr($s, 0, $keep).'…'.mb_substr($s, -$keep);
    }
}
