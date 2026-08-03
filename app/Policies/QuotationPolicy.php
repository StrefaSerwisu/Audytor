<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Quotation;
use App\Models\SalesQualification;
use App\Models\User;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->hasAnyRole(UserRole::Sales, UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->viewAny($user) && (! $user->hasRole(UserRole::Sales) || $quotation->sales_owner_id === $user->id);
    }

    public function createQuotation(User $user, SalesQualification $qualification): bool
    {
        return $user->active && ($user->hasAnyRole(UserRole::GlobalAdmin, UserRole::SuperAdmin)
            || ($user->hasRole(UserRole::Sales) && $qualification->sales_owner_id === $user->id));
    }

    public function override(User $user, Quotation $quotation): bool
    {
        return $quotation->canBeOverridden() && $this->view($user, $quotation)
            && $user->hasAnyRole(UserRole::Sales, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function sendForReview(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation) && $user->hasAnyRole(UserRole::Sales, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function approveInternally(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation) && $user->hasAnyRole(UserRole::TechnicalLead, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function returnForChanges(User $user, Quotation $quotation): bool
    {
        return $this->approveInternally($user, $quotation);
    }

    public function sendToClient(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation) && $user->hasAnyRole(UserRole::Sales, UserRole::GlobalAdmin, UserRole::SuperAdmin);
    }

    public function recordClientDecision(User $user, Quotation $quotation): bool
    {
        return $this->sendToClient($user, $quotation);
    }

    public function expire(User $user, Quotation $quotation): bool
    {
        return $this->recordClientDecision($user, $quotation);
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        return $this->sendToClient($user, $quotation);
    }

    public function createAuditOrder(User $user, Quotation $quotation): bool
    {
        return $this->view($user, $quotation)
            && $user->hasAnyRole(UserRole::Sales, UserRole::GlobalAdmin, UserRole::SuperAdmin)
            && $quotation->status === QuotationStatus::Accepted
            && $quotation->is_current;
    }
}
