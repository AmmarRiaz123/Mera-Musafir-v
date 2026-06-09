<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // use WithoutModelEvents; // Assuming we want events for roles right now, or comment out

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles
        $roles = ['traveler', 'agency', 'admin'];
        
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@meramusafir.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'is_verified' => true,
            ]
        );
        $admin->assignRole('admin');

        // Optional default users
        $traveler = User::firstOrCreate(
            ['email' => 'traveler@example.com'],
            [
                'name' => 'Test Traveler',
                'password' => Hash::make('password'),
                'type' => 'traveler',
                'is_verified' => true,
            ]
        );
        $traveler->assignRole('traveler');
    }
}
