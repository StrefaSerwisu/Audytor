<?php

namespace App\Services;

use App\Models\TechnicalAudit;
use App\Models\TechnicalAuditControl;

class TechnicalAuditProgressService
{
    public function refresh(TechnicalAudit $audit): TechnicalAudit
    {
        $audit->load(['controls.answer', 'controls.evidence', 'modules.controls.answer', 'modules.controls.evidence', 'escalations']);
        $active = $audit->controls->where('active', true);
        $complete = $active->filter(fn ($c) => $this->isComplete($c));
        $blocked = $active->where('status', 'blocked');
        $escalated = $active->whereIn('status', ['requires_consultation', 'blocked']);
        $total = $active->count();
        $audit->update(['total_controls' => $total, 'completed_controls' => $complete->count(), 'blocked_controls' => $blocked->count(), 'escalated_controls' => $escalated->count(), 'progress_percent' => $total ? (int) floor($complete->count() * 100 / $total) : 100]);
        foreach ($audit->modules as $m) {
            $controls = $m->controls->where('active', true);
            $done = $controls->filter(fn ($c) => $this->isComplete($c))->count();
            $pct = $controls->count() ? (int) floor($done * 100 / $controls->count()) : 100;
            $status = $pct === 100 ? 'completed' : ($controls->contains(fn ($c) => $c->status === 'blocked') ? 'blocked' : ($pct > 0 ? 'in_progress' : 'not_started'));
            $m->update(['progress_percent' => $pct, 'status' => $status, 'completed_at' => $pct === 100 ? now() : null]);
        }

return $audit->refresh();
    }

    public function isComplete(TechnicalAuditControl $c): bool
    {
        if (! $c->active) {
            return true;
        }$a = $c->answer;
        if (! $a) {
            return false;
        }if ($a->not_applicable) {
            return $c->allow_not_applicable && (! $c->require_comment_when_na || filled($a->not_applicable_reason));
        }

return $c->status === 'completed' && (! $c->require_evidence || $c->evidence->isNotEmpty());
    }

    public function blockers(TechnicalAudit $audit): array
    {
        $this->refresh($audit);
        $items = [];
        if ($audit->controls()->where('active', true)->whereIn('status', ['blocked', 'requires_consultation'])->exists()) {
            $items[] = 'Istnieja zablokowane kontrole lub kontrole wymagajace konsultacji.';
        }if ($audit->escalations()->whereNotIn('status', ['resolved', 'cancelled'])->where('priority', 'critical')->exists()) {
            $items[] = 'Istnieje otwarta eskalacja krytyczna.';
        }foreach ($audit->controls()->where('active', true)->with(['answer', 'evidence'])->get() as $c) {
            if (! $this->isComplete($c)) {
                $items[] = "Kontrola {$c->code} nie jest kompletna.";
            }
        }

return $items;
    }
}
