<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccessRequestRequest;
use App\Models\AccessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;

class AccessRequestController extends Controller
{
    /** Requests one visitor (by IP address) may send per minute. */
    private const PER_MINUTE = 5;

    /**
     * Save a request from the landing page. Bots (honeypot filled) and repeats from the same email
     * within 24 hours get the same "thanks" as everyone else, but nothing is saved, so the form
     * never reveals which addresses have already asked.
     */
    public function store(StoreAccessRequestRequest $request): RedirectResponse
    {
        $limiterKey = 'access-request:'.$request->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, self::PER_MINUTE)) {
            // A code, not a sentence: the landing page shows it in the visitor's language.
            return back()->withErrors(['form' => 'too_many_attempts']);
        }

        RateLimiter::hit($limiterKey);

        $validated = $request->safe()->except('website');

        $isBot = filled($request->input('website'));
        $isRepeat = AccessRequest::query()
            ->where('email', $validated['email'])
            ->where('created_at', '>=', now()->subHours(AccessRequest::REPEAT_AFTER_HOURS))
            ->exists();

        if (! $isBot && ! $isRepeat) {
            AccessRequest::create([
                ...$validated,
                'language' => $validated['language'] ?? 'en',
                'status' => 'new',
            ]);
        }

        Inertia::flash('accessRequested', true);

        return back();
    }
}
