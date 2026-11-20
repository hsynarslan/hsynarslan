<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Değiştirilemez işlem kaydı. Kayıtlar yalnızca App\Services\Audit üzerinden eklenir. */
class AuditLog extends Model
{
    // created_at hash hesabına girdiği için yalnızca Audit servisi tarafından yazılır.
    public $timestamps = false;

    protected $fillable = [
        'created_at', 'user_id', 'action', 'subject_type', 'subject_id', 'data', 'ip_address', 'user_agent',
        'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log kayıtları değiştirilemez.'));
        static::deleting(fn () => throw new LogicException('Audit log kayıtları silinemez.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
