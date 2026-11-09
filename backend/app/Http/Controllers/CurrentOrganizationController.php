<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentOrganizationController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(['organization_id' => ['required', 'integer']]);

        // 404s unless the user is an active member of that org
        $organization = $request->user()->activeOrganizations->find($validated['organization_id']);

        abort_if($organization === null, 404);

        $request->session()->put('current_organization_id', $organization->id);

        return back();
    }
}
