<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\AcceptInvitation;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\OrganizationInvitation;
use App\Models\User;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of an invitation link. Depending on who opens it, the page offers to accept it, to log in
 * first, to create an account (registration is otherwise closed), or explains that it is for someone else.
 */
class InvitationAcceptanceController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function show(Request $request, string $token): Response
    {
        $invitation = OrganizationInvitation::findByToken($token);

        if (! $invitation?->isUsable()) {
            return Inertia::render('auth/accept-invitation', ['state' => 'invalid', 'token' => $token]);
        }

        $user = $request->user();
        $hasAccount = User::where('email', $invitation->email)->exists();

        $state = match (true) {
            $user !== null && mb_strtolower($user->email) === $invitation->email => 'accept',
            $user !== null => 'wrong_account',
            $hasAccount => 'login',
            default => 'register',
        };

        if ($state === 'login') {
            // Bring the user back to this link after they log in.
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return Inertia::render('auth/accept-invitation', [
            'state' => $state,
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'organization' => $invitation->organization->name,
                'invited_by' => $invitation->inviter?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'signedInAs' => $user?->email,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Accept as the logged-in user, whose email must be the invited one.
     */
    public function accept(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->usableInvitation($token);

        abort_unless(mb_strtolower($request->user()->email) === $invitation->email, 403);

        $this->acceptAndEnter($request, $invitation, $request->user(), $acceptInvitation);

        return to_route('dashboard');
    }

    /**
     * Create the account for an invited email that has none yet, log it in and accept.
     */
    public function register(Request $request, string $token, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $invitation = $this->usableInvitation($token);

        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['name' => __('An account with this email already exists. Log in to accept.')]);
        }

        $validated = $request->validate([
            'name' => $this->nameRules(),
            'password' => $this->passwordRules(),
        ]);

        $user = DB::transaction(function () use ($validated, $invitation, $request, $acceptInvitation): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
            ]);

            $this->acceptAndEnter($request, $invitation, $user, $acceptInvitation);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    private function usableInvitation(string $token): OrganizationInvitation
    {
        $invitation = OrganizationInvitation::findByToken($token);

        abort_unless($invitation?->isUsable(), 404);

        return $invitation;
    }

    /**
     * @throws ValidationException when the invitation can no longer be accepted
     */
    private function acceptAndEnter(Request $request, OrganizationInvitation $invitation, User $user, AcceptInvitation $acceptInvitation): void
    {
        try {
            $acceptInvitation->handle($invitation, $user);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['invitation' => $exception->getMessage()]);
        }

        $request->session()->put('current_organization_id', $invitation->organization_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :organization.', ['organization' => $invitation->organization->name])]);
    }
}
