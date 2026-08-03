<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditTrail
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            'actor_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => self::sanitize($oldValues) ?: null,
            'new_values' => self::sanitize($newValues) ?: null,
            'metadata' => self::sanitize($metadata) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function sanitize(array $values): array
    {
        $sensitiveKeys = ['password', 'remember_token', 'token', 'secret'];

        return collect($values)
            ->mapWithKeys(function (mixed $value, string|int $key) use ($sensitiveKeys): array {
                if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                    return [$key => '[REDACTED]'];
                }

                if (is_array($value)) {
                    return [$key => self::sanitize($value)];
                }

                if ($value instanceof \BackedEnum) {
                    return [$key => $value->value];
                }

                return [$key => $value];
            })
            ->all();
    }
}
