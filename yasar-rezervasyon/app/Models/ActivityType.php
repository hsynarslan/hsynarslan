<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityType extends Model
{
    use HasLocalizedName;

    protected $fillable = ['facility_id', 'name_tr', 'name_en', 'units_required', 'is_team', 'allowed_roles', 'is_active'];

    protected function casts(): array
    {
        return ['allowed_roles' => 'array', 'is_team' => 'boolean', 'is_active' => 'boolean'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function allowsAnyRole(array $roles): bool
    {
        return count(array_intersect($roles, $this->allowed_roles ?? [])) > 0;
    }
}
