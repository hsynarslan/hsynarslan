<?php

namespace App\Services;

use App\Models\Facility;

/**
 * Dinamik QR: tesis girişindeki tablet her PERIOD saniyede yeni, imzalı bir bağlantı gösterir.
 * Bağlantı yalnızca kısa bir süre geçerlidir; önceden çekilmiş bir fotoğraf ya da kaydedilmiş adres
 * sonradan kullanılamaz (ISO 27001 A.8.5). Not: Canlı olarak paylaşılan bir ekran görüntüsünü
 * tamamen engellemez; bu nedenle görevli işaretlemesi de devam eder.
 */
class CheckinToken
{
    public const PERIOD = 30;

    /** Kabul edilen geriye dönük pencere sayısı (QR okutma + onay adımı için tolerans). */
    public const GRACE_WINDOWS = 2;

    public static function window(?int $time = null): int
    {
        return intdiv($time ?? now()->getTimestamp(), self::PERIOD);
    }

    public static function sign(Facility $facility, int $window): string
    {
        $payload = 'checkin|'.$facility->id.'|'.$window.'|'.$facility->checkin_token;

        return substr(hash_hmac('sha256', $payload, (string) config('app.key')), 0, 32);
    }

    public static function url(Facility $facility, ?int $window = null): string
    {
        $window ??= self::window();

        return route('checkin.dynamic', ['facility' => $facility->id, 'window' => $window, 'sig' => self::sign($facility, $window)]);
    }

    public static function valid(Facility $facility, int $window, string $sig): bool
    {
        $now = self::window();
        if ($window > $now || $window < $now - self::GRACE_WINDOWS) {
            return false;
        }

        return hash_equals(self::sign($facility, $window), $sig);
    }
}
