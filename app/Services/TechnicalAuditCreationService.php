<?php

namespace App\Services;

use App\Enums\AuditOrderStatus;
use App\Enums\TechnicalAuditStatus;
use App\Models\AuditOrder;
use App\Models\TechnicalAudit;
use App\Models\User;
use App\Support\AuditLogService;
use App\Support\TechnicalAuditNotifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicalAuditCreationService
{
    public function create(AuditOrder $order, User $actor): TechnicalAudit
    {
        if (! $actor->can('transition', $order)) {
            throw new AuthorizationException('Brak uprawnien do rozpoczecia audytu technicznego.');
        }
        if ($order->status !== AuditOrderStatus::Scheduled) {
            throw ValidationException::withMessages(['status' => 'Audyt techniczny mozna rozpoczac tylko z zaplanowanego zlecenia.']);
        }

        return DB::transaction(function () use ($order, $actor) {
            $order = AuditOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->technicalAudit()->exists()) {
                throw ValidationException::withMessages(['audit_order' => 'Audyt techniczny juz istnieje.']);
            }
            $modules = collect($order->configuration_snapshot['technical_modules'] ?? [])->filter(fn ($m) => ($m['active'] ?? true));
            $controls = $modules->flatMap(fn ($m) => $m['controls'] ?? [])->filter(fn ($c) => ($c['active'] ?? true));
            if ($modules->isEmpty() || $controls->isEmpty()) {
                throw ValidationException::withMessages(['configuration_snapshot' => 'Snapshot nie zawiera aktywnych modulow i kontroli technicznych.']);
            } if (! $order->technical_lead_id) {
                throw ValidationException::withMessages(['technical_lead' => 'Brak lidera technicznego.']);
            }
            $engineers = $order->assignees()->whereIn('assignment_role', ['auditor', 'supporting_engineer'])->pluck('user_id')->values();
            if ($engineers->isEmpty()) {
                throw ValidationException::withMessages(['assignees' => 'Brak przypisanego inzyniera.']);
            }
            $audit = TechnicalAudit::create(['number' => $this->nextNumber(), 'audit_order_id' => $order->id, 'client_id' => $order->client_id, 'client_location_id' => $order->client_location_id, 'audit_type_id' => $order->audit_type_id, 'audit_type_version_id' => $order->audit_type_version_id, 'title' => $order->title, 'status' => TechnicalAuditStatus::InProgress, 'technical_lead_id' => $order->technical_lead_id, 'delivery_owner_id' => $order->delivery_owner_id, 'started_at' => now(), 'started_by' => $actor->id, 'configuration_snapshot' => $order->configuration_snapshot, 'source_snapshot' => $order->source_snapshot, 'total_controls' => $controls->count()]);
            $controlIndex = 0;
            foreach ($modules->sortBy('sort_order') as $moduleData) {
                $module = $audit->modules()->create(['source_module_id' => $moduleData['id'] ?? null, 'code' => $moduleData['code'], 'name' => $moduleData['name'], 'description' => $moduleData['description'] ?? null, 'instructions' => $order->delivery_instructions, 'sort_order' => $moduleData['sort_order'] ?? 0, 'estimated_minutes' => $moduleData['estimated_minutes'] ?? 0]);
                foreach (collect($moduleData['controls'] ?? [])->filter(fn ($c) => ($c['active'] ?? true))->sortBy('sort_order') as $data) {
                    $audit->controls()->create([...collect($data)->only(['code', 'name', 'objective', 'description', 'execution_instructions', 'where_to_check', 'required_access', 'required_tools', 'minimum_competency_level', 'estimated_minutes', 'field_type', 'options_json', 'required', 'allow_not_applicable', 'require_comment_when_na', 'require_evidence', 'evidence_types', 'positive_criteria', 'negative_criteria', 'escalation_criteria', 'default_risk_level', 'default_recommendation', 'standard_reference', 'sort_order', 'active'])->all(), 'technical_audit_module_id' => $module->id, 'source_control_id' => $data['id'] ?? null, 'assigned_to' => $engineers[$controlIndex++ % $engineers->count()]]);
                }
            }
            $order->update(['status' => AuditOrderStatus::InProgress]);
            AuditLogService::record('technical_audit.created', $audit, metadata: $audit->logMetadata());
            AuditLogService::record('technical_audit.started', $audit, metadata: $audit->logMetadata(['started_by' => $actor->id]));
            TechnicalAuditNotifier::assignees($audit, 'technical_audit.started', 'Audyt techniczny zostal rozpoczęty');

            return $audit->load('modules.controls');
        });
    }

    private function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        DB::table('technical_audit_sequences')->insertOrIgnore(['year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('technical_audit_sequences')->where('year', $year)->lockForUpdate()->first();
        $n = ((int) $row->last_number) + 1;
        DB::table('technical_audit_sequences')->where('year', $year)->update(['last_number' => $n, 'updated_at' => now()]);

        return sprintf('AUD-TECH/%d/%04d', $year, $n);
    }
}
