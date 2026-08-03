<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuditTrail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    private const AUDITED_FIELDS = [
        'name',
        'email',
        'role',
        'client_id',
        'mfa_enabled',
        'active',
    ];

    public function saving(User $user): void
    {
        /** @var User|null $actor */
        $actor = Auth::user();

        if ($actor?->is($user)) {
            $roleChanged = $user->exists
                && $user->getRawOriginal('role') !== ($user->getAttributes()['role'] ?? null);

            if ($roleChanged || ! $user->active) {
                throw new AuthorizationException('Nie mozna odebrac roli ani dezaktywowac wlasnego konta.');
            }
        }

        if (
            $actor?->hasRole(UserRole::GlobalAdmin)
            && ($user->getRawOriginal('role') === UserRole::SuperAdmin->value
                || ($user->getAttributes()['role'] ?? null) === UserRole::SuperAdmin->value)
        ) {
            throw new AuthorizationException('Tylko Super Admin moze zarzadzac rola Super Admin.');
        }

        if ($user->role !== UserRole::Client) {
            $user->client_id = null;
        }
    }

    public function created(User $user): void
    {
        AuditTrail::record('user.created', $user, newValues: $this->snapshot($user));
    }

    public function updated(User $user): void
    {
        $changedFields = array_values(array_intersect(self::AUDITED_FIELDS, array_keys($user->getChanges())));

        if ($changedFields === []) {
            return;
        }

        AuditTrail::record(
            'user.updated',
            $user,
            oldValues: collect($changedFields)->mapWithKeys(fn (string $field): array => [
                $field => $user->getRawOriginal($field),
            ])->all(),
            newValues: collect($changedFields)->mapWithKeys(fn (string $field): array => [
                $field => $user->getAttributes()[$field] ?? null,
            ])->all(),
        );
    }

    public function deleting(User $user): void
    {
        if (Auth::id() === $user->id) {
            throw new AuthorizationException('Nie mozna usunac wlasnego konta.');
        }
    }

    public function deleted(User $user): void
    {
        AuditTrail::record('user.deleted', $user, oldValues: $this->snapshot($user));
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return collect(self::AUDITED_FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => $user->getAttributes()[$field] ?? null])
            ->all();
    }
}
