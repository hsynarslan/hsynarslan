<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityRule extends Model
{
    public const FIELDS = ['max_duration_minutes', 'max_per_day', 'max_per_week', 'max_days_ahead', 'min_hours_before'];

    protected $fillable = ['facility_id', 'role', ...self::FIELDS];
}
