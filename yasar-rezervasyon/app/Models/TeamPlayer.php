<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class TeamPlayer extends Model
{
    /** Takımdan çıkışta silinen, şifreli saklanan alanlar. */
    public const SENSITIVE = [
        'birth_date', 'phone', 'license_no', 'health_report_date', 'health_report_expires_on',
        'emergency_contact_name', 'emergency_contact_phone', 'notes',
    ];

    protected $fillable = ['team_id', 'user_id', 'jersey_no', 'position', 'joined_on', ...self::SENSITIVE];

    protected function casts(): array
    {
        return array_fill_keys(self::SENSITIVE, 'encrypted') + [
            'joined_on' => 'date',
            'left_on' => 'date',
            'health_warned_at' => 'datetime',
            'sensitive_purged_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function healthExpiresOn(): ?Carbon
    {
        return $this->health_report_expires_on ? Carbon::parse($this->health_report_expires_on)->startOfDay() : null;
    }

    /** @return 'expired'|'soon'|'ok'|'none' */
    public function healthStatus(int $warningDays = 30): string
    {
        $exp = $this->healthExpiresOn();
        if (! $exp) {
            return 'none';
        }
        if ($exp->lt(today())) {
            return 'expired';
        }

        return $exp->lte(today()->addDays($warningDays)) ? 'soon' : 'ok';
    }

    /** Takımdan çıkar ve hassas verileri kalıcı olarak sil (ISO 27001 A.8.10). */
    public function removeFromTeam(): void
    {
        if ($this->photo_path) {
            Storage::disk('local')->delete($this->photo_path);
        }
        foreach (self::SENSITIVE as $field) {
            $this->{$field} = null;
        }
        $this->photo_path = null;
        $this->left_on = today();
        $this->sensitive_purged_at = now();
        $this->save();
    }
}
