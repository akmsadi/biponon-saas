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
     * Abort with a 404 unless the model belongs to the current organization.
     */
    protected function ensureBelongsToOrganization(Request $request, Model $model): void
    {
        abort_unless($model->getAttribute('organization_id') === $this->organization($request)->id, 404);
    }
}
