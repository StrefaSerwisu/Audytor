<?php

namespace App\Models\Concerns;

use App\Models\AuditTypeVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait BelongsToDraftAuditTypeVersion
{
    protected static function bootBelongsToDraftAuditTypeVersion(): void
    {
        $ensureDraft = function (Model $model): void {
            $version = $model->auditTypeVersionForMutation();

            if (! $version || ! $version->isDraft()) {
                throw ValidationException::withMessages([
                    'audit_type_version_id' => 'Mozna zmieniac tylko elementy wersji roboczej.',
                ]);
            }
        };

        static::creating($ensureDraft);
        static::updating($ensureDraft);
        static::deleting($ensureDraft);
    }

    abstract protected function auditTypeVersionForMutation(): ?AuditTypeVersion;
}
