<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacilityHour extends Model
{
    public $timestamps = false;

    protected $fillable = ['facility_id', 'weekday', 'is_closed', 'opens_at', 'closes_at'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }

    /** Açılışın gece yarısından itibaren dakikası. */
    public function opensMinute(): int
    {
        return self::toMinutes($this->opens_at);
    }

    public function closesMinute(): int
    {
        return self::toMinutes($this->closes_at);
    }

    public static function toMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }
}
