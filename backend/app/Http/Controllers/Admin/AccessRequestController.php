<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admins read the landing page's access requests, track who they have answered and turn a
 * request into an organization.
 */
class AccessRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), AccessRequest::STATUSES, true) ? $request->query('status') : null;

        $accessRequests = AccessRequest::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AccessRequest $accessRequest): array => [
                'id' => $accessRequest->id,
                'name' => $accessRequest->name,
                'company' => $accessRequest->company,
                'email' => $accessRequest->email,
                'phone' => $accessRequest->phone,
                'message' => $accessRequest->message,
                'language' => $accessRequest->language,
                'status' => $accessRequest->status,
                'created_at' => $accessRequest->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/access-requests', [
            'accessRequests' => $accessRequests,
            'filters' => ['search' => $search, 'status' => $status],
            'statusCounts' => AccessRequest::query()->toBase()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count),
        ]);
    }

    public function update(Request $request, AccessRequest $accessRequest): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(AccessRequest::STATUSES)]]);

        $accessRequest->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request from :company marked :status.', [
            'company' => $accessRequest->company,
            'status' => $validated['status'],
        ])]);

        return back();
    }

    public function destroy(AccessRequest $accessRequest): RedirectResponse
    {
        $accessRequest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request deleted.')]);

        return back();
    }
}
