<?php

namespace App\Support;

use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\AuditQuestion;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

class AuditReportData
{
    /**
     * @return array<int, string>
     */
    public static function lines(Audit $audit, string $type): array
    {
        $audit->loadMissing(['client', 'location', 'template', 'leadReviewer', 'answers', 'selectedModules.module.questions.recommendations']);

        $lines = [
            'Global IT / Audytor IT',
            'Typ raportu: '.self::label($type),
            'Audyt: '.$audit->title,
            'Klient: '.$audit->client->name,
            'Lokalizacja: '.$audit->location->name,
            'Szablon: '.$audit->template->name,
            'Lider techniczny: '.($audit->leadReviewer?->name ?? '-'),
            'Status: '.(Audit::STATUSES[$audit->status] ?? $audit->status),
            'Podsumowanie ryzyka: '.self::riskText($audit),
            'Sekcje: strona tytulowa, podsumowanie, ryzyka, rekomendacje, plan dzialan.',
        ];

        if ($type === 'sales') {
            foreach (self::salesOpportunities($audit) as $item) {
                $lines[] = 'Szansa sprzedazowa: '.$item['title'].' / '.$item['sales_category'].' / '.$item['recommendation_text'];
            }
        } else {
            foreach (self::recommendations($audit) as $item) {
                $lines[] = 'Rekomendacja: '.$item['title'].' / '.$item['recommendation_text'];
            }
        }

        return $lines;
    }

    public static function label(string $type): string
    {
        return match ($type) {
            'technical' => 'Raport techniczny',
            'business' => 'Raport biznesowy',
            'sales' => 'Raport sprzedazowy',
            default => 'Raport',
        };
    }

    private static function riskText(Audit $audit): string
    {
        $summary = array_fill_keys(array_keys(AuditAnswer::RISK_LEVELS), 0);

        foreach ($audit->answers as $answer) {
            if ($answer->risk_level && array_key_exists($answer->risk_level, $summary)) {
                $summary[$answer->risk_level]++;
            }
        }

        return collect($summary)
            ->map(fn ($count, $risk) => (AuditAnswer::RISK_LEVELS[$risk] ?? $risk).': '.$count)
            ->implode(', ');
    }

    private static function recommendations(Audit $audit): Collection
    {
        $answers = $audit->answers->keyBy('audit_question_id');

        return $audit->selectedModules
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions ?? collect())
            ->flatMap(function (AuditQuestion $question) use ($answers): Collection {
                $answer = $answers->get($question->id);
                $items = collect();

                if ($answer?->recommendation_text) {
                    $items->push([
                        'title' => 'Rekomendacja audytora',
                        'recommendation_text' => $answer->recommendation_text,
                    ]);
                }

                foreach ($question->recommendations as $recommendation) {
                    $items->push([
                        'title' => $recommendation->title,
                        'recommendation_text' => $recommendation->recommendation_text,
                    ]);
                }

                return $items;
            });
    }

    private static function salesOpportunities(Audit $audit): Collection
    {
        return $audit->selectedModules
            ->flatMap(fn ($selectedModule) => $selectedModule->module?->questions ?? collect())
            ->flatMap(fn (AuditQuestion $question) => $question->recommendations
                ->filter(fn (Recommendation $recommendation) => $recommendation->global_it_can_do)
                ->map(fn (Recommendation $recommendation) => [
                    'title' => $recommendation->title,
                    'sales_category' => $recommendation->sales_category ?: 'Bez kategorii',
                    'recommendation_text' => $recommendation->recommendation_text,
                ]));
    }
}
