<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AttendantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CheckinController;
use App\Http\Controllers\KvkkController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/locale', [AuthController::class, 'locale'])->name('locale');

Route::middleware(['auth', 'throttle:app'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/kvkk', [KvkkController::class, 'show'])->name('kvkk.show');
    Route::post('/kvkk', [KvkkController::class, 'accept'])->name('kvkk.accept');

    Route::middleware('kvkk')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('calendar');

        Route::get('/rezervasyonlarim', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/rezervasyon/yeni', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/rezervasyon', [ReservationController::class, 'store'])->name('reservations.store');
        Route::post('/rezervasyon/{reservation}/iptal', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::post('/rezervasyon/{reservation}/ayril', [ReservationController::class, 'leave'])->name('reservations.leave');

        Route::get('/checkin/{token}', [CheckinController::class, 'show'])->name('checkin.show');
        Route::post('/checkin/{token}', [CheckinController::class, 'store'])->name('checkin.store');

        Route::middleware('role:approver')->group(function () {
            Route::get('/onaylar', [ApprovalController::class, 'index'])->name('approvals.index');
            Route::post('/onaylar/{reservation}/onayla', [ApprovalController::class, 'approve'])->name('approvals.approve');
            Route::post('/onaylar/{reservation}/reddet', [ApprovalController::class, 'reject'])->name('approvals.reject');
            Route::post('/onaylar/{reservation}/iptal', [ApprovalController::class, 'cancel'])->name('approvals.cancel');
            Route::get('/raporlar', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/raporlar/indir/{format}', [ReportController::class, 'export'])->whereIn('format', ['xlsx', 'pdf'])->name('reports.export');
        });

        Route::middleware('role:attendant')->group(function () {
            Route::get('/gunluk', [AttendantController::class, 'index'])->name('attendant.index');
            Route::post('/gunluk/{reservation}/katilim', [AttendantController::class, 'attendance'])->name('attendant.attendance');
            Route::post('/gunluk/ekipman/{item}', [AttendantController::class, 'equipment'])->name('attendant.equipment');
        });

        Route::middleware('role:coach')->group(function () {
            Route::get('/takimlar', [TeamController::class, 'index'])->name('teams.index');
            Route::get('/takimlar/{team}', [TeamController::class, 'show'])->name('teams.show');
            Route::get('/takimlar/{team}/oyuncu/yeni', [TeamController::class, 'createPlayer'])->name('teams.players.create');
            Route::post('/takimlar/{team}/oyuncu', [TeamController::class, 'storePlayer'])->name('teams.players.store');
            Route::get('/takimlar/{team}/oyuncu/{player}', [TeamController::class, 'showPlayer'])->name('teams.players.show');
            Route::put('/takimlar/{team}/oyuncu/{player}', [TeamController::class, 'updatePlayer'])->name('teams.players.update');
            Route::delete('/takimlar/{team}/oyuncu/{player}', [TeamController::class, 'removePlayer'])->name('teams.players.remove');
            Route::get('/takimlar/{team}/oyuncu/{player}/foto', [TeamController::class, 'photo'])->name('teams.players.photo');
        });

        Route::middleware('role:sks_admin')->prefix('yonetim')->name('admin.')->group(function () {
            Route::resource('tesisler', Admin\FacilityController::class)->parameters(['tesisler' => 'facility'])
                ->except(['show', 'destroy'])->names('facilities');
            Route::post('tesisler/{facility}/birim', [Admin\FacilityController::class, 'addUnit'])->name('facilities.units.store');
            Route::delete('tesisler/{facility}/birim/{unit}', [Admin\FacilityController::class, 'removeUnit'])->name('facilities.units.destroy');
            Route::post('tesisler/{facility}/aktivite', [Admin\FacilityController::class, 'saveActivity'])->name('facilities.activities.save');
            Route::put('tesisler/{facility}/saatler', [Admin\FacilityController::class, 'updateHours'])->name('facilities.hours');
            Route::put('tesisler/{facility}/kurallar', [Admin\FacilityController::class, 'updateRules'])->name('facilities.rules');
            Route::put('tesisler/{facility}/onaycilar', [Admin\FacilityController::class, 'updateApprovers'])->name('facilities.approvers');
            Route::post('tesisler/{facility}/qr-yenile', [Admin\FacilityController::class, 'regenerateToken'])->name('facilities.token');
            Route::get('tesisler/{facility}/qr', [Admin\FacilityController::class, 'qr'])->name('facilities.qr');

            Route::get('kapatmalar', [Admin\ClosureController::class, 'index'])->name('closures.index');
            Route::post('kapatmalar', [Admin\ClosureController::class, 'store'])->name('closures.store');
            Route::delete('kapatmalar/{closure}', [Admin\ClosureController::class, 'destroy'])->name('closures.destroy');

            Route::get('tatiller', [Admin\HolidayController::class, 'index'])->name('holidays.index');
            Route::post('tatiller', [Admin\HolidayController::class, 'store'])->name('holidays.store');
            Route::delete('tatiller/{holiday}', [Admin\HolidayController::class, 'destroy'])->name('holidays.destroy');

            Route::get('bloklar', [Admin\BlockController::class, 'index'])->name('blocks.index');
            Route::post('bloklar', [Admin\BlockController::class, 'store'])->name('blocks.store');
            Route::delete('bloklar/{block}', [Admin\BlockController::class, 'destroy'])->name('blocks.destroy');

            Route::get('takimlar', [Admin\TeamAdminController::class, 'index'])->name('teams.index');
            Route::post('takimlar', [Admin\TeamAdminController::class, 'store'])->name('teams.store');
            Route::put('takimlar/{team}', [Admin\TeamAdminController::class, 'update'])->name('teams.update');

            Route::get('ekipman', [Admin\EquipmentController::class, 'index'])->name('equipment.index');
            Route::post('ekipman', [Admin\EquipmentController::class, 'store'])->name('equipment.store');
            Route::put('ekipman/{equipment}', [Admin\EquipmentController::class, 'update'])->name('equipment.update');

            Route::get('kullanicilar', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('kullanicilar/ice-aktar', [Admin\UserController::class, 'import'])->name('users.import');
            Route::put('kullanicilar/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('kullanicilar/{user}/yaptirim', [Admin\UserController::class, 'sanction'])->name('users.sanction');
            Route::post('yaptirim/{sanction}/kaldir', [Admin\UserController::class, 'liftSanction'])->name('sanctions.lift');

            Route::get('ayarlar', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('ayarlar', [Admin\SettingController::class, 'update'])->name('settings.update');

            Route::get('kvkk', [Admin\KvkkTextController::class, 'index'])->name('kvkk.index');
            Route::post('kvkk', [Admin\KvkkTextController::class, 'store'])->name('kvkk.store');

            Route::get('islem-kaydi', [Admin\AuditController::class, 'index'])->name('audit.index');
        });
    });
});
