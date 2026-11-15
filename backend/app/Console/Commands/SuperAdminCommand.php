<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Grants or revokes platform super-admin rights. This is the only way to set the flag, so nobody can grant it
 * through the app. With --create it also makes the account when it doesn't exist yet (the first login on a fresh
 * install, where registration is off), asking for the password in the terminal so it never lands in a file.
 */
#[Signature('biponon:super-admin {email : The email address of the user} {--create : Create the user first if no one has this email} {--revoke : Take super-admin rights away}')]
#[Description('Make a user a platform super-admin (optionally creating the account), or revoke it')]
class SuperAdminCommand extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $grant = ! $this->option('revoke');
        $user = User::firstWhere('email', $email);

        if ($user === null && $grant && $this->option('create')) {
            return $this->createSuperAdmin($email);
        }

        if ($user === null) {
            $this->components->error("No user with the email {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => $grant])->save();

        $this->components->info($grant
            ? "{$user->email} is now a super-admin."
            : "{$user->email} is no longer a super-admin.");

        return self::SUCCESS;
    }

    private function createSuperAdmin(string $email): int
    {
        $input = [
            'email' => $email,
            'name' => trim((string) $this->ask('Name')),
            'password' => (string) $this->secret('Password'),
            'password_confirmation' => (string) $this->secret('Password again'),
        ];

        $validator = Validator::make($input, [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $input['name'], 'email' => $email, 'password' => $input['password']]);
        $user->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Created {$user->email} as a super-admin.");

        return self::SUCCESS;
    }
}
