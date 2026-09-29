<?php

namespace App\Models\Mock;

// Manager / Developers にログインする人（体験では2人）
class Account extends MockModel
{
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';
}
