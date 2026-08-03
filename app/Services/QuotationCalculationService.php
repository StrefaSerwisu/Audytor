<?php

namespace App\Services;

use App\Enums\QuotationStatus;
use App\Enums\SalesQualificationStatus;
use App\Models\PricingRule;
use App\Models\QualificationAnswer;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationCalculationService
{
    public function __construct(
        private readonly DecimalMath $math,
        private readonly QualificationConditionService $conditions,
    ) {}

    public function createForQualification(SalesQualification $qualification, User $actor): Quotation
    {
        if (! $actor->can('createQuotation', $qualification)) {
            throw new AuthorizationException('Brak uprawnien do utworzenia wyceny.');
        }

        if ($qualification->status !== SalesQualificationStatus::ReadyForPricing) {
            throw ValidationException::withMessages(['qualification' => 'Wycene mozna utworzyc tylko dla kwalifikacji gotowej do wyceny.']);
        }

        if (empty($qualification->qualification_snapshot) || ! $qualification->version()->exists()) {
            throw ValidationException::withMessages(['qualification' => 'Brak kompletnego historycznego snapshotu lub wersji audytu.']);
        }

        return DB::transaction(function () use ($qualification, $actor): Quotation {
            $locked = SalesQualification::query()->lockForUpdate()->findOrFail($qualification->id);
            $nextVersion = ((int) $locked->quotations()->max('version')) + 1;
            $locked->quotations()->where('is_current', true)->update(['is_current' => false]);
            $version = $locked->version()->firstOrFail();

            $quotation = Quotation::create([
                'number' => $this->nextNumber(),
                'sales_qualification_id' => $locked->id,
                'client_id' => $locked->client_id,
                'audit_type_id' => $locked->audit_type_id,
                'audit_type_version_id' => $locked->audit_type_version_id,
                'sales_owner_id' => $locked->sales_owner_id,
                'version' => $nextVersion,
                'is_current' => true,
                'status' => QuotationStatus::Draft,
                'hourly_rate' => $version->default_hourly_rate ?? '0.00',
                'engineers_count' => $version->default_engineers_count,
                'tax_rate' => $version->default_tax_rate,
                'valid_until' => now()->addDays($version->default_validity_days)->toDateString(),
                'calculation_snapshot' => [],
            ]);

            AuditLogService::record('quotation.created', $quotation, metadata: $this->metadata($quotation));
            $this->calculate($quotation, $actor);

            return $quotation->fresh(['lines', 'qualification', 'client', 'auditType', 'salesOwner']);
        });
    }

    public function calculate(Quotation $quotation, User $actor): void
    {
        $quotation->loadMissing(['qualification.answers', 'qualification.version.pricingRules']);
        $qualification = $quotation->qualification;
        $answers = $qualification->answers->mapWithKeys(fn (QualificationAnswer $answer): array => [
            $answer->question_code => data_get($answer->value_json, 'value'),
        ])->all();
        $rules = collect(data_get($qualification->qualification_snapshot, 'pricing_rules'));

        if ($rules->isEmpty()) {
            $rules = $qualification->version->pricingRules->where('active', true)->map->snapshot();
        }

        $lines = [];
        $hoursBeforeRule = '0.00';
        $priceBeforeRule = '0.00';

        foreach ($rules->sortBy('sort_order') as $rule) {
            if (! ($rule['active'] ?? true) || ! $this->ruleMatches($rule, $qualification, $answers)) {
                continue;
            }

            $quantity = $this->quantity($rule, $qualification, $answers);
            $line = $this->lineForRule($rule, $quantity, $hoursBeforeRule, $priceBeforeRule, (string) $quotation->hourly_rate);

            if ($line['total_hours'] === '0.00' && $line['total_price'] === '0.00') {
                continue;
            }

            $lines[] = $line;
            $hoursBeforeRule = $this->math->add($hoursBeforeRule, $line['total_hours']);
            $priceBeforeRule = $this->math->add($priceBeforeRule, $line['total_price']);
        }

        $versionData = data_get($qualification->qualification_snapshot, 'version', []);
        $minimumHours = (string) ($versionData['minimum_hours'] ?? $qualification->version->minimum_hours ?? '0');
        $reservePercent = (string) ($versionData['reserve_percent'] ?? $qualification->version->reserve_percent ?? '0');
        $minimumPrice = (string) ($versionData['minimum_price'] ?? $qualification->version->minimum_price ?? '0');

        if (bccomp($reservePercent, '0', 2) > 0) {
            $reserveHours = $this->math->percent($hoursBeforeRule, $reservePercent);
            $lines[] = $this->systemLine('reserve', 'Rezerwa '.$reservePercent.'%', 'reserve', $reservePercent, $reserveHours);
            $hoursBeforeRule = $this->math->add($hoursBeforeRule, $reserveHours);
        }

        if (bccomp($hoursBeforeRule, $minimumHours, 2) < 0) {
            $difference = $this->math->subtract($minimumHours, $hoursBeforeRule);
            $lines[] = $this->systemLine('minimum-hours', 'Dopelnienie do minimum godzin', 'reserve', '1', $difference);
            $hoursBeforeRule = $minimumHours;
        }

        $quotation->lines()->delete();
        foreach ($lines as $index => $line) {
            $quotation->lines()->create([...$line, 'sort_order' => ($index + 1) * 10]);
        }

        $totals = $this->totals($quotation, $hoursBeforeRule, $priceBeforeRule, $minimumPrice);
        $snapshot = [
            'engine' => '2C-v1',
            'qualification_id' => $qualification->id,
            'audit_type_version_id' => $qualification->audit_type_version_id,
            'answers' => $answers,
            'pricing_rules' => $rules->values()->all(),
            'parameters' => [
                'minimum_hours' => $this->math->normalize($minimumHours),
                'minimum_price' => $this->math->normalize($minimumPrice),
                'reserve_percent' => $this->math->normalize($reservePercent),
                'hourly_rate' => $this->math->normalize($quotation->hourly_rate),
                'tax_rate' => $this->math->normalize($quotation->tax_rate),
            ],
            'lines' => $lines,
            'totals' => $totals,
            'calculated_at' => now()->toISOString(),
        ];

        $quotation->forceFill([
            ...$totals,
            'status' => QuotationStatus::Calculated,
            'calculation_snapshot' => $snapshot,
            'final_calculation_snapshot' => $snapshot,
            'calculated_at' => now(),
            'calculated_by' => $actor->id,
        ])->save();

        AuditLogService::record('quotation.calculated', $quotation, metadata: $this->metadata($quotation));
    }

    public function recalculateFinal(Quotation $quotation): void
    {
        $snapshot = $quotation->calculation_snapshot;
        $baseHours = (string) data_get($snapshot, 'totals.base_hours', $quotation->base_hours);
        $directPrice = collect(data_get($snapshot, 'lines', []))
            ->reduce(fn (string $sum, array $line): string => $this->math->add($sum, (string) ($line['total_price'] ?? '0')), '0.00');
        $minimumPrice = (string) data_get($snapshot, 'parameters.minimum_price', '0');
        $totals = $this->totals($quotation, $baseHours, $directPrice, $minimumPrice);

        $quotation->forceFill([
            ...$totals,
            'final_calculation_snapshot' => [
                ...$snapshot,
                'totals' => $totals,
                'overrides' => $quotation->overrides()->oldest('created_at')->get()
                    ->map->only(['field', 'old_value', 'new_value', 'reason', 'user_id', 'created_at'])->all(),
                'recalculated_at' => now()->toISOString(),
            ],
        ])->save();
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $answers */
    private function ruleMatches(array $rule, SalesQualification $qualification, array $answers): bool
    {
        $type = $rule['rule_type'] ?? 'always';
        $code = $rule['source_question_code'] ?? null;
        $comparison = data_get($rule, 'comparison_value.value', $rule['comparison_value'] ?? null);

        return match ($type) {
            'always' => true,
            'answer_exists' => $code && array_key_exists($code, $answers) && ! in_array($answers[$code], [null, '', []], true),
            'answer_equals' => $this->conditions->matches(['question_code' => $code, 'operator' => 'equals', 'value' => $comparison], $answers),
            'answer_not_equals' => $this->conditions->matches(['question_code' => $code, 'operator' => 'not_equals', 'value' => $comparison], $answers),
            'answer_greater_than' => $this->conditions->matches(['question_code' => $code, 'operator' => 'greater_than', 'value' => $comparison], $answers),
            'answer_less_than' => $this->conditions->matches(['question_code' => $code, 'operator' => 'less_than', 'value' => $comparison], $answers),
            'answer_contains' => $this->conditions->matches(['question_code' => $code, 'operator' => 'contains', 'value' => $comparison], $answers),
            'module_active' => collect($this->conditions->visibleModules($qualification->qualification_snapshot, $answers))
                ->contains(fn (array $module): bool => ($module['code'] ?? null) === $code && ($module['active'] ?? false)),
            default => false,
        };
    }

    /** @param array<string, mixed> $rule @param array<string, mixed> $answers */
    private function quantity(array $rule, SalesQualification $qualification, array $answers): string
    {
        $source = (string) ($rule['quantity_source'] ?? 'fixed');
        $value = match (true) {
            str_starts_with($source, 'answer:') => $answers[substr($source, 7)] ?? 0,
            $source === 'locations_count' => $qualification->client()->firstOrFail()->locations()->count(),
            $source === 'active_sales_modules_count' => count($this->conditions->visibleModules($qualification->qualification_snapshot, $answers)),
            default => $rule['fixed_quantity'] ?? 1,
        };

        if (is_array($value)) {
            $value = count($value);
        } elseif (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $quantity = is_numeric($value) ? $this->math->normalize((string) $value) : '0.00';
        if (($rule['minimum_value'] ?? null) !== null) {
            $quantity = $this->math->max($quantity, (string) $rule['minimum_value']);
        }
        if (($rule['maximum_value'] ?? null) !== null) {
            $quantity = $this->math->min($quantity, (string) $rule['maximum_value']);
        }

        return $quantity;
    }

    /** @param array<string, mixed> $rule @return array<string, mixed> */
    private function lineForRule(array $rule, string $quantity, string $hoursBefore, string $priceBefore, string $hourlyRate): array
    {
        $type = $rule['calculation_type'];
        $unitHours = (string) ($rule['hours_per_unit'] ?? '0');
        $unitPrice = (string) ($rule['unit_price'] ?? '0');
        $totalHours = match ($type) {
            'fixed_hours' => $this->math->normalize((string) ($rule['fixed_hours'] ?? '0')),
            'hours_per_quantity' => $this->math->multiply($quantity, $unitHours),
            'hours_and_price' => $this->math->add((string) ($rule['fixed_hours'] ?? '0'), $this->math->multiply($quantity, $unitHours)),
            'percentage_of_hours' => $this->math->percent($hoursBefore, $quantity),
            default => '0.00',
        };
        $totalPrice = match ($type) {
            'fixed_price' => $this->math->normalize((string) ($rule['fixed_price'] ?? '0')),
            'price_per_quantity' => $this->math->multiply($quantity, $unitPrice),
            'hours_and_price' => $this->math->add((string) ($rule['fixed_price'] ?? '0'), $this->math->multiply($quantity, $unitPrice)),
            'percentage_of_price' => $this->math->percent(
                $this->math->add($priceBefore, $this->math->multiply($hoursBefore, $hourlyRate)),
                $quantity,
            ),
            default => '0.00',
        };

        return [
            'code' => $rule['code'], 'name' => $rule['name'], 'description' => $rule['description'] ?? null,
            'category' => $rule['category'], 'source_type' => PricingRule::class, 'source_id' => $rule['id'] ?? null,
            'quantity' => $quantity, 'unit' => data_get($rule, 'configuration.unit', 'szt.'),
            'unit_hours' => $this->math->normalize($unitHours), 'total_hours' => $totalHours,
            'unit_price' => $this->math->normalize($unitPrice), 'total_price' => $totalPrice,
            'metadata' => ['rule_type' => $rule['rule_type'], 'calculation_type' => $type],
        ];
    }

    /** @return array<string, mixed> */
    private function systemLine(string $code, string $name, string $category, string $quantity, string $hours): array
    {
        return [
            'code' => $code, 'name' => $name, 'description' => null, 'category' => $category,
            'source_type' => 'system', 'source_id' => null, 'quantity' => $this->math->normalize($quantity),
            'unit' => '%', 'unit_hours' => '0.00', 'total_hours' => $this->math->normalize($hours),
            'unit_price' => '0.00', 'total_price' => '0.00', 'metadata' => ['system' => true],
        ];
    }

    /** @return array<string, string> */
    private function totals(Quotation $quotation, string $baseHours, string $directPrice, string $minimumPrice): array
    {
        $totalHours = $this->math->add($baseHours, $quotation->additional_hours);
        $hourlyPrice = $this->math->multiply($totalHours, $quotation->hourly_rate);
        $basePrice = $this->math->add($hourlyPrice, $directPrice);
        $beforeDiscount = $this->math->max($this->math->add($basePrice, $quotation->additional_costs), $minimumPrice);
        $discount = match ($quotation->discount_type) {
            'percent' => $this->math->percent($beforeDiscount, $quotation->discount_value),
            'fixed' => $this->math->normalize($quotation->discount_value),
            default => '0.00',
        };
        $discount = $this->math->min($discount, $beforeDiscount);
        $net = $this->math->subtract($beforeDiscount, $discount);
        $tax = $this->math->percent($net, $quotation->tax_rate);

        return [
            'base_hours' => $this->math->normalize($baseHours), 'total_hours' => $totalHours,
            'base_price' => $basePrice, 'discount_amount' => $discount,
            'net_price' => $net, 'tax_amount' => $tax, 'gross_price' => $this->math->add($net, $tax),
        ];
    }

    private function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        DB::table('quotation_sequences')->insertOrIgnore([
            'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sequence = DB::table('quotation_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = ((int) $sequence->last_number) + 1;
        DB::table('quotation_sequences')->where('year', $year)->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('AUD/%d/%04d', $year, $next);
    }

    /** @return array<string, mixed> */
    private function metadata(Quotation $quotation): array
    {
        return [
            'quotation_id' => $quotation->id, 'qualification_id' => $quotation->sales_qualification_id,
            'number' => $quotation->number, 'status_to' => $quotation->status,
            'total_hours' => $quotation->total_hours, 'net_price' => $quotation->net_price,
        ];
    }
}
