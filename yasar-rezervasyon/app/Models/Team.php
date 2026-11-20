<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'sport', 'coach_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    public function activePlayers(): HasMany
    {
        return $this->players()->whereNull('left_on');
    }

    /** Hassas oyuncu bilgilerini görebilir mi (ISO 27001 A.8.3). */
    public function canManage(User $user): bool
    {
        return $user->is_sks_admin || ($user->is_coach && $this->coach_id === $user->id);
    }
}
