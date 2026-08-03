<?php

namespace App\Observers;

use App\Models\AuditControlDefinition;
use App\Models\AuditType;
use App\Models\AuditTypeModule;
use App\Models\AuditTypeVersion;
use App\Models\SalesQualificationQuestion;
use App\Support\AuditLogService;
use Illuminate\Database\Eloquent\Model;

class AuditLibraryObserver
{
    public function created(Model $model): void
    {
        $event = match (true) {
            $model instanceof AuditType => 'audit_type.created',
            $model instanceof AuditTypeVersion => 'audit_type_version.created',
            $model instanceof AuditTypeModule => 'audit_type_module.created',
            $model instanceof SalesQualificationQuestion => 'sales_question.created',
            $model instanceof AuditControlDefinition => 'audit_control.created',
            default => null,
        };

        if ($event) {
            AuditLogService::record($event, $model, metadata: $this->metadata($model));
        }
    }

    public function updated(Model $model): void
    {
        if ($model instanceof AuditType) {
            AuditLogService::record('audit_type.updated', $model, metadata: $this->metadata($model));
        }
    }

    /** @return array<string, int|string|null> */
    private function metadata(Model $model): array
    {
        return array_filter([
            'id' => $model->getKey(),
            'audit_type_id' => $model->getAttribute('audit_type_id'),
            'audit_type_version_id' => $model->getAttribute('audit_type_version_id'),
            'audit_type_module_id' => $model->getAttribute('audit_type_module_id'),
            'code' => $model->getAttribute('code'),
            'name' => $model->getAttribute('name') ?? $model->getAttribute('name_snapshot'),
            'version' => $model->getAttribute('version'),
            'module_type' => $model->getAttribute('module_type'),
        ], fn (mixed $value): bool => $value !== null);
    }
}
