<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

/**
 * Türkiye resmi tatilleri. Dini bayram tarihleri Diyanet takvimine göre her yıl kontrol edilmelidir.
 * closed_from: yarım gün (arife) için kapanış saati.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $list = [
            ['2026-01-01', 'Yılbaşı', 'New Year\'s Day'],
            ['2026-03-19', 'Ramazan Bayramı Arifesi', 'Eid al-Fitr Eve', '13:00'],
            ['2026-03-20', 'Ramazan Bayramı 1. Gün', 'Eid al-Fitr Day 1'],
            ['2026-03-21', 'Ramazan Bayramı 2. Gün', 'Eid al-Fitr Day 2'],
            ['2026-03-22', 'Ramazan Bayramı 3. Gün', 'Eid al-Fitr Day 3'],
            ['2026-04-23', 'Ulusal Egemenlik ve Çocuk Bayramı', 'National Sovereignty and Children\'s Day'],
            ['2026-05-01', 'Emek ve Dayanışma Günü', 'Labour and Solidarity Day'],
            ['2026-05-19', 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı', 'Commemoration of Atatürk, Youth and Sports Day'],
            ['2026-05-26', 'Kurban Bayramı Arifesi', 'Eid al-Adha Eve', '13:00'],
            ['2026-05-27', 'Kurban Bayramı 1. Gün', 'Eid al-Adha Day 1'],
            ['2026-05-28', 'Kurban Bayramı 2. Gün', 'Eid al-Adha Day 2'],
            ['2026-05-29', 'Kurban Bayramı 3. Gün', 'Eid al-Adha Day 3'],
            ['2026-05-30', 'Kurban Bayramı 4. Gün', 'Eid al-Adha Day 4'],
            ['2026-07-15', 'Demokrasi ve Millî Birlik Günü', 'Democracy and National Unity Day'],
            ['2026-08-30', 'Zafer Bayramı', 'Victory Day'],
            ['2026-10-28', 'Cumhuriyet Bayramı Arifesi', 'Republic Day Eve', '13:00'],
            ['2026-10-29', 'Cumhuriyet Bayramı', 'Republic Day'],
            ['2027-01-01', 'Yılbaşı', 'New Year\'s Day'],
            ['2027-03-08', 'Ramazan Bayramı Arifesi', 'Eid al-Fitr Eve', '13:00'],
            ['2027-03-09', 'Ramazan Bayramı 1. Gün', 'Eid al-Fitr Day 1'],
            ['2027-03-10', 'Ramazan Bayramı 2. Gün', 'Eid al-Fitr Day 2'],
            ['2027-03-11', 'Ramazan Bayramı 3. Gün', 'Eid al-Fitr Day 3'],
            ['2027-04-23', 'Ulusal Egemenlik ve Çocuk Bayramı', 'National Sovereignty and Children\'s Day'],
            ['2027-05-01', 'Emek ve Dayanışma Günü', 'Labour and Solidarity Day'],
            ['2027-05-15', 'Kurban Bayramı Arifesi', 'Eid al-Adha Eve', '13:00'],
            ['2027-05-16', 'Kurban Bayramı 1. Gün', 'Eid al-Adha Day 1'],
            ['2027-05-17', 'Kurban Bayramı 2. Gün', 'Eid al-Adha Day 2'],
            ['2027-05-18', 'Kurban Bayramı 3. Gün', 'Eid al-Adha Day 3'],
            ['2027-05-19', 'Kurban Bayramı 4. Gün / Gençlik ve Spor Bayramı', 'Eid al-Adha Day 4 / Youth and Sports Day'],
            ['2027-07-15', 'Demokrasi ve Millî Birlik Günü', 'Democracy and National Unity Day'],
            ['2027-08-30', 'Zafer Bayramı', 'Victory Day'],
            ['2027-10-28', 'Cumhuriyet Bayramı Arifesi', 'Republic Day Eve', '13:00'],
            ['2027-10-29', 'Cumhuriyet Bayramı', 'Republic Day'],
        ];
        foreach ($list as $h) {
            Holiday::updateOrCreate(['date' => $h[0]], ['name_tr' => $h[1], 'name_en' => $h[2], 'closed_from' => $h[3] ?? null]);
        }
    }
}
