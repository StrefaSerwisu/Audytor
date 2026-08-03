<?php

namespace App\Services;

use App\Models\TechnicalAuditAnswer;
use App\Models\TechnicalAuditControl;
use App\Models\User;
use App\Support\AuditLogService;
use Illuminate\Support\Facades\DB;

class TechnicalAuditExecutionService
{
    public function __construct(private readonly TechnicalAuditProgressService $progress) {}

    public function save(TechnicalAuditControl $c, User $u, array $data): TechnicalAuditAnswer
    {
        return DB::transaction(function () use ($c, $u, $data) {
            $complete = (bool) ($data['complete'] ?? false);
            unset($data['complete']);
            $na = (bool) ($data['not_applicable'] ?? false);
            if ($na) {
                $data['result_status'] = 'not_applicable';
            }
            $value = $data['value'] ?? null;
            unset($data['value']);
            $answer = TechnicalAuditAnswer::updateOrCreate(['technical_audit_id' => $c->technical_audit_id, 'technical_audit_control_id' => $c->id], [...$data, 'value_json' => $value === null ? null : ['value' => $value], 'answered_by' => $u->id, 'not_applicable' => $na, 'started_at' => $c->answer?->started_at ?? now(), 'answered_at' => $complete ? now() : null]);
            $status = $na && $complete ? 'not_applicable' : ($complete ? 'completed' : 'in_progress');
            $c->update(['status' => $status]);
            AuditLogService::record('technical_audit.answer_saved', $c->audit, metadata: $c->audit->logMetadata(['control_id' => $c->id, 'completed' => $complete]));
            if ($complete) {
                AuditLogService::record('technical_audit.control_completed', $c->audit, metadata: $c->audit->logMetadata(['control_id' => $c->id]));
            }$this->progress->refresh($c->audit);

            return $answer;
        });
    }

    public function setStatus(TechnicalAuditControl $c, string $status, User $u): void
    {
        $c->update(['status' => $status]);
        AuditLogService::record($status === 'blocked' ? 'technical_audit.control_blocked' : 'technical_audit.control_requires_consultation', $c->audit, metadata: $c->audit->logMetadata(['control_id' => $c->id, 'user_id' => $u->id]));
        $this->progress->refresh($c->audit);
    }
}
