<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KvkkText extends Model
{
    protected $fillable = ['version', 'body_tr', 'body_en', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(KvkkConsent::class);
    }

    public static function current(): ?self
    {
        return static::where('published_at', '<=', now())->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public function body(): string
    {
        return app()->getLocale() === 'en' ? $this->body_en : $this->body_tr;
    }
}
