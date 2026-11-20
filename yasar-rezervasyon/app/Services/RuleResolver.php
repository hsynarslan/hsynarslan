<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\FacilityRule;
use App\Models\Setting;
use App\Models\User;

/** Geçerli kural değerini belirler: tesis+rol → tesis varsayılanı → sistem varsayılanı. */
class RuleResolver
{
    /** @return array<string,int> */
    public function for(Facility $facility, User $user): array
    {
        $rules = $facility->relationLoaded('rules') ? $facility->rules : $facility->rules()->get();
        $roleRule = $rules->firstWhere('role', $user->bookingRole());
        $default = $rules->first(fn ($r) => $r->role === null);

        $resolved = [];
        foreach (FacilityRule::FIELDS as $field) {
            $resolved[$field] = $roleRule?->{$field} ?? $default?->{$field} ?? Setting::get('default_'.$field);
        }

        return $resolved;
    }
}
