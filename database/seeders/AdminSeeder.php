<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator'],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@airsense.test'],
            [
                'name' => 'AirSense Admin',
                'password' => Hash::make('P@$$w0rd'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
                'gender' => 'other',
            ],
        );
    }
}
