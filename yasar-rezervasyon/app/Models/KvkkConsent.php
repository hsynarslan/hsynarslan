<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KvkkConsent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'kvkk_text_id', 'accepted_at', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
