<?php

namespace App\Services;

use App\Contracts\EvidenceScanner;
use App\Models\TechnicalAuditEvidence;

class NullEvidenceScanner implements EvidenceScanner
{
    public function scan(TechnicalAuditEvidence $evidence): string
    {
        return 'not_scanned';
    }
}
