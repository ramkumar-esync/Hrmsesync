<?php

declare(strict_types=1);

namespace HR\Identity\Presentation\Http\Middleware;

use Closure;
use HR\Identity\Domain\Enum\Role;
use HR\Identity\Infrastructure\Persistence\Eloquent\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: role:hr_admin or role:hr_admin,manager */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->is_active) {
            abort(401, 'Sign in to continue.');
        }

        $allowed = array_map(static fn (string $role) => Role::from($role), $roles);

        if (! $user->hasRole(...$allowed)) {
            abort(403, 'Your account does not have access to this area.');
        }

        return $next($request);
    }
}
