<?php

namespace App\Http\Controllers;

use App\Models\AuditAnswer;
use App\Models\AuditPublication;
use Illuminate\View\View;

class ClientReportController extends Controller
{
    public function show(string $token): View
    {
        $publication = AuditPublication::query()
            ->where('token', $token)
            ->whereNotNull('published_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->with([
                'publisher',
                'audit.client',
                'audit.location',
                'audit.template',
                'audit.leadReviewer',
                'audit.answers',
            ])
            ->firstOrFail();

        return view('client-reports.show', [
            'publication' => $publication,
            'audit' => $publication->audit,
            'riskLevels' => AuditAnswer::RISK_LEVELS,
            'riskSummary' => $this->riskSummary($publication->audit),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function riskSummary($audit): array
    {
        $summary = array_fill_keys(array_keys(AuditAnswer::RISK_LEVELS), 0);

        foreach ($audit->answers as $answer) {
            if ($answer->risk_level && array_key_exists($answer->risk_level, $summary)) {
                $summary[$answer->risk_level]++;
            }
        }

        return $summary;
    }
}
