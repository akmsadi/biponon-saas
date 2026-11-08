<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'akmsadi.cste@gmail.com'],
            [
                'name' => 'AKM Sadi',
                'password' => Hash::make('akmsadi102030'),
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

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
