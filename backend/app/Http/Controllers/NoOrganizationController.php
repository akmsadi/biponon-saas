<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where users land when they are not an active member of any active organization, e.g. after being suspended.
 */
class NoOrganizationController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($request->user()->activeOrganizations()->exists()) {
            return to_route('dashboard');
        }

        if ($request->user()->isSuperAdmin()) {
            return to_route('admin.organizations.index');
        }

        return Inertia::render('auth/no-organization');
    }
}
