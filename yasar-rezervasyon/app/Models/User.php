<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    // Yetki bayrakları (is_coach, is_sks_admin, is_attendant, is_active) bilerek toplu atamaya kapalıdır;
    // yalnızca SKS Admin ekranından açıkça değiştirilir (ISO 27001 A.8.2).
    protected $fillable = [
        'username', 'ldap_dn', 'name', 'email', 'number', 'department', 'base_role', 'locale', 'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'is_coach' => 'boolean',
            'is_sks_admin' => 'boolean',
            'is_attendant' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return ''; // Yerel şifre yok.
    }

    public function getRememberTokenName(): ?string
    {
        return null; // "Beni hatırla" kapalı.
    }

    /** Rezervasyon kurallarında kullanılan rol: antrenörler kendi kuralına tabidir. */
    public function bookingRole(): string
    {
        return $this->is_coach ? Roles::COACH : $this->base_role;
    }

    /** Kullanıcının izinli sayılacağı roller (antrenör hem antrenör hem temel rolüyle eşleşir). */
    public function bookingRoles(): array
    {
        return $this->is_coach ? [Roles::COACH, $this->base_role] : [$this->base_role];
    }

    public function isStaff(): bool
    {
        return in_array($this->base_role, [Roles::ADMIN_STAFF, Roles::ACADEMIC], true);
    }

    public function isExemptFromLimits(): bool
    {
        return $this->is_coach;
    }

    public function approvableFacilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_approvers');
    }

    /** Onaylayabileceği tesis kimlikleri (SKS Admin için null = hepsi). */
    public function approvableFacilityIds(): ?array
    {
        return $this->is_sks_admin ? null : $this->approvableFacilities()->pluck('facilities.id')->all();
    }

    public function isApprover(): bool
    {
        return $this->is_sks_admin || $this->approvableFacilities()->exists();
    }

    public function canApproveFacility(int $facilityId): bool
    {
        return $this->is_sks_admin || $this->approvableFacilities()->where('facilities.id', $facilityId)->exists();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function coachedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'coach_id');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function activeSanction(): ?Sanction
    {
        return $this->sanctions()->active()->orderByDesc('ends_at')->first();
    }

    public function roleLabels(): array
    {
        $labels = [Roles::label($this->base_role)];
        if ($this->is_coach) {
            $labels[] = __('Antrenör');
        }
        if ($this->is_attendant) {
            $labels[] = __('Salon Görevlisi');
        }
        if ($this->is_sks_admin) {
            $labels[] = __('SKS Admin');
        }

        return $labels;
    }
}
