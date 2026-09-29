<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Menu extends Model
{
    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function template(): BelongsTo { return $this->belongsTo(MenuTemplate::class, 'menu_template_id'); }
}
