<?php

namespace App\Models\Concerns;

trait HasLocalizedName
{
    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' ? ($this->name_en ?: $this->name_tr) : $this->name_tr;
    }

    public function localized(string $field): ?string
    {
        $en = $this->{$field.'_en'} ?? null;

        return app()->getLocale() === 'en' && $en ? $en : $this->{$field.'_tr'};
    }
}
