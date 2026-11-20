<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sanction extends Model
{
    protected $fillable = ['user_id', 'reason', 'starts_at', 'ends_at', 'is_automatic', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'lifted_at' => 'datetime', 'is_automatic' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('lifted_at')->where('starts_at', '<=', now())->where('ends_at', '>', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
