<?php

namespace App\Services;

use App\Enums\AuditOrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\AuditOrder;
use App\Models\QualificationAttachment;
use App\Models\Quotation;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\AuditOrderNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuditOrderCreationService
{
    private const CHECKLIST = [
        ['scheduling', 'client_date_confirmed', 'Potwierdzenie terminu z klientem'],
        ['client', 'contact_confirmed', 'Potwierdzenie osoby kontaktowej'],
        ['access', 'administrative_access', 'Dostep administracyjny'],
        ['access', 'remote_access', 'Dostep zdalny lub VPN'],
        ['client', 'evidence_consent', 'Zgody na wykonanie zdjec i screenshotow'],
        ['documentation', 'systems_inventory', 'Lista urzadzen i systemow w zakresie'],
        ['documentation', 'client_documentation', 'Dokumentacja klienta'],
        ['tools', 'required_tools', 'Wymagane narzedzia'],
        ['internal', 'engineers_assigned', 'Przypisanie inzynierow'],
        ['internal', 'technical_lead_assigned', 'Przypisanie lidera technicznego'],
        ['scheduling', 'opening_meeting', 'Spotkanie otwierajace'],
        ['internal', 'evidence_storage_rules', 'Zasady bezpiecznego przechowywania dowodow'],
    ];

    public function create(Quotation $quotation, User $actor): AuditOrder
    {
        if (! $actor->can('createAuditOrder', $quotation)) {
            throw new AuthorizationException('Brak uprawnien do utworzenia zlecenia audytu.');
        }
        if ($quotation->status !== QuotationStatus::Accepted || ! $quotation->is_current) {
            throw ValidationException::withMessages(['quotation' => 'Zlecenie mozna utworzyc tylko z aktualnej, zaakceptowanej wyceny.']);
        }

        return DB::transaction(function () use ($quotation, $actor): AuditOrder {
            $quotation = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);
            if ($quotation->auditOrder()->exists()) {
                throw ValidationException::withMessages(['quotation' => 'Dla tej wyceny zlecenie audytu juz istnieje.']);
            }
            $quotation->load(['qualification.answers', 'qualification.attachments', 'lines', 'overrides', 'versionDefinition']);
            $qualification = $quotation->qualification;
            $order = AuditOrder::create([
                'number' => $this->nextNumber(), 'quotation_id' => $quotation->id,
                'sales_qualification_id' => $qualification->id, 'client_id' => $quotation->client_id,
                'client_location_id' => $qualification->client_location_id, 'audit_type_id' => $quotation->audit_type_id,
                'audit_type_version_id' => $quotation->audit_type_version_id, 'title' => $qualification->title,
                'status' => AuditOrderStatus::AwaitingPlanning, 'sales_owner_id' => $quotation->sales_owner_id,
                'expected_hours' => $quotation->total_hours, 'engineers_count' => $quotation->engineers_count,
                'minimum_competency_level' => $quotation->versionDefinition->minimum_competency_level,
                'purpose' => $qualification->purpose, 'scope_summary' => $qualification->scope_summary,
                'assumptions' => $quotation->assumptions, 'exclusions' => $quotation->exclusions,
                'delivery_instructions' => $quotation->versionDefinition->delivery_instructions,
                'client_contact_name' => $qualification->contact_name, 'client_contact_email' => $qualification->contact_email,
                'client_contact_phone' => $qualification->contact_phone,
                'configuration_snapshot' => $quotation->versionDefinition->snapshot(),
                'source_snapshot' => $this->sourceSnapshot($quotation), 'created_by' => $actor->id,
            ]);
            foreach (self::CHECKLIST as $sort => [$category, $code, $title]) {
                $order->preparationItems()->create([...compact('category', 'code', 'title'), 'sort_order' => $sort + 1, 'source' => 'default']);
            }
            foreach ($qualification->attachments as $attachment) {
                $order->documents()->create($this->documentReference($attachment));
            }
            AuditLogService::record('audit_order.created', $order, newValues: ['number' => $order->number, 'status' => $order->status], metadata: $order->logMetadata());
            AuditLogService::record('audit_order.snapshot_created', $order, metadata: $order->logMetadata(['audit_type_version_id' => $quotation->audit_type_version_id]));
            foreach (User::where('role', UserRole::TechnicalLead)->where('active', true)->get() as $lead) {
                AuditOrderNotifier::notify($lead, $order, 'audit_order.created', 'Nowe zlecenie oczekuje na planowanie', $order->number);
            }

            return $order->load(['preparationItems', 'documents']);
        });
    }

    private function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        DB::table('audit_order_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $sequence = DB::table('audit_order_sequences')->where('year', $year)->lockForUpdate()->first();
        $number = ((int) $sequence->last_number) + 1;
        DB::table('audit_order_sequences')->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);

        return sprintf('AUD-ZL/%d/%04d', $year, $number);
    }

    /** @return array<string, mixed> */
    private function sourceSnapshot(Quotation $quotation): array
    {
        return [
            'qualification' => $quotation->qualification->only(['id', 'title', 'purpose', 'expected_date', 'contact_name', 'contact_email', 'contact_phone', 'scope_summary', 'qualification_snapshot']),
            'answers' => $quotation->qualification->answers->map->only(['question_code', 'question_snapshot', 'value_json', 'answered_at'])->values()->all(),
            'quotation' => $quotation->only(['id', 'number', 'version', 'status', 'currency', 'total_hours', 'engineers_count', 'net_price', 'gross_price', 'assumptions', 'exclusions', 'accepted_at', 'accepted_by', 'purchase_order_number', 'acceptance_comment', 'calculation_snapshot', 'final_calculation_snapshot']),
            'lines' => $quotation->lines->map->toArray()->values()->all(),
            'overrides' => $quotation->overrides->map->only(['field', 'old_value', 'new_value', 'reason', 'created_at'])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function documentReference(QualificationAttachment $attachment): array
    {
        return ['category' => 'qualification', 'source_type' => QualificationAttachment::class, 'source_id' => $attachment->id,
            'uploaded_by' => $attachment->uploaded_by, 'disk' => $attachment->disk, 'path' => $attachment->path,
            'original_name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes];
    }
}
