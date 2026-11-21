<?php

// Eş zamanlılık (yük) testi — MySQL/MariaDB üzerinde çalıştırın, SQLite satır kilidi desteklemez.
// Kullanım: php tests/Concurrency/stress.php limit|slot|decide 30
// Beklenen: limit → 1 başarılı (günlük sınır), slot → 2 başarılı (2 pota), decide → onay ve red asla birlikte geçmez.
// UYARI: Veritabanına test kaydı yazar; yalnızca test veritabanında çalıştırın.
// Laravel'i yükler, N alt süreç çatallar; hepsi aynı anda (zaman bariyeri) servisi çağırır.
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->isProduction()) {
    fwrite(STDERR, "Üretim ortamında çalıştırılamaz.\n");
    exit(2);
}
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

Mail::fake();
[$_, $mode, $n] = $argv + [null, 'limit', 20];
$go = microtime(true) + 1.5;
$pids = [];
for ($i = 0; $i < $n; $i++) {
    $pid = pcntl_fork();
    if ($pid === 0) {
        DB::purge();
        DB::reconnect();
        $svc = app(ReservationService::class);
        time_sleep_until($go);
        try {
            if ($mode === 'limit') {          // aynı kullanıcı, aynı gün, farklı saatler
                $h = 8 + $i % 13;
                $svc->create(User::where('username', 'ogrenci')->first(), ['facility_id' => 1, 'activity_type_id' => 1, 'date' => '2026-11-26', 'start' => sprintf('%02d:00', $h), 'end' => sprintf('%02d:00', $h + 1)]);
            } elseif ($mode === 'slot') {     // farklı kullanıcılar, aynı saat (2 pota)
                $u = User::firstOrCreate(['username' => "yuk$i"], ['name' => "Yük $i", 'base_role' => 'student', 'number' => "9$i"]);
                $svc->create($u, ['facility_id' => 1, 'activity_type_id' => 1, 'date' => '2026-11-27', 'start' => '15:00', 'end' => '16:00']);
            } elseif ($mode === 'decide') {   // aynı talebe eş zamanlı onay / red / sahip iptali
                $r = Reservation::where('status', 'pending')->orderBy('id')->first();
                $admin = User::where('username', 'sksadmin')->first();
                match ($i % 3) {
                    0 => $svc->approve($admin, $r), 1 => $svc->reject($admin, $r, 'yük testi'), 2 => $svc->cancelByOwner($r->user, $r)
                };
            }
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, get_class($e).': '.mb_substr($e->getMessage(), 0, 160)."\n");
            exit(1);
        }
    }
    $pids[] = $pid;
}
$ok = 0;
foreach ($pids as $p) {
    pcntl_waitpid($p, $st);
    if (pcntl_wexitstatus($st) === 0) {
        $ok++;
    }
}
echo "$mode: $n eş zamanlı istekten $ok başarılı\n";
