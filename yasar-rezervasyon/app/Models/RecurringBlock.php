<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringBlock extends Model
{
    protected $fillable = [
        'facility_id', 'activity_type_id', 'team_id', 'title', 'weekdays', 'unit_ids', 'start_time', 'end_time',
        'starts_on', 'ends_on', 'created_by',
    ];

    protected function casts(): array
    {
        return ['weekdays' => 'array', 'unit_ids' => 'array', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
