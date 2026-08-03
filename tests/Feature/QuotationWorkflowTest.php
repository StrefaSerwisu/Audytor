<?php

namespace Tests\Feature;

use App\Enums\QuotationStatus;
use App\Enums\SalesQualificationStatus;
use App\Enums\UserRole;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\QualificationAnswer;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\User;
use App\Services\QuotationCalculationService;
use App\Services\QuotationOverrideService;
use App\Services\QuotationWorkflowService;
use App\Services\SalesQualificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuotationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_rule_can_be_created_only_for_mutable_draft_version(): void
    {
        [$admin] = $this->actors();
        [, $version] = $this->draftType();
        $rule = $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00']);

        $this->assertDatabaseHas('pricing_rules', ['id' => $rule->id, 'code' => 'base']);
        $version->publish($admin);

        $this->expectException(ValidationException::class);
        $rule->update(['fixed_hours' => '5.00']);
    }

    public function test_pricing_rules_are_included_in_version_and_qualification_snapshots(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00']);
        $version->publish($admin);
        $qualification = $this->qualification($sales, $client, $type, 10);

        $this->assertSame('base', $version->snapshot()['pricing_rules'][0]['code']);
        $this->assertSame('base', $qualification->qualification_snapshot['pricing_rules'][0]['code']);
    }

    public function test_ready_qualification_creates_calculated_quotation_with_transactional_number(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00']);
        $version->publish($admin);
        $qualification = $this->qualification($sales, $client, $type, 10);

        $this->actingAs($sales)->post(route('sales.qualifications.quotation.store', $qualification))->assertRedirect();
        $quotation = Quotation::firstOrFail();

        $this->assertMatchesRegularExpression('/^AUD\/\d{4}\/0001$/', $quotation->number);
        $this->assertSame(QuotationStatus::Calculated, $quotation->status);
        $this->assertSame('4.00', $quotation->total_hours);
        $this->assertSame('400.00', $quotation->net_price);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation.created', 'subject_id' => $quotation->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation.calculated', 'subject_id' => $quotation->id]);
    }

    public function test_non_ready_qualification_cannot_create_quotation(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $qualification = $this->qualification($sales, $client, $type, 1, SalesQualificationStatus::InProgress);

        $this->expectException(ValidationException::class);
        $this->calculate($qualification, $sales);
    }

    public function test_fixed_quantity_and_percentage_rules_calculate_hours_and_prices(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        foreach ([
            ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00'],
            ['code' => 'users-hours', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'answer:users_count', 'hours_per_unit' => '0.15'],
            ['code' => 'setup-price', 'calculation_type' => 'fixed_price', 'fixed_price' => '500.00'],
            ['code' => 'users-price', 'calculation_type' => 'price_per_quantity', 'quantity_source' => 'answer:users_count', 'unit_price' => '10.00'],
            ['code' => 'rule-reserve', 'calculation_type' => 'percentage_of_hours', 'fixed_quantity' => '10.00'],
        ] as $rule) {
            $this->rule($version, $rule);
        }
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 100), $sales);

        $this->assertSame('20.90', $quotation->total_hours);
        $this->assertSame('3590.00', $quotation->net_price);
        $this->assertSame('825.70', $quotation->tax_amount);
        $this->assertSame('4415.70', $quotation->gross_price);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $quotation->id, 'code' => 'users-hours', 'total_hours' => '15.00']);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $quotation->id, 'code' => 'users-price', 'total_price' => '1000.00']);
    }

    public function test_hours_and_price_rule_calculates_both_dimensions(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, [
            'code' => 'combined', 'calculation_type' => 'hours_and_price', 'quantity_source' => 'answer:users_count',
            'hours_per_unit' => '0.50', 'fixed_hours' => '1.00', 'unit_price' => '20.00', 'fixed_price' => '100.00',
        ]);
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 10), $sales);

        $this->assertSame('6.00', $quotation->total_hours);
        $this->assertSame('900.00', $quotation->net_price);
    }

    public function test_minimum_hours_reserve_minimum_price_and_vat_are_applied_in_order(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType([
            'minimum_hours' => '12.00', 'reserve_percent' => '15.00', 'minimum_price' => '2000.00',
        ]);
        $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '4.00']);
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);

        $this->assertSame('12.00', $quotation->total_hours);
        $this->assertSame('2000.00', $quotation->net_price);
        $this->assertSame('460.00', $quotation->tax_amount);
        $this->assertSame('2460.00', $quotation->gross_price);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $quotation->id, 'code' => 'reserve', 'total_hours' => '0.60']);
        $this->assertDatabaseHas('quotation_lines', ['quotation_id' => $quotation->id, 'code' => 'minimum-hours', 'total_hours' => '7.40']);
    }

    public function test_quantity_can_come_from_locations_and_active_modules(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        $client->locations()->createMany([
            ['name' => 'A', 'location_type' => 'office'], ['name' => 'B', 'location_type' => 'branch'],
        ]);
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'locations', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'locations_count', 'hours_per_unit' => '2.00']);
        $this->rule($version, ['code' => 'modules', 'calculation_type' => 'hours_per_quantity', 'quantity_source' => 'active_sales_modules_count', 'hours_per_unit' => '1.00']);
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);

        $this->assertSame('5.00', $quotation->total_hours);
        $this->assertDatabaseHas('quotation_lines', ['code' => 'locations', 'quantity' => '2.00']);
        $this->assertDatabaseHas('quotation_lines', ['code' => 'modules', 'quantity' => '1.00']);
    }

    public function test_all_answer_condition_rule_types_are_evaluated(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        foreach ([
            ['code' => 'exists', 'rule_type' => 'answer_exists'],
            ['code' => 'equals', 'rule_type' => 'answer_equals', 'comparison_value' => ['value' => 10]],
            ['code' => 'not-equals', 'rule_type' => 'answer_not_equals', 'comparison_value' => ['value' => 20]],
            ['code' => 'greater', 'rule_type' => 'answer_greater_than', 'comparison_value' => ['value' => 5]],
            ['code' => 'less', 'rule_type' => 'answer_less_than', 'comparison_value' => ['value' => 15]],
        ] as $rule) {
            $this->rule($version, [...$rule, 'source_question_code' => 'users_count', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '1.00']);
        }
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 10), $sales);

        $this->assertSame('5.00', $quotation->total_hours);
        $this->assertCount(5, $quotation->lines);
    }

    public function test_contains_module_active_and_percentage_of_price_rules_are_supported(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'base-price', 'calculation_type' => 'fixed_price', 'fixed_price' => '1000.00']);
        $this->rule($version, [
            'code' => 'contains', 'rule_type' => 'answer_contains', 'source_question_code' => 'services',
            'comparison_value' => ['value' => 'm365'], 'calculation_type' => 'fixed_hours', 'fixed_hours' => '1.00',
        ]);
        $this->rule($version, [
            'code' => 'module', 'rule_type' => 'module_active', 'source_question_code' => 'SALES',
            'calculation_type' => 'fixed_hours', 'fixed_hours' => '1.00',
        ]);
        $this->rule($version, [
            'code' => 'price-reserve', 'calculation_type' => 'percentage_of_price', 'fixed_quantity' => '10.00',
        ]);
        $version->publish($admin);
        $qualification = $this->qualification($sales, $client, $type, 1);
        QualificationAnswer::create([
            'sales_qualification_id' => $qualification->id, 'question_code' => 'services',
            'question_snapshot' => ['code' => 'services', 'field_type' => 'multiselect'],
            'value_json' => ['value' => ['m365', 'backup']], 'answered_by' => $sales->id, 'answered_at' => now(),
        ]);
        $quotation = $this->calculate($qualification, $sales);

        $this->assertSame('2.00', $quotation->total_hours);
        $this->assertSame('1320.00', $quotation->net_price);
        $this->assertDatabaseHas('quotation_lines', ['code' => 'price-reserve', 'total_price' => '120.00']);
    }

    public function test_calculation_snapshot_is_complete_and_not_changed_by_override(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '10.00']);
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);
        $snapshot = $quotation->calculation_snapshot;

        app(QuotationOverrideService::class)->apply($quotation, $sales, ['hourly_rate' => '120.00'], 'Zmiana stawki po uzgodnieniu.');

        $this->assertSame($snapshot, $quotation->refresh()->calculation_snapshot);
        $this->assertSame('1200.00', $quotation->net_price);
        $this->assertNotSame($snapshot, $quotation->final_calculation_snapshot);
        $this->assertDatabaseHas('quotation_overrides', ['quotation_id' => $quotation->id, 'field' => 'hourly_rate', 'reason' => 'Zmiana stawki po uzgodnieniu.']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation.override_added', 'subject_id' => $quotation->id]);
    }

    public function test_override_requires_reason_and_supports_percent_and_fixed_discount(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $this->rule($version, ['code' => 'base', 'calculation_type' => 'fixed_hours', 'fixed_hours' => '10.00']);
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);

        app(QuotationOverrideService::class)->apply($quotation, $sales, ['discount_type' => 'percent', 'discount_value' => '10.00'], 'Rabat handlowy.');
        $this->assertSame('900.00', $quotation->refresh()->net_price);
        app(QuotationOverrideService::class)->apply($quotation, $sales, ['discount_type' => 'fixed', 'discount_value' => '100.00'], 'Rabat kwotowy.');
        $this->assertSame('900.00', $quotation->refresh()->net_price);

        $this->actingAs($sales)->patch(route('sales.quotations.override', $quotation), ['hourly_rate' => '90.00'])
            ->assertSessionHasErrors('reason');
    }

    public function test_new_quotation_version_marks_previous_as_not_current(): void
    {
        [$admin, $sales, , $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $qualification = $this->qualification($sales, $client, $type, 1);
        $first = $this->calculate($qualification, $sales);
        $second = $this->calculate($qualification, $sales);

        $this->assertFalse($first->refresh()->is_current);
        $this->assertTrue($second->is_current);
        $this->assertSame(2, $second->version);
        $this->assertNotSame($first->number, $second->number);
    }

    public function test_sales_visibility_technical_approval_and_price_protection(): void
    {
        [$admin, $sales, $lead, $client] = $this->actors();
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);

        $this->actingAs($otherSales)->get(route('sales.quotations.show', $quotation))->assertForbidden();
        $this->actingAs($otherSales)->get(route('sales.quotations.index'))->assertDontSee($quotation->number);
        $this->actingAs($lead)->get(route('sales.quotations.show', $quotation))->assertOk();
        $this->assertFalse($lead->can('override', $quotation));

        app(QuotationWorkflowService::class)->transition($quotation, QuotationStatus::InternalReview, $sales);
        app(QuotationWorkflowService::class)->transition($quotation->refresh(), QuotationStatus::InternallyApproved, $lead);
        $this->assertSame(QuotationStatus::InternallyApproved, $quotation->refresh()->status);
    }

    public function test_auditor_and_client_have_no_quotation_access(): void
    {
        foreach ([UserRole::Auditor, UserRole::Client] as $role) {
            $client = $role === UserRole::Client ? Client::create(['name' => 'Klient portalu', 'status' => 'active']) : null;
            $user = User::factory()->create(['role' => $role, 'client_id' => $client?->id, 'active' => true]);
            $this->actingAs($user)->get(route('sales.quotations.index'))->assertForbidden();
        }
    }

    public function test_full_workflow_records_review_approval_send_and_acceptance(): void
    {
        [$admin, $sales, $lead, $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);
        $workflow = app(QuotationWorkflowService::class);

        $workflow->transition($quotation, QuotationStatus::InternalReview, $sales);
        $workflow->transition($quotation->refresh(), QuotationStatus::InternallyApproved, $lead);
        $workflow->transition($quotation->refresh(), QuotationStatus::SentToClient, $sales);
        $workflow->transition($quotation->refresh(), QuotationStatus::Accepted, $sales, [
            'accepted_at' => '2026-08-03', 'accepted_by' => 'Jan Klient',
            'purchase_order_number' => 'PO-123', 'comment' => 'Akceptacja mailowa.',
        ]);

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Accepted, $quotation->status);
        $this->assertSame('Jan Klient', $quotation->accepted_by);
        $this->assertSame('PO-123', $quotation->purchase_order_number);
        $this->assertNotNull($quotation->internally_approved_at);
        $this->assertNotNull($quotation->sent_at);
        $this->assertSame('2026-08-03', $quotation->accepted_at->toDateString());
        foreach (['quotation.sent_for_review', 'quotation.internally_approved', 'quotation.sent_to_client', 'quotation.accepted'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event, 'subject_id' => $quotation->id]);
        }
    }

    public function test_return_reject_cancel_and_invalid_transition_require_business_context(): void
    {
        [$admin, $sales, $lead, $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $workflow = app(QuotationWorkflowService::class);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);
        $workflow->transition($quotation, QuotationStatus::InternalReview, $sales);

        try {
            $workflow->transition($quotation->refresh(), QuotationStatus::Calculated, $lead);
            $this->fail('Cofniecie bez komentarza zostalo przyjete.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $workflow->transition($quotation->refresh(), QuotationStatus::Calculated, $lead, ['comment' => 'Popraw stawke.']);
        $workflow->transition($quotation->refresh(), QuotationStatus::InternalReview, $sales);
        $workflow->transition($quotation->refresh(), QuotationStatus::InternallyApproved, $lead);
        $workflow->transition($quotation->refresh(), QuotationStatus::SentToClient, $sales);

        try {
            $workflow->transition($quotation->refresh(), QuotationStatus::Rejected, $sales);
            $this->fail('Odrzucenie bez powodu zostalo przyjete.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $workflow->transition($quotation->refresh(), QuotationStatus::Rejected, $sales, ['reason' => 'Budzet klienta.']);
        $this->assertSame(QuotationStatus::Rejected, $quotation->refresh()->status);

        $cancelled = $this->calculate($this->qualification($sales, $client, $type, 2), $sales);
        $workflow->transition($cancelled, QuotationStatus::Cancelled, $sales, ['reason' => 'Zakres nieaktualny.']);
        $this->assertSame(QuotationStatus::Cancelled, $cancelled->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation.cancelled', 'subject_id' => $cancelled->id]);
    }

    public function test_wrong_status_transition_and_unauthorized_override_are_blocked(): void
    {
        [$admin, $sales, $lead, $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);

        try {
            app(QuotationWorkflowService::class)->transition($quotation, QuotationStatus::InternallyApproved, $lead);
            $this->fail('Pominieto wymagany etap review.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->expectException(AuthorizationException::class);
        app(QuotationOverrideService::class)->apply($quotation, $lead, ['hourly_rate' => '1.00'], 'Niedozwolona zmiana.');
    }

    public function test_sent_quotation_can_expire_only_after_valid_until(): void
    {
        [$admin, $sales, $lead, $client] = $this->actors();
        [$type, $version] = $this->draftType();
        $version->publish($admin);
        $quotation = $this->calculate($this->qualification($sales, $client, $type, 1), $sales);
        $workflow = app(QuotationWorkflowService::class);
        $workflow->transition($quotation, QuotationStatus::InternalReview, $sales);
        $workflow->transition($quotation->refresh(), QuotationStatus::InternallyApproved, $lead);
        $workflow->transition($quotation->refresh(), QuotationStatus::SentToClient, $sales);

        try {
            $workflow->transition($quotation->refresh(), QuotationStatus::Expired, $sales);
            $this->fail('Wazna wycena zostala oznaczona jako wygasla.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $quotation->update(['valid_until' => now()->subDay()]);
        $workflow->transition($quotation->refresh(), QuotationStatus::Expired, $sales);
        $this->assertSame(QuotationStatus::Expired, $quotation->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'quotation.expired', 'subject_id' => $quotation->id]);
    }

    /** @return array{User, User, User, Client} */
    private function actors(): array
    {
        $admin = User::factory()->create(['role' => UserRole::GlobalAdmin, 'active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);
        $lead = User::factory()->create(['role' => UserRole::TechnicalLead, 'active' => true]);
        $client = Client::create(['name' => 'Klient wyceny', 'status' => 'active']);
        $this->actingAs($admin);

        return [$admin, $sales, $lead, $client];
    }

    /** @return array{AuditType, AuditTypeVersion} */
    private function draftType(array $versionData = []): array
    {
        $type = AuditType::create(['name' => 'Audyt wyceniany', 'code' => 'QUOTE-'.uniqid(), 'active' => true]);
        $version = $type->createDraftVersion([
            'default_hourly_rate' => '100.00', 'minimum_hours' => '0.00', 'minimum_price' => '0.00',
            'reserve_percent' => '0.00', 'default_tax_rate' => '23.00', ...$versionData,
        ]);
        $salesModule = $version->modules()->create([
            'name' => 'Zakres Sales', 'code' => 'SALES', 'module_type' => AuditTypeModule::TYPE_SALES,
            'sort_order' => 10, 'active' => true,
        ]);
        $salesModule->salesQuestions()->create([
            'code' => 'users_count', 'question' => 'Liczba uzytkownikow', 'field_type' => 'number',
            'required' => true, 'affects_pricing' => true, 'active' => true, 'sort_order' => 10,
        ]);
        $version->modules()->create([
            'name' => 'Realizacja', 'code' => 'TECH', 'module_type' => AuditTypeModule::TYPE_TECHNICAL,
            'sort_order' => 20, 'active' => true,
        ]);

        return [$type, $version];
    }

    private function rule(AuditTypeVersion $version, array $data): PricingRule
    {
        return $version->pricingRules()->create([
            'code' => $data['code'], 'name' => $data['code'], 'active' => true, 'rule_type' => 'always',
            'calculation_type' => 'fixed_hours', 'category' => 'base', 'sort_order' => $version->pricingRules()->count() * 10 + 10,
            ...$data,
        ]);
    }

    private function qualification(
        User $sales,
        Client $client,
        AuditType $type,
        int $users,
        SalesQualificationStatus $status = SalesQualificationStatus::ReadyForPricing,
    ): SalesQualification {
        $this->actingAs($sales);
        $qualification = app(SalesQualificationService::class)->create([
            'client_id' => $client->id, 'audit_type_id' => $type->id, 'title' => 'Kwalifikacja wyceny '.uniqid(),
        ], $sales);
        QualificationAnswer::create([
            'sales_qualification_id' => $qualification->id, 'question_code' => 'users_count',
            'question_snapshot' => ['code' => 'users_count', 'field_type' => 'number'],
            'value_json' => ['value' => $users], 'answered_by' => $sales->id, 'answered_at' => now(),
        ]);
        $qualification->update(['status' => $status]);

        return $qualification->refresh();
    }

    private function calculate(SalesQualification $qualification, User $sales): Quotation
    {
        $this->actingAs($sales);

        return app(QuotationCalculationService::class)->createForQualification($qualification, $sales);
    }
}
