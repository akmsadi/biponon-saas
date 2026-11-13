<?php

namespace App\Http\Controllers\Sales;

use App\Concerns\ScopesToOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The current organization's customers. Everyone views them; managers and admins change them (enforced by the routes).
 */
class CustomerController extends Controller
{
    use ScopesToOrganization;

    /**
     * List the current organization's customers, active ones first.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('sales/customers/index', [
            'customers' => $this->organization($request)->customers()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'contact_name', 'email', 'phone', 'address', 'notes', 'is_active']),
        ]);
    }

    /**
     * Create a customer in the current organization.
     */
    public function store(CustomerRequest $request): RedirectResponse
    {
        $this->organization($request)->customers()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return back();
    }

    /**
     * Update a customer of the current organization.
     */
    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $customer);

        $customer->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return back();
    }

    /**
     * Delete a customer.
     */
    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        $this->ensureBelongsToOrganization($request, $customer);

        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return back();
    }
}
