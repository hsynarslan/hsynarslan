<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hash zincirli, yalnızca eklemeli işlem kaydı (ISO 27001 A.8.15 Kayıt tutma, A.5.28 Delil toplama).
 * Her kayıt bir öncekinin hash'ini içerir; araya kayıt eklenmesi, silinmesi veya değiştirilmesi
 * `php artisan audit:verify` ile tespit edilir.
 */
class Audit
{
    /** Kayda asla açık yazılmayacak anahtarlar. */
    private const MASKED = ['password', 'token', 'checkin_token', '_token', 'secret',
        'birth_date', 'phone', 'license_no', 'health_report_date', 'health_report_expires_on',
        'emergency_contact_name', 'emergency_contact_phone', 'notes'];

    public static function log(string $action, ?Model $subject = null, array $data = [], ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return DB::transaction(function () use ($action, $subject, $data, $userId, $request) {
            $prev = AuditLog::query()->orderByDesc('id')->lockForUpdate()->first();
            $record = [
                'created_at' => now()->format('Y-m-d H:i:s'),
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'data' => self::mask($data) ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'prev_hash' => $prev?->hash,
            ];
            $record['hash'] = self::hash($record);

            return AuditLog::create($record);
        });
    }

    public static function hash(array $record): string
    {
        $payload = json_encode([
            $record['prev_hash'], substr((string) $record['created_at'], 0, 19),
            $record['user_id'] === null ? null : (int) $record['user_id'], $record['action'], $record['subject_type'],
            $record['subject_id'] === null ? null : (int) $record['subject_id'], $record['data'], $record['ip_address'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /** Zinciri doğrular; bozuk ilk kaydın kimliğini döndürür, sağlamsa null. */
    public static function verify(): ?int
    {
        $prevHash = null;
        foreach (AuditLog::query()->orderBy('id')->lazy(500) as $log) {
            $record = [
                'prev_hash' => $log->prev_hash,
                'created_at' => $log->getRawOriginal('created_at'),
                'user_id' => $log->user_id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'data' => $log->data,
                'ip_address' => $log->ip_address,
            ];
            if ($log->prev_hash !== $prevHash || ! hash_equals($log->hash, self::hash($record))) {
                return $log->id;
            }
            $prevHash = $log->hash;
        }

        return null;
    }

    private static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::mask($value);
            } elseif (in_array($key, self::MASKED, true)) {
                $data[$key] = $value === null ? null : '***';
            }
        }

        return $data;
    }
}
