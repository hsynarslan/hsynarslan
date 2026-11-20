<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationEquipment extends Model
{
    protected $table = 'reservation_equipment';

    protected $fillable = ['reservation_id', 'equipment_id', 'quantity'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function conditionLabel(): string
    {
        return __(match ($this->return_condition) {
            'ok' => 'Sağlam',
            'damaged' => 'Hasarlı',
            'missing' => 'Kayıp',
            default => '—',
        });
    }
}
