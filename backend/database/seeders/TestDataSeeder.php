<?php

namespace Database\Seeders;

use App\Models\Trip;
use App\Models\TripMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create 6 test travelers with different preferences
        $travelers = [
            [
                'name'        => 'Ahmed Khan',
                'email'       => 'ahmed@test.com',
                'preferences' => ['travel_style' => ['adventure', 'backpacking'], 'regions' => ['Northern Areas', 'Karakoram Range']],
                'city'        => 'Lahore',
                'gender'      => 'male',
            ],
            [
                'name'        => 'Sara Ali',
                'email'       => 'sara@test.com',
                'preferences' => ['travel_style' => ['cultural', 'luxury'], 'regions' => ['Punjab', 'Sindh']],
                'city'        => 'Karachi',
                'gender'      => 'female',
            ],
            [
                'name'        => 'Bilal Mahmood',
                'email'       => 'bilal@test.com',
                'preferences' => ['travel_style' => ['budget', 'backpacking'], 'regions' => ['KPK', 'Northern Areas']],
                'city'        => 'Islamabad',
                'gender'      => 'male',
            ],
            [
                'name'        => 'Fatima Zahra',
                'email'       => 'fatima@test.com',
                'preferences' => ['travel_style' => ['adventure', 'cultural'], 'regions' => ['Northern Areas', 'AJK']],
                'city'        => 'Rawalpindi',
                'gender'      => 'female',
            ],
            [
                'name'        => 'Hassan Raza',
                'email'       => 'hassan@test.com',
                'preferences' => ['travel_style' => ['luxury', 'cultural'], 'regions' => ['Balochistan', 'Sindh']],
                'city'        => 'Multan',
                'gender'      => 'male',
            ],
            [
                'name'        => 'Zainab Malik',
                'email'       => 'zainab@test.com',
                'preferences' => ['travel_style' => ['adventure', 'backpacking'], 'regions' => ['Northern Areas', 'Gilgit-Baltistan']],
                'city'        => 'Peshawar',
                'gender'      => 'female',
            ],
        ];

        $createdUsers = [];
        foreach ($travelers as $data) {
            $createdUsers[] = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'        => $data['name'],
                    'password'    => Hash::make('password'),
                    'type'        => 'traveler',
                    'city'        => $data['city'],
                    'gender'      => $data['gender'],
                    'preferences' => $data['preferences'],
                    'is_verified' => true,
                ]
            );
        }

        // Create 8 trips across different destinations and types
        $trips = [
            ['destination_id' => 1,  'title' => 'Hunza Valley Adventure',    'type' => 'adventure',  'start_date' => now()->addDays(15), 'end_date' => now()->addDays(20)],
            ['destination_id' => 2,  'title' => 'Fairy Meadows Trek',        'type' => 'backpacking','start_date' => now()->addDays(25), 'end_date' => now()->addDays(30)],
            ['destination_id' => 11, 'title' => 'Swat Valley Weekend',       'type' => 'cultural',   'start_date' => now()->addDays(8),  'end_date' => now()->addDays(10)],
            ['destination_id' => 20, 'title' => 'Lahore Heritage Tour',      'type' => 'cultural',   'start_date' => now()->addDays(5),  'end_date' => now()->addDays(7)],
            ['destination_id' => 5,  'title' => 'Attabad Lake Budget Trip',  'type' => 'budget',     'start_date' => now()->addDays(40), 'end_date' => now()->addDays(45)],
            ['destination_id' => 14, 'title' => 'Kalash Valley Cultural',    'type' => 'cultural',   'start_date' => now()->addDays(20), 'end_date' => now()->addDays(25)],
            ['destination_id' => 3,  'title' => 'Skardu Luxury Escape',      'type' => 'luxury',     'start_date' => now()->addDays(35), 'end_date' => now()->addDays(42)],
            ['destination_id' => 17, 'title' => 'Neelum Valley Backpacking', 'type' => 'backpacking','start_date' => now()->addDays(12), 'end_date' => now()->addDays(16)],
        ];

        foreach ($trips as $index => $tripData) {
            $creator = $createdUsers[$index % count($createdUsers)];

            $trip = Trip::create([
                ...$tripData,
                'creator_id'    => $creator->id,
                'description'   => 'An amazing trip to ' . $tripData['title'],
                'max_travelers' => 10,
                'current_count' => 1,
                'budget_min'    => 10000,
                'budget_max'    => 30000,
                'visibility'    => 'public',
                'status'        => 'planning',
            ]);

            // Add creator as host
            TripMember::firstOrCreate(
                ['trip_id' => $trip->id, 'user_id' => $creator->id],
                ['status' => 'joined', 'role' => 'host', 'joined_at' => now()]
            );

            // Add 2-3 random members
            $members = collect($createdUsers)->filter(fn($u) => $u->id !== $creator->id)->random(2);
            foreach ($members as $member) {
                TripMember::firstOrCreate(
                    ['trip_id' => $trip->id, 'user_id' => $member->id],
                    ['status' => 'joined', 'role' => 'member', 'joined_at' => now()]
                );
                $trip->increment('current_count');
            }
        }

        $this->command->info('Test data seeded: 6 travelers + 8 trips');
    }
}