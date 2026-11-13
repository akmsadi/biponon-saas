<?php

namespace App\Http\Controllers\Organization;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\GeneralSettingsRequest;
use App\Http\Requests\Organization\InventorySettingsRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current organization's settings. Every member can view them; only admins save (enforced by the routes).
 * Any member can leave the organization, except its last active admin.
 */
class SettingsController extends Controller
{
    use ScopesToOrganization;

    public function editGeneral(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('organization/settings/general', [
            'settings' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'contact_email' => $organization->setting('contact_email'),
                'phone' => $organization->setting('phone'),
                'address' => $organization->setting('address'),
                'timezone' => $organization->setting('timezone'),
                'currency' => $organization->setting('currency'),
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $organization->name = $request->validated('name');
        $organization->putSettings(Arr::except($request->validated(), 'name'));
        $organization->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }

    public function editInventory(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('organization/settings/inventory', [
            'settings' => [
                'default_warehouse_id' => $organization->defaultWarehouseId(),
                'default_reorder_level' => $organization->setting('default_reorder_level'),
            ],
            'warehouses' => $organization->warehouses()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function updateInventory(InventorySettingsRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        $organization->putSettings([
            'default_warehouse_id' => $request->validated('default_warehouse_id') === null ? null : (int) $request->validated('default_warehouse_id'),
            'default_reorder_level' => $request->validated('default_reorder_level') === null ? null : (int) $request->validated('default_reorder_level'),
        ]);
        $organization->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }

    public function editMembership(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('organization/settings/membership', [
            'organizationName' => $organization->name,
            'isLastAdmin' => $this->isLastAdmin($organization, $request),
        ]);
    }

    /**
     * Remove the current user from the organization. The next request picks another organization, if any.
     */
    public function leave(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);

        if ($this->isLastAdmin($organization, $request)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('You are the only admin. Make someone else an admin before leaving.')]);

            return back();
        }

        $organization->users()->detach($request->user()->id);
        $request->session()->forget('current_organization_id');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left :name.', ['name' => $organization->name])]);

        return to_route('dashboard');
    }

    private function isLastAdmin(Organization $organization, Request $request): bool
    {
        return $organization->hasAdmin($request->user())
            && $organization->admins()->count() === 1;
    }
}
