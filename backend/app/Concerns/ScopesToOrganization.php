<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Helpers for controllers behind the `organization` middleware, which resolves the current organization.
 */
trait ScopesToOrganization
{
    /**
     * Get the current organization resolved by the SetCurrentOrganization middleware.
     */
    protected function organization(Request $request): Organization
    {
        return $request->attributes->get('organization');
    }

    /**
     * Whether the user's role in the current organization is at least the given one (admin > manager > member).
     */
    protected function roleIncludes(Request $request, string $minimumRole): bool
    {
        return Organization::roleIncludes($this->organization($request)->pivot?->role, $minimumRole);
    }

    /**
     * Abort with a 404 unless the model belongs to the current organization.
     */
    protected function ensureBelongsToOrganization(Request $request, Model $model): void
    {
        abort_unless($model->getAttribute('organization_id') === $this->organization($request)->id, 404);
    }
}
