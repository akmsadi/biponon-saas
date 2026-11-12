<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the request only when the user's role in the current organization is at least the given one, e.g.
 * `role:manager`. Runs after the `organization` middleware, whose organization carries the membership pivot.
 */
class EnsureOrganizationRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $minimumRole): Response
    {
        /** @var Organization $organization */
        $organization = $request->attributes->get('organization');

        abort_unless(Organization::roleIncludes($organization?->pivot?->role, $minimumRole), 403);

        return $next($request);
    }
}
