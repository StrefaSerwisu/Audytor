<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditReportExport;
use App\Models\User;

class AuditReportExportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(
            UserRole::SuperAdmin,
            UserRole::GlobalAdmin,
            UserRole::TechnicalLead,
        );
    }

    public function view(User $user, AuditReportExport $export): bool
    {
        $export->loadMissing('audit');

        if (! $user->active || $export->audit === null) {
            return false;
        }

        return $user->can('generateTechnicalReport', $export->audit)
            || $user->can('generateBusinessReport', $export->audit);
    }

    public function download(User $user, AuditReportExport $export): bool
    {
        return $this->view($user, $export);
    }

    public function retry(User $user, AuditReportExport $export): bool
    {
        return $this->view($user, $export);
    }
}
