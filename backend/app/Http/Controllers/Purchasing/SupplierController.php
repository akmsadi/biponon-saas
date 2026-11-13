<?php

namespace App\Http\Controllers\Purchasing;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current organization's suppliers. Everyone views them; managers and admins change them (enforced by the routes).
 */
class SupplierController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's suppliers, active ones first.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('purchasing/suppliers/index', [
            'suppliers' => $this->organization($request)->suppliers()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'contact_name', 'email', 'phone', 'address', 'notes', 'is_active']),
        ]);
    }

    /**
     * Create a supplier in the current organization.
     */
    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->organization($request)->suppliers()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier created.')]);

        return back();
    }

    /**
     * Update a supplier of the current organization.
     */
    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $supplier);

        $supplier->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);

        return back();
    }

    /**
     * Delete a supplier that has no purchase orders.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This supplier has purchase orders. Deactivate it instead.')]);

            return back();
        }

        $supplier->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier deleted.')]);

        return back();
    }
}
