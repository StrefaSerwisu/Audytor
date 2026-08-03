<?php

namespace Tests\Feature;

use App\Enums\SalesQualificationStatus;
use App\Enums\UserRole;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\Client;
use App\Models\QualificationAnswer;
use App\Models\QualificationAttachment;
use App\Models\SalesQualification;
use App\Models\User;
use App\Services\QualificationCompletionService;
use App\Services\QualificationConditionService;
use App\Services\SalesQualificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalesQualificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_login_redirects_to_qualification_workspace(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);

        $this->post(route('auditor.login.store'), [
            'email' => $sales->email,
            'password' => 'password',
        ])->assertRedirect(route('sales.qualifications.index'));
    }

    public function test_sales_creates_qualification_using_current_published_version_and_snapshot(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type, $version] = $this->publishedType($admin);

        $this->actingAs($sales)->post(route('sales.qualifications.store'), [
            'client_id' => $client->id,
            'audit_type_id' => $type->id,
            'title' => 'Kwalifikacja klienta',
        ])->assertRedirect();

        $qualification = SalesQualification::firstOrFail();
        $this->assertSame($sales->id, $qualification->sales_owner_id);
        $this->assertSame($version->id, $qualification->audit_type_version_id);
        $this->assertSame(SalesQualificationStatus::Draft, $qualification->status);
        $this->assertCount(1, $qualification->qualification_snapshot['sales_modules']);
        $this->assertSame('users_count', $qualification->qualification_snapshot['sales_modules'][0]['questions'][0]['code']);
        $this->assertArrayNotHasKey('technical_modules', $qualification->qualification_snapshot);
    }

    public function test_type_without_published_version_blocks_creation(): void
    {
        [, $sales, $client] = $this->actors();
        $type = AuditType::create(['name' => 'Roboczy', 'code' => 'DRAFT-ONLY']);

        $this->actingAs($sales)->post(route('sales.qualifications.store'), [
            'client_id' => $client->id,
            'audit_type_id' => $type->id,
            'title' => 'Nie powstanie',
        ])->assertSessionHasErrors('audit_type_id');

        $this->assertDatabaseCount('sales_qualifications', 0);
    }

    public function test_existing_qualification_keeps_version_and_snapshot_after_new_library_version(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type, $first] = $this->publishedType($admin);
        $qualification = $this->createQualification($sales, $client, $type);
        $snapshot = $qualification->qualification_snapshot;

        $second = $type->createDraftVersion(['name_snapshot' => 'Nowa wersja']);
        $second->modules()->create([
            'name' => 'Sales v2', 'code' => 'SALES-V2', 'module_type' => AuditTypeModule::TYPE_SALES, 'active' => true,
        ])->salesQuestions()->create([
            'code' => 'new_question', 'question' => 'Nowe pytanie', 'field_type' => 'text', 'required' => true, 'active' => true,
        ]);
        $second->modules()->create([
            'name' => 'Technical v2', 'code' => 'TECH-V2', 'module_type' => AuditTypeModule::TYPE_TECHNICAL, 'active' => true,
        ]);
        $second->publish($admin);

        $this->assertSame($first->id, $qualification->refresh()->audit_type_version_id);
        $this->assertSame($snapshot, $qualification->qualification_snapshot);
        $this->assertSame($second->id, $type->refresh()->current_version_id);
    }

    public function test_sales_sees_only_own_qualifications_and_administrator_sees_all(): void
    {
        [$admin, $sales, $client] = $this->actors();
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);
        [$type] = $this->publishedType($admin);
        $own = $this->createQualification($sales, $client, $type, 'Wlasna kwalifikacja');
        $other = $this->createQualification($otherSales, $client, $type, 'Cudza kwalifikacja');

        $this->actingAs($sales)->get(route('sales.qualifications.index'))
            ->assertOk()->assertSee($own->title)->assertDontSee($other->title);
        $this->actingAs($sales)->get(route('sales.qualifications.show', $other))->assertForbidden();
        $this->actingAs($admin)->get(route('sales.qualifications.index'))
            ->assertOk()->assertSee($own->title)->assertSee($other->title);
    }

    public function test_technical_lead_can_view_but_cannot_change_sales_answers(): void
    {
        [$admin, $sales, $client] = $this->actors();
        $lead = User::factory()->create(['role' => UserRole::TechnicalLead, 'active' => true]);
        [$type] = $this->publishedType($admin);
        $qualification = $this->createQualification($sales, $client, $type);

        $this->actingAs($lead)->get(route('sales.qualifications.show', $qualification))->assertOk();
        $this->post(route('sales.qualifications.answers.update', [$qualification, 'users_count']), ['value' => 25])
            ->assertForbidden();
        $this->assertDatabaseCount('qualification_answers', 0);
    }

    public function test_auditor_and_client_have_no_access(): void
    {
        foreach ([UserRole::Auditor, UserRole::Client] as $role) {
            $attributes = ['role' => $role, 'active' => true];

            if ($role === UserRole::Client) {
                $attributes['client_id'] = Client::create(['name' => 'Portal klienta'])->id;
            }

            $user = User::factory()->create($attributes);
            $this->actingAs($user)->get('/sales/qualifications')->assertForbidden();
            $this->flushSession();
        }
    }

    public function test_each_non_file_answer_type_is_saved_as_typed_json_and_info_is_rejected(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type] = $this->publishedType($admin, $this->allQuestionTypes());
        $qualification = $this->createQualification($sales, $client, $type);
        $this->actingAs($sales);

        $values = [
            'text_q' => ['value' => 'Tekst'],
            'textarea_q' => ['value' => 'Dluzsza odpowiedz'],
            'number_q' => ['value' => '0'],
            'boolean_q' => ['value' => 'false'],
            'select_q' => ['value' => 'm365'],
            'multiselect_q' => ['value' => ['wifi', 'lan']],
            'date_q' => ['value' => '2026-09-15'],
        ];

        foreach ($values as $code => $payload) {
            $this->post(route('sales.qualifications.answers.update', [$qualification, $code]), $payload)->assertRedirect();
        }

        $answers = QualificationAnswer::where('sales_qualification_id', $qualification->id)->get()->keyBy('question_code');
        $this->assertSame('Tekst', $answers['text_q']->value_json['value']);
        $this->assertIsNumeric($answers['number_q']->value_json['value']);
        $this->assertEquals(0, $answers['number_q']->value_json['value']);
        $this->assertFalse($answers['boolean_q']->value_json['value']);
        $this->assertSame(['wifi', 'lan'], $answers['multiselect_q']->value_json['value']);
        $this->assertSame('2026-09-15', $answers['date_q']->value_json['value']);
        $this->post(route('sales.qualifications.answers.update', [$qualification, 'info_q']), ['value' => 'forged'])
            ->assertSessionHasErrors('value');
    }

    public function test_boolean_unknown_is_saved_and_counts_as_complete(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type] = $this->publishedType($admin, [[
            'code' => 'unknown_q', 'question' => 'Czy wiadomo?', 'field_type' => 'boolean', 'required' => true, 'active' => true,
        ]]);
        $qualification = $this->createQualification($sales, $client, $type);

        $this->actingAs($sales)->post(route('sales.qualifications.answers.update', [$qualification, 'unknown_q']), [
            'value' => 'unknown',
        ])->assertRedirect();

        $answer = QualificationAnswer::firstOrFail();
        $this->assertArrayHasKey('value', $answer->value_json);
        $this->assertNull($answer->value_json['value']);
        $this->assertSame(100, app(QualificationCompletionService::class)->calculate($qualification)['percent']);
    }

    public function test_condition_service_supports_all_required_operators(): void
    {
        $service = app(QualificationConditionService::class);
        $values = ['bool' => true, 'count' => 10, 'tags' => ['m365', 'wifi'], 'text' => 'Microsoft 365', 'empty' => ''];

        $this->assertTrue($service->matches(['question_code' => 'bool', 'operator' => 'equals', 'value' => true], $values));
        $this->assertTrue($service->matches(['question_code' => 'bool', 'operator' => 'not_equals', 'value' => false], $values));
        $this->assertTrue($service->matches(['question_code' => 'count', 'operator' => 'greater_than', 'value' => 5], $values));
        $this->assertTrue($service->matches(['question_code' => 'count', 'operator' => 'less_than', 'value' => 20], $values));
        $this->assertTrue($service->matches(['question_code' => 'tags', 'operator' => 'contains', 'value' => 'wifi'], $values));
        $this->assertTrue($service->matches(['question_code' => 'empty', 'operator' => 'is_empty'], $values));
        $this->assertTrue($service->matches(['question_code' => 'text', 'operator' => 'is_not_empty'], $values));
    }

    public function test_hidden_required_question_does_not_block_completion_but_visible_missing_question_does(): void
    {
        [$admin, $sales, $client] = $this->actors();
        $questions = [
            ['code' => 'uses_m365', 'question' => 'Czy uzywa M365?', 'field_type' => 'boolean', 'required' => true, 'affects_scope' => true, 'active' => true],
            ['code' => 'tenant_count', 'question' => 'Liczba tenantow', 'field_type' => 'number', 'required' => true, 'active' => true,
                'conditional_logic' => ['question_code' => 'uses_m365', 'operator' => 'equals', 'value' => true]],
        ];
        [$type] = $this->publishedType($admin, $questions);
        $qualification = $this->createQualification($sales, $client, $type);
        $this->actingAs($sales)->post(route('sales.qualifications.start', $qualification))->assertRedirect();
        $this->post(route('sales.qualifications.complete', $qualification))->assertSessionHasErrors('answers');

        $this->post(route('sales.qualifications.answers.update', [$qualification, 'uses_m365']), ['value' => 'false'])->assertRedirect();
        $progress = app(QualificationCompletionService::class)->calculate($qualification->refresh());
        $this->assertSame(1, $progress['required']);
        $this->assertSame(100, $progress['percent']);
        $this->post(route('sales.qualifications.complete', $qualification))->assertRedirect();

        $this->assertSame(SalesQualificationStatus::ReadyForPricing, $qualification->refresh()->status);
        $this->assertNotNull($qualification->completed_at);
        $this->assertStringContainsString($client->name, $qualification->scope_summary);
        $this->assertStringContainsString('Nie', $qualification->scope_summary);
    }

    public function test_cancellation_requires_reason_and_is_audited(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type] = $this->publishedType($admin);
        $qualification = $this->createQualification($sales, $client, $type);
        $this->actingAs($sales);

        $this->post(route('sales.qualifications.cancel', $qualification))->assertSessionHasErrors('reason');
        $this->post(route('sales.qualifications.cancel', $qualification), ['reason' => 'Klient zrezygnowal.'])->assertRedirect();

        $this->assertSame(SalesQualificationStatus::Cancelled, $qualification->refresh()->status);
        $this->assertStringContainsString('Klient zrezygnowal', $qualification->internal_notes);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sales_qualification.cancelled', 'subject_id' => $qualification->id]);
    }

    public function test_private_file_upload_download_delete_and_idor_protection(): void
    {
        Storage::fake('local');
        [$admin, $sales, $client] = $this->actors();
        $otherSales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);
        [$type] = $this->publishedType($admin, [[
            'code' => 'scope_file', 'question' => 'Plik zakresu', 'field_type' => 'file', 'required' => true, 'active' => true,
        ]]);
        $qualification = $this->createQualification($sales, $client, $type);

        $this->actingAs($sales)->post(route('sales.qualifications.answers.update', [$qualification, 'scope_file']), [
            'file' => UploadedFile::fake()->create('scope.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $attachment = QualificationAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->actingAs($otherSales)->get(route('sales.qualifications.attachments.download', $attachment))->assertForbidden();
        $this->actingAs($sales)->get(route('sales.qualifications.attachments.download', $attachment))->assertDownload('scope.pdf');
        $this->delete(route('sales.qualifications.attachments.destroy', $attachment))->assertRedirect();
        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sales_qualification.file_uploaded']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'sales_qualification.file_deleted']);
    }

    public function test_key_workflow_actions_are_written_to_audit_log(): void
    {
        [$admin, $sales, $client] = $this->actors();
        [$type] = $this->publishedType($admin, [[
            'code' => 'required_q', 'question' => 'Zakres', 'field_type' => 'text', 'required' => true, 'active' => true,
        ]]);
        $qualification = $this->createQualification($sales, $client, $type);
        $this->actingAs($sales);
        $this->post(route('sales.qualifications.start', $qualification));
        $this->post(route('sales.qualifications.wait', $qualification));
        $this->post(route('sales.qualifications.resume', $qualification));
        $this->post(route('sales.qualifications.answers.update', [$qualification, 'required_q']), ['value' => 'Zakres klienta']);
        $this->post(route('sales.qualifications.complete', $qualification));

        foreach ([
            'sales_qualification.created', 'sales_qualification.started', 'sales_qualification.waiting_for_client',
            'sales_qualification.resumed', 'sales_qualification.answer_updated', 'sales_qualification.completed',
            'sales_qualification.ready_for_pricing',
        ] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event]);
        }
    }

    /** @return array{User, User, Client} */
    private function actors(): array
    {
        $admin = User::factory()->create(['role' => UserRole::GlobalAdmin, 'active' => true]);
        $sales = User::factory()->create(['role' => UserRole::Sales, 'active' => true]);
        $client = Client::create(['name' => 'Klient 2B', 'status' => 'active']);
        $this->actingAs($admin);

        return [$admin, $sales, $client];
    }

    /** @return array{AuditType, AuditTypeVersion} */
    private function publishedType(User $admin, ?array $questions = null): array
    {
        $this->actingAs($admin);
        $type = AuditType::create(['name' => 'Audyt kwalifikowany', 'code' => 'QUAL-'.uniqid(), 'active' => true]);
        $version = $type->createDraftVersion(['sales_instructions' => 'Zbierz dane klienta.']);
        $salesModule = $version->modules()->create([
            'name' => 'Zakres Sales', 'code' => 'SALES', 'module_type' => AuditTypeModule::TYPE_SALES, 'sort_order' => 10, 'active' => true,
        ]);
        $version->modules()->create([
            'name' => 'Realizacja', 'code' => 'TECH', 'module_type' => AuditTypeModule::TYPE_TECHNICAL, 'sort_order' => 20, 'active' => true,
        ]);

        foreach ($questions ?? [[
            'code' => 'users_count', 'question' => 'Liczba uzytkownikow', 'field_type' => 'number',
            'required' => true, 'affects_scope' => true, 'affects_pricing' => true, 'active' => true,
        ]] as $index => $question) {
            $salesModule->salesQuestions()->create([...$question, 'sort_order' => ($index + 1) * 10]);
        }

        $version->publish($admin);

        return [$type, $version];
    }

    private function createQualification(User $sales, Client $client, AuditType $type, string $title = 'Kwalifikacja'): SalesQualification
    {
        $this->actingAs($sales);

        return app(SalesQualificationService::class)->create([
            'client_id' => $client->id,
            'audit_type_id' => $type->id,
            'title' => $title,
        ], $sales);
    }

    private function allQuestionTypes(): array
    {
        return [
            ['code' => 'text_q', 'question' => 'Tekst', 'field_type' => 'text', 'required' => true, 'active' => true],
            ['code' => 'textarea_q', 'question' => 'Opis', 'field_type' => 'textarea', 'required' => true, 'active' => true],
            ['code' => 'number_q', 'question' => 'Liczba', 'field_type' => 'number', 'required' => true, 'active' => true],
            ['code' => 'boolean_q', 'question' => 'Tak czy nie', 'field_type' => 'boolean', 'required' => true, 'active' => true],
            ['code' => 'select_q', 'question' => 'System', 'field_type' => 'select', 'options_json' => ['m365' => 'Microsoft 365', 'google' => 'Google'], 'required' => true, 'active' => true],
            ['code' => 'multiselect_q', 'question' => 'Sieci', 'field_type' => 'multiselect', 'options_json' => ['wifi' => 'Wi-Fi', 'lan' => 'LAN'], 'required' => true, 'active' => true],
            ['code' => 'date_q', 'question' => 'Data', 'field_type' => 'date', 'required' => true, 'active' => true],
            ['code' => 'file_q', 'question' => 'Plik', 'field_type' => 'file', 'required' => false, 'active' => true],
            ['code' => 'info_q', 'question' => 'Informacja', 'description' => 'Tresci informacyjne', 'field_type' => 'info', 'required' => true, 'active' => true],
        ];
    }
}
