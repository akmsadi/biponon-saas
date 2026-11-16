<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the developer's own super-admin and the "Biponon" organization on a local machine. The account comes
 * from SEED_ADMIN_* in .env (config `app.seed_admin`) so no login details live in the repository; without them
 * the seeder does nothing.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $account = config('app.seed_admin');

        if (blank($account['email']) || blank($account['password'])) {
            $this->command?->warn('Skipping the admin account: set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD in .env to create it.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $account['email']],
            [
                'name' => $account['name'],
                'password' => Hash::make($account['password']),
            ],
        );

        $user->forceFill(['email_verified_at' => now(), 'is_super_admin' => true])->save();

        $organization = Organization::updateOrCreate(
            ['slug' => 'biponon'],
            [
                'name' => 'Biponon',
                'status' => 'active',
                'created_by' => $user->id,
            ],
        );

        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role' => 'admin',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);
    }
}
