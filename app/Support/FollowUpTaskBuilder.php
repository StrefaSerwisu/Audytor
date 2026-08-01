<?php

namespace App\Support;

use App\Models\AuditAnswer;
use App\Models\AuditFollowUpTask;
use App\Models\AuditPublication;
use App\Models\Recommendation;
use Illuminate\Support\Collection;

class FollowUpTaskBuilder
{
    public static function syncFromPublication(AuditPublication $publication): void
    {
        $publication->loadMissing('audit');

        $acceptedKeys = collect($publication->accepted_recommendations_json ?? []);
        $items = self::itemsForKeys($publication, $acceptedKeys);

        $publication->followUpTasks()
            ->whereNotIn('source_key', $acceptedKeys->all())
            ->delete();

        foreach ($items as $item) {
            AuditFollowUpTask::updateOrCreate(
                [
                    'audit_publication_id' => $publication->id,
                    'source_key' => $item['source_key'],
                ],
                [
                    'audit_id' => $publication->audit_id,
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'priority' => $item['priority'],
                    'status' => 'new',
                    'client_visible' => true,
                ],
            );
        }
    }

    /**
     * @param  Collection<int, string>  $keys
     * @return Collection<int, array{source_key:string, title:string, description:?string, priority:?string}>
     */
    private static function itemsForKeys(AuditPublication $publication, Collection $keys): Collection
    {
        $answerIds = $keys
            ->filter(fn (string $key) => str_starts_with($key, 'answer:'))
            ->map(fn (string $key) => (int) str_replace('answer:', '', $key));
        $recommendationIds = $keys
            ->filter(fn (string $key) => str_starts_with($key, 'recommendation:'))
            ->map(fn (string $key) => (int) str_replace('recommendation:', '', $key));

        $answers = AuditAnswer::query()
            ->whereIn('id', $answerIds)
            ->where('audit_id', $publication->audit_id)
            ->get()
            ->keyBy('id');

        $recommendations = Recommendation::query()
            ->whereIn('id', $recommendationIds)
            ->get()
            ->keyBy('id');

        return $keys
            ->map(function (string $key) use ($answers, $recommendations): ?array {
                if (str_starts_with($key, 'answer:')) {
                    $answer = $answers->get((int) str_replace('answer:', '', $key));

                    if (! $answer?->recommendation_text) {
                        return null;
                    }

                    return [
                        'source_key' => $key,
                        'title' => 'Wdrozenie rekomendacji audytora',
                        'description' => $answer->recommendation_text,
                        'priority' => $answer->risk_level,
                    ];
                }

                if (str_starts_with($key, 'recommendation:')) {
                    $recommendation = $recommendations->get((int) str_replace('recommendation:', '', $key));

                    if (! $recommendation) {
                        return null;
                    }

                    return [
                        'source_key' => $key,
                        'title' => $recommendation->title,
                        'description' => $recommendation->recommendation_text,
                        'priority' => $recommendation->priority,
                    ];
                }

                return null;
            })
            ->filter()
            ->values();
    }
}
