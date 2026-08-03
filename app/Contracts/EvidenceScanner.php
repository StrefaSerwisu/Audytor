<?php

namespace App\Contracts;

use App\Models\TechnicalAuditEvidence;

interface EvidenceScanner
{
    public function scan(TechnicalAuditEvidence $evidence): string;
}
