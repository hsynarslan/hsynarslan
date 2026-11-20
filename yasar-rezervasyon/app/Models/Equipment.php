<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    use HasLocalizedName;

    protected $table = 'equipment';

    protected $fillable = ['name_tr', 'name_en', 'total_stock', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'equipment_facility');
    }
}
