<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->active, 403);

        $allowedRoles = collect($roles)
            ->map(fn (string $role): ?UserRole => UserRole::tryFrom($role))
            ->filter()
            ->values();

        abort_unless($allowedRoles->contains(fn (UserRole $role): bool => $user->hasRole($role)), 403);

        return $next($request);
    }
}
