<?php

use App\Services\Audit;
use App\Services\ScheduledTasks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('reservations:remind-approvers', fn (ScheduledTasks $t) => $this->info($t->remindApprovers().' hatırlatma'))
    ->purpose('Onay bekleyen talepler için onaycılara hatırlatma gönderir');
Artisan::command('reservations:expire', fn (ScheduledTasks $t) => $this->info($t->expirePending().' talep süresi doldu'))
    ->purpose('Başlangıç saatine kadar onaylanmayan talepleri iptal eder');
Artisan::command('reservations:remind-users', fn (ScheduledTasks $t) => $this->info($t->remindUsers().' hatırlatma'))
    ->purpose('Kullanıcılara 1 gün ve 2 saat önce hatırlatma gönderir');
Artisan::command('reservations:mark-no-shows', fn (ScheduledTasks $t) => $this->info($t->markNoShows().' gelmeme'))
    ->purpose('Check-in yapılmayan rezervasyonları "gelmedi" işaretler ve yaptırımı değerlendirir');
Artisan::command('players:health-warnings', fn (ScheduledTasks $t) => $this->info($t->healthWarnings().' uyarı'))
    ->purpose('Sağlık raporu süresi dolan oyuncular için antrenöre e-posta gönderir');
Artisan::command('retention:purge', fn (ScheduledTasks $t) => $this->info($t->purgeExpiredData().' rezervasyon anonimleştirildi'))
    ->purpose('Saklama süresi dolan kişisel verileri anonimleştirir');
Artisan::command('audit:verify', function () {
    $broken = Audit::verify();
    if ($broken) {
        Log::critical('İşlem kaydı zinciri bozuk', ['first_broken_id' => $broken]);
        $this->error("İşlem kaydı zinciri #$broken numaralı kayıtta bozuk!");

        return 1;
    }
    $this->info('İşlem kaydı zinciri sağlam.');
})->purpose('Hash zincirli işlem kaydının bütünlüğünü doğrular');

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:mark-no-shows')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:remind-approvers')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('reservations:remind-users')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('players:health-warnings')->dailyAt('08:30');
Schedule::command('retention:purge')->dailyAt('03:15');
Schedule::command('audit:verify')->dailyAt('03:45');
// Başarısız e-posta işleri (alıcı adı ve e-postası içerir) 30 gün sonra silinir (KVKK, ISO 27001 A.8.10).
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('03:30');
