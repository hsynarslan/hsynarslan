<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Facility extends Model
{
    use HasLocalizedName;

    protected $fillable = [
        'name_tr', 'name_en', 'description_tr', 'description_en', 'slot_minutes', 'capacity', 'group_allowed',
        'participant_list_required', 'cancel_deadline_hours', 'allowed_roles', 'is_active', 'sort', 'checkin_mode',
    ];

    protected function casts(): array
    {
        return [
            'allowed_roles' => 'array',
            'group_allowed' => 'boolean',
            'participant_list_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Facility $f) {
            $f->checkin_token ??= self::newToken();
        });
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public function units(): HasMany
    {
        return $this->hasMany(FacilityUnit::class)->orderBy('sort')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityType::class)->orderBy('id');
    }

    public function hours(): HasMany
    {
        return $this->hasMany(FacilityHour::class)->orderBy('weekday');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FacilityRule::class);
    }

    public function approvers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_approvers');
    }

    public function attendants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_attendants');
    }

    public function usesDynamicQr(): bool
    {
        return $this->checkin_mode !== 'static';
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'equipment_facility');
    }

    public function allowsAnyRole(array $roles): bool
    {
        return count(array_intersect($roles, $this->allowed_roles ?? [])) > 0;
    }

    public function hourFor(int $isoWeekday): ?FacilityHour
    {
        return $this->hours->firstWhere('weekday', $isoWeekday);
    }
}
