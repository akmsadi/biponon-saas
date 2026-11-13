<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Grants or revokes platform super-admin rights. This is the only way to set the flag, so nobody can grant it
 * through the app.
 */
#[Signature('biponon:super-admin {email : The email address of an existing user} {--revoke : Take super-admin rights away}')]
#[Description('Make an existing user a platform super-admin, or revoke it')]
class SuperAdminCommand extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $user = User::firstWhere('email', $email);

        if ($user === null) {
            $this->components->error("No user with the email {$email}.");

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        $user->forceFill(['is_super_admin' => $grant])->save();

        $this->components->info($grant
            ? "{$user->email} is now a super-admin."
            : "{$user->email} is no longer a super-admin.");

        return self::SUCCESS;
    }
}
