<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organizations = $request->user()->activeOrganizations;
        $sessionOrganizationId = $request->session()->get('current_organization_id');

        // Prefer the session's organization while the user is still an active member, otherwise the first by name.
        $organization = $organizations->firstWhere('id', $sessionOrganizationId) ?? $organizations->first();

        abort_if($organization === null, 403, 'You are not a member of any active organization.');

        if ($organization->id !== $sessionOrganizationId) {
            $request->session()->put('current_organization_id', $organization->id);
        }

        $request->attributes->set('organization', $organization);

        return $next($request);
    }
}
