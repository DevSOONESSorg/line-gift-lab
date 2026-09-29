<?php

namespace App\Models\Mock;

class LiffApp extends MockModel
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = 'liff_id';
    protected $keyType = 'string';
    protected $casts = ['bot_prompt' => 'boolean'];

    public function channel() { return $this->belongsTo(Channel::class); }
}
