<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Fortify\Features;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'activeOrganizations' => fn () => $user?->activeOrganizations->map(fn ($org) => [
                    'id' => $org->id,
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'role' => $org->pivot->role,
                ]) ?? [],
                'currentOrganizationId' => fn () => $request->session()->get('current_organization_id'),
                'can' => fn () => $this->permissions($request),
            ],

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'canRegister' => Features::enabled(Features::registration()),
        ];
    }

    /**
     * What the user may do in the current organization, so pages can hide actions they would be refused.
     * Pages outside the `organization` middleware get no permissions.
     *
     * @return array{runOperations: bool, manageCatalog: bool, manageWarehouses: bool, manageMembers: bool}
     */
    private function permissions(Request $request): array
    {
        $role = $request->attributes->get('organization')?->pivot?->role;

        return [
            'runOperations' => Organization::roleIncludes($role, 'member'),
            'manageCatalog' => Organization::roleIncludes($role, 'manager'),
            'manageWarehouses' => Organization::roleIncludes($role, 'manager'),
            'manageMembers' => Organization::roleIncludes($role, 'admin'),
        ];
    }
}
