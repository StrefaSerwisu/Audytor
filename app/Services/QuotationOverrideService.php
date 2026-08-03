<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\User;
use App\Support\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationOverrideService
{
    public const FIELDS = [
        'hourly_rate', 'engineers_count', 'additional_hours', 'additional_costs',
        'discount_type', 'discount_value', 'valid_until', 'assumptions', 'exclusions',
    ];

    public function __construct(private readonly QuotationCalculationService $calculator) {}

    /** @param array<string, mixed> $changes */
    public function apply(Quotation $quotation, User $actor, array $changes, string $reason): void
    {
        if (! $actor->can('override', $quotation)) {
            throw new AuthorizationException('Brak uprawnien do korekty wyceny.');
        }

        $changes = collect($changes)->only(self::FIELDS)->reject(fn (mixed $value): bool => $value === null)->all();
        if ($changes === []) {
            throw ValidationException::withMessages(['override' => 'Wprowadz przynajmniej jedna zmiane.']);
        }

        DB::transaction(function () use ($quotation, $actor, $changes, $reason): void {
            $quotation->refresh();

            foreach ($changes as $field => $value) {
                $old = $quotation->getAttribute($field);
                if ((string) $old === (string) $value) {
                    continue;
                }

                $quotation->overrides()->create([
                    'user_id' => $actor->id,
                    'field' => $field,
                    'old_value' => $old instanceof \Stringable ? (string) $old : $old,
                    'new_value' => is_array($value) ? json_encode($value) : (string) $value,
                    'reason' => $reason,
                    'created_at' => now(),
                ]);
                $quotation->setAttribute($field, $value);
            }

            $quotation->save();
            $this->calculator->recalculateFinal($quotation);
            AuditLogService::record('quotation.override_added', $quotation, metadata: [
                ...$this->metadata($quotation), 'fields' => array_keys($changes), 'reason' => mb_substr($reason, 0, 250),
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function metadata(Quotation $quotation): array
    {
        return [
            'quotation_id' => $quotation->id, 'qualification_id' => $quotation->sales_qualification_id,
            'number' => $quotation->number, 'total_hours' => $quotation->total_hours, 'net_price' => $quotation->net_price,
        ];
    }
}
