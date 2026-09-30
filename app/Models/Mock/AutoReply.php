<?php

namespace App\Models\Mock;

// 応答メッセージ（本物の Manager「自動応答 › 応答メッセージ」の1行）
//   keywords … 改行区切り。送られた文字がどれかと「完全に一致」したら返す
//   keywords が空 … 「一律応答」。キーワードに当たらなかったメッセージすべてに返す
//   enabled  … 一覧の「利用」スイッチ
class AutoReply extends MockModel
{
    protected $casts = ['enabled' => 'boolean'];

    public function officialAccount() { return $this->belongsTo(OfficialAccount::class); }

    /** @return string[] */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->keywords))));
    }

    public function isCatchAll(): bool { return ! $this->keywordList(); }

    // この公式アカウントで、送られた文字に返す応答メッセージを決める（キーワード一致 → 一律応答 の順）
    public static function match(OfficialAccount $oa, string $text): ?self
    {
        $items = $oa->autoReplies()->where('enabled', true)->orderBy('id')->get();
        $text = trim($text);
        return $items->first(fn ($a) => in_array($text, $a->keywordList(), true))
            ?? $items->first(fn ($a) => $a->isCatchAll());
    }
}
