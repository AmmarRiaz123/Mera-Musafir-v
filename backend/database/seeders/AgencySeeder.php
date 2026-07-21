<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyPackage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Seeds sample agencies + published packages so the Packages/Agencies pages,
 * package detail (report button), and the package->trip booking flow are
 * testable. Idempotent (firstOrCreate on email / slug).
 *
 * Agency owner logins (password: password): agency1@test.com, agency2@test.com
 */
class AgencySeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'agency']);

        $agencies = [
            [
                'email'         => 'agency1@test.com',
                'owner'         => 'Hunza Explorers Admin',
                'business_name' => 'Hunza Explorers',
                'tier'          => 'pro',
                'is_verified'   => true,
                'description'   => 'Specialists in Northern Pakistan adventures since 2015. Hunza, Skardu, Fairy Meadows and beyond.',
                'contact_phone' => '03001234567',
                'contact_email' => 'hello@hunzaexplorers.pk',
                'packages'      => [
                    [
                        'destination_id' => 1,
                        'title'          => 'Hunza Valley 6-Day Explorer',
                        'type'           => 'extended',
                        'price'          => 35000,
                        'capacity'       => 15,
                        'duration'       => 6,
                        'days_out'       => 21,
                        'trip_len'       => 6,
                        'includes'       => ['Transport (AC coaster)', 'Hotel (3 nights)', 'Breakfast & dinner', 'Guide'],
                    ],
                    [
                        'destination_id' => 2,
                        'title'          => 'Fairy Meadows Weekend Trek',
                        'type'           => 'weekend',
                        'price'          => 18000,
                        'capacity'       => 12,
                        'duration'       => 3,
                        'days_out'       => 14,
                        'trip_len'       => 3,
                        'includes'       => ['Transport', 'Camping gear', 'Meals', 'Trek guide'],
                    ],
                ],
            ],
            [
                'email'         => 'agency2@test.com',
                'owner'         => 'Karakoram Adventures Admin',
                'business_name' => 'Karakoram Adventures',
                'tier'          => 'basic',
                'is_verified'   => false,
                'description'   => 'Budget-friendly group tours across Gilgit-Baltistan. Small groups, big memories.',
                'contact_phone' => '03007654321',
                'contact_email' => 'info@karakoramadv.pk',
                'packages'      => [
                    [
                        'destination_id' => 3,
                        'title'          => 'Skardu Luxury Escape',
                        'type'           => 'extended',
                        'price'          => 48000,
                        'capacity'       => 10,
                        'duration'       => 5,
                        'days_out'       => 30,
                        'trip_len'       => 5,
                        'includes'       => ['Flight (optional)', '4-star hotel', 'All meals', 'Jeep safari'],
                    ],
                    [
                        'destination_id' => 5,
                        'title'          => 'Attabad Lake Day Trip',
                        'type'           => 'day_trip',
                        'price'          => 8000,
                        'capacity'       => 20,
                        'duration'       => 1,
                        'days_out'       => 10,
                        'trip_len'       => 1,
                        'includes'       => ['Transport', 'Boat ride', 'Lunch'],
                    ],
                ],
            ],
        ];

        foreach ($agencies as $a) {
            $owner = User::firstOrCreate(
                ['email' => $a['email']],
                [
                    'name'        => $a['owner'],
                    'password'    => Hash::make('password'),
                    'type'        => 'agency',
                    'is_verified' => $a['is_verified'],
                ]
            );
            $owner->assignRole('agency');

            $agency = Agency::firstOrCreate(
                ['user_id' => $owner->id],
                [
                    'business_name' => $a['business_name'],
                    'slug'          => Str::slug($a['business_name']),
                    'description'   => $a['description'],
                    'tier'          => $a['tier'],
                    'is_verified'   => $a['is_verified'],
                    'verified_at'   => $a['is_verified'] ? now() : null,
                    'contact_phone' => $a['contact_phone'],
                    'contact_email' => $a['contact_email'],
                    'total_trips'   => 0,
                ]
            );

            foreach ($a['packages'] as $p) {
                AgencyPackage::firstOrCreate(
                    ['slug' => Str::slug($p['title'])],
                    [
                        'agency_id'          => $agency->id,
                        'destination_id'     => $p['destination_id'],
                        'title'              => $p['title'],
                        'description'        => $p['title'] . ' — a curated group experience by ' . $a['business_name'] . '.',
                        'price_per_person'   => $p['price'],
                        'max_capacity'       => $p['capacity'],
                        'booked_count'       => 0,
                        'start_date'         => now()->addDays($p['days_out'])->toDateString(),
                        'end_date'           => now()->addDays($p['days_out'] + $p['trip_len'])->toDateString(),
                        'duration_days'      => $p['duration'],
                        'includes'           => $p['includes'],
                        'itinerary_overview' => ['Day 1: Arrival & orientation', 'Day 2 onward: Guided exploration'],
                        'type'               => $p['type'],
                        'status'             => 'published',
                    ]
                );
            }
        }

        $this->command->info('Agencies seeded: 2 agencies (agency1@/agency2@test.com) + 4 published packages');
    }
}
