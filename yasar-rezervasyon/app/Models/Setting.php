<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public const DEFAULTS = [
        'default_max_duration_minutes' => 120,
        'default_max_per_day' => 1,
        'default_max_per_week' => 3,
        'default_max_days_ahead' => 14,
        'default_min_hours_before' => 2,
        'approver_reminder_after_hours' => 4,
        'approver_reminder_before_start_hours' => 2,
        'noshow_grace_minutes' => 15,
        'checkin_early_minutes' => 10,
        'sanction_threshold' => 3,
        'sanction_window_days' => 30,
        'sanction_duration_days' => 14,
        'retention_years' => 5,
        'health_warning_days' => 30,
    ];

    public static function get(string $key): int
    {
        $all = Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key')->all());

        return (int) ($all[$key] ?? self::DEFAULTS[$key]);
    }

    public static function put(string $key, int $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }
}
