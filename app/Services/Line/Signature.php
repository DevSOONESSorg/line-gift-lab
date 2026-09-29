<?php

namespace App\Services\Line;

// =====================================================
// LINE の署名（X-Line-Signature）
//   署名 ＝ HMAC-SHA256(チャネルシークレット, 届いた本文そのもの) を Base64 にしたもの
// LINE社と自社は同じシークレットを持っていて、同じ計算をして結果を比べる。
// シークレットそのものはネットを流れない（だから「合言葉」）
// =====================================================
class Signature
{
    public static function sign(string $secret, string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    public static function verify(?string $secret, string $body, ?string $signature): bool
    {
        if (! $secret || ! $signature) return false;
        // hash_equals：比べる時間から答えを推測されないようにする比較
        return hash_equals(self::sign($secret, $body), $signature);
    }
}
