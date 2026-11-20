<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityUnit extends Model
{
    use HasLocalizedName;

    protected $fillable = ['facility_id', 'name_tr', 'name_en', 'sort'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
