<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the shared demo accounts usable for every visitor. Their password is public in demo mode, so they may
 * not change their login details, delete themselves, add two-factor or passkeys, change who belongs to the demo
 * organization, or send invitation emails. Outside demo mode they are logged out and cannot log in.
 */
class RestrictDemoAccounts
{
    /**
     * Routes demo accounts are refused.
     *
     * @var list<string>
     */
    public const BLOCKED_ROUTES = [
        'profile.update',
        'profile.destroy',
        'user-password.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'passkey.store',
        'passkey.destroy',
        'organization.invitations.store',
        'organization.invitations.resend',
        'organization.members.update',
        'organization.members.destroy',
        'organization.settings.membership.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isDemo()) {
            return $next($request);
        }

        if (! PlatformSetting::demoMode()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401);
            }

            return to_route('login')->with('status', __('Demo accounts are only available while the site is in demo mode.'));
        }

        if (in_array($request->route()?->getName(), self::BLOCKED_ROUTES, true)) {
            $message = __('Demo accounts are shared by every visitor, so this is turned off for them.');

            if ($request->expectsJson()) {
                abort(403, $message);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        }

        return $next($request);
    }
}
