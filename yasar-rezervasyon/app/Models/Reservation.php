<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /** Slotu kilitleyen durumlar. */
    public const ACTIVE = [self::PENDING, self::APPROVED];

    protected $fillable = [
        'code', 'facility_id', 'activity_type_id', 'user_id', 'team_id', 'recurring_block_id', 'kind', 'status',
        'starts_at', 'ends_at', 'participant_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'attendance_marked_at' => 'datetime',
            'approver_reminded_at' => 'datetime',
            'reminded_day_at' => 'datetime',
            'reminded_hours_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'is_late_cancel' => 'boolean',
        ];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(RecurringBlock::class, 'recurring_block_id');
    }

    public function reservationUnits(): HasMany
    {
        return $this->hasMany(ReservationUnit::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(FacilityUnit::class, 'reservation_units')->orderBy('sort');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ReservationParticipant::class)->whereNull('removed_at');
    }

    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'reservation_participants')->wherePivotNull('removed_at');
    }

    public function equipmentItems(): HasMany
    {
        return $this->hasMany(ReservationEquipment::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(ReservationCheckin::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE);
    }

    public function scopeOverlapping(Builder $q, $start, $end): Builder
    {
        return $q->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** Kullanıcının sahibi ya da (çıkmamış) katılımcısı olduğu rezervasyonlar. */
    public function scopeInvolving(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($w) => $w->where('user_id', $userId)
            ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId)));
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isBlock(): bool
    {
        return $this->kind === 'block';
    }

    public function statusLabel(): string
    {
        return __(match ($this->status) {
            self::PENDING => 'Beklemede',
            self::APPROVED => 'Onaylandı',
            self::REJECTED => 'Reddedildi',
            self::CANCELLED => 'İptal',
            self::EXPIRED => 'Süresi doldu',
        });
    }

    public function kindLabel(): string
    {
        return __(match ($this->kind) {
            'individual' => 'Bireysel',
            'group' => 'Grup',
            'team' => 'Takım',
            'block' => 'Sabit blok',
        });
    }

    public function title(): string
    {
        if ($this->isBlock()) {
            return $this->block?->title ?? $this->activity?->name ?? '';
        }

        return $this->activity?->name ?? '';
    }

    /** Geç iptal sınırı. */
    public function cancelDeadline()
    {
        return $this->starts_at->copy()->subHours($this->facility->cancel_deadline_hours);
    }

    /** Durum değiştiğinde birim kilitlerini senkronize eder. */
    public function syncUnitLocks(): void
    {
        $this->reservationUnits()->update(['is_active' => $this->isActive()]);
    }
}
