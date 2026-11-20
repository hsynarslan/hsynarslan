<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Sanction;
use App\Models\Setting;
use App\Models\User;

/** Gelmeme / geç iptal yaptırımı. */
class SanctionService
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Son pencere içindeki ihlal sayısı (son otomatik yaptırımdan sonrası sayılır). */
    public function offenseCount(User $user): int
    {
        $since = now()->subDays(Setting::get('sanction_window_days'));
        $last = $user->sanctions()->where('is_automatic', true)->whereNull('lifted_at')->latest('starts_at')->first();
        if ($last && $last->starts_at->gt($since)) {
            $since = $last->starts_at;
        }

        $noShows = Reservation::where('user_id', $user->id)->where('status', Reservation::APPROVED)
            ->where('attendance', 'no_show')->where('starts_at', '>=', $since)->where('starts_at', '<=', now())->count();
        $late = Reservation::where('user_id', $user->id)->where('is_late_cancel', true)
            ->where('cancelled_at', '>=', $since)->count();

        return $noShows + $late;
    }

    public function evaluate(User $user, bool $liftIfBelow = false): ?Sanction
    {
        if ($user->isExemptFromLimits()) {
            return null;
        }
        $threshold = Setting::get('sanction_threshold');
        $active = $user->sanctions()->active()->where('is_automatic', true)->first();

        if ($active) {
            // Görevli "gelmedi"yi "geldi" olarak düzelttiyse ve eşik altına inildiyse otomatik yaptırımı kaldır.
            if ($liftIfBelow && $this->countBefore($user, $active) < $threshold) {
                $this->lift($active, null);
            }

            return null;
        }

        $count = $this->offenseCount($user);
        if ($count < $threshold) {
            return null;
        }

        $sanction = Sanction::create([
            'user_id' => $user->id,
            'reason' => __(':n gelmeme / geç iptal (son :d gün)', ['n' => $count, 'd' => Setting::get('sanction_window_days')]),
            'starts_at' => now(),
            'ends_at' => now()->addDays(Setting::get('sanction_duration_days')),
            'is_automatic' => true,
        ]);
        Audit::log('sanction.applied', $sanction, ['user' => $user->id, 'count' => $count], null);
        $this->notifier->sanctioned($sanction->load('user'));

        return $sanction;
    }

    private function countBefore(User $user, Sanction $s): int
    {
        $since = $s->starts_at->copy()->subDays(Setting::get('sanction_window_days'));
        $noShows = Reservation::where('user_id', $user->id)->where('attendance', 'no_show')
            ->whereBetween('starts_at', [$since, $s->starts_at])->count();
        $late = Reservation::where('user_id', $user->id)->where('is_late_cancel', true)
            ->whereBetween('cancelled_at', [$since, $s->starts_at])->count();

        return $noShows + $late;
    }

    public function lift(Sanction $s, ?User $by): void
    {
        $s->lifted_at = now();
        $s->lifted_by = $by?->id;
        $s->save();
        Audit::log('sanction.lifted', $s, ['user' => $s->user_id]);
    }
}
