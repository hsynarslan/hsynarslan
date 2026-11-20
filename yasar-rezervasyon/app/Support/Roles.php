<?php

namespace App\Support;

/** Rezervasyon kurallarında kullanılan rol kodları. */
final class Roles
{
    public const STUDENT = 'student';

    public const ADMIN_STAFF = 'admin_staff';

    public const ACADEMIC = 'academic';

    public const ALUMNI = 'alumni';

    public const COACH = 'coach';

    /** LDAP'tan gelebilen temel roller. */
    public const BASE = [self::STUDENT, self::ADMIN_STAFF, self::ACADEMIC, self::ALUMNI];

    /** Tesis ve aktivitelerde izin verilebilen roller. */
    public const BOOKING = [self::STUDENT, self::ADMIN_STAFF, self::ACADEMIC, self::COACH, self::ALUMNI];

    public static function label(string $role): string
    {
        return __(match ($role) {
            self::STUDENT => 'Öğrenci',
            self::ADMIN_STAFF => 'İdari Personel',
            self::ACADEMIC => 'Akademik Personel',
            self::ALUMNI => 'Mezun',
            self::COACH => 'Antrenör',
            default => $role,
        });
    }

    /** @return array<string,string> */
    public static function bookingOptions(): array
    {
        return collect(self::BOOKING)->mapWithKeys(fn ($r) => [$r => self::label($r)])->all();
    }
}
