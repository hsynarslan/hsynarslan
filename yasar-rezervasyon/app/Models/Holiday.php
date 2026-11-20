<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasLocalizedName;

    protected $fillable = ['date', 'name_tr', 'name_en', 'closed_from'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
