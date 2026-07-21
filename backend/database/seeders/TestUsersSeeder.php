<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Systematic quick-switch test travelers (test1..test6).
 *
 * All share the password 'password'. Details are deliberately varied:
 * 3 female / 3 male (women-only trip tests) and 3 verified / 3 not
 * (verified-badge tests), each with distinct city + travel preferences.
 *
 * Idempotent (firstOrCreate on email) so it is safe to run repeatedly and
 * is wired into DatabaseSeeder, so the accounts survive `migrate:fresh --seed`.
 */
class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'traveler']);

        // [name, email, gender, city, is_verified, preferences]
        $users = [
            ['test1', 'test1@test.com', 'female', 'Lahore',    true,  ['travel_style' => ['adventure', 'cultural'],   'regions' => ['Northern Areas', 'Punjab']]],
            ['test2', 'test2@test.com', 'male',   'Karachi',   false, ['travel_style' => ['budget', 'backpacking'],   'regions' => ['Sindh', 'Balochistan']]],
            ['test3', 'test3@test.com', 'female', 'Islamabad', true,  ['travel_style' => ['luxury', 'cultural'],      'regions' => ['Northern Areas', 'KPK']]],
            ['test4', 'test4@test.com', 'male',   'Sialkot',   false, ['travel_style' => ['adventure', 'backpacking'], 'regions' => ['Gilgit-Baltistan', 'AJK']]],
            ['test5', 'test5@test.com', 'female', 'Multan',    true,  ['travel_style' => ['cultural', 'budget'],      'regions' => ['Punjab', 'Sindh']]],
            ['test6', 'test6@test.com', 'male',   'Peshawar',  false, ['travel_style' => ['adventure', 'luxury'],     'regions' => ['KPK', 'Northern Areas']]],
        ];

        foreach ($users as [$name, $email, $gender, $city, $verified, $preferences]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'        => $name,
                    'password'    => Hash::make('password'),
                    'type'        => 'traveler',
                    'gender'      => $gender,
                    'city'        => $city,
                    'is_verified' => $verified,
                    'preferences' => $preferences,
                ]
            );

            $user->assignRole('traveler');
        }

        $this->command->info('Test users seeded: test1..test6 (password: password)');
    }
}
