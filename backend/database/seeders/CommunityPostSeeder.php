<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\Destination;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo content for the community feed. Idempotent — posts are keyed on their
 * body text, so re-running won't duplicate them.
 */
class CommunityPostSeeder extends Seeder
{
    /** [type, body, destination slug (or null)] */
    private const POSTS = [
        ['tip',     'Hunza in autumn is unreal — the poplars turn gold around mid-October. Pack proper layers, the nights drop below freezing.', 'hunza-valley'],
        ['story',   'Did the Fairy Meadows jeep track last weekend. Terrifying, gorgeous, worth every second. Nanga Parbat at sunrise is something I will not forget.', 'fairy-meadows'],
        ['tip',     'For Deosai, carry cash. There is no signal and no card machines for hours in any direction.', 'deosai-plains'],
        ['review',  'Did a 3-day Skardu trip with a local operator. Transport was on time, food was solid, guide knew every viewpoint. Would go again.', 'skardu'],
        ['story',   'Attabad Lake is bluer in person than any photo suggests. Went on a weekday and had the boats almost to ourselves.', 'attabad-lake'],
        ['tip',     'Book Naran hotels before June. Prices roughly double once the season opens and the good ones fill fast.', 'naran'],
        ['story',   'Swat in spring: green everywhere, rivers running high, and far fewer crowds than the north. Underrated.', 'swat-valley'],
        ['review',  'Kalash Valley during Chilam Joshi was a privilege to witness. Be respectful, ask before photographing people.', 'kalash-valley'],
        ['tip',     'Altitude hits harder than people expect above 3,000m. Give yourself a day to acclimatise before any serious walking.', null],
        ['story',   'Solo travelled to Gilgit as a woman and felt safe throughout. Plan your transport ahead and keep someone updated.', 'gilgit'],
        ['tip',     'Download offline maps before heading north. Coverage disappears past Chilas and stays gone for a while.', null],
        ['review',  'Shogran was pleasant but Siri Paye is the real reward. Go early, the clouds roll in by midday.', 'shogran'],
        ['companion', 'Planning Skardu for 5 days mid-September, flying into Islamabad first. Looking for 2-3 people to split transport and rooms. Easy pace, lots of photo stops.', 'skardu'],
        ['alert',   'Babusar Pass was closed this morning after overnight snow. Check before you commit to that route — the detour via Besham adds most of a day.', 'naran'],
        ['question','First time driving the KKH in my own car. Is a sedan realistic past Chilas or should I arrange a 4x4?', null],
        ['gear',    'Three trips in and my packing list has shrunk a lot: down jacket, one fleece, thermals, a 20,000mAh power bank, and a proper headtorch. Everything else I regretted carrying.', null],
        ['budget',  'Full breakdown of 6 days in Hunza for two people: transport 28k, rooms 24k, food 15k, entries and misc 8k. Total about 75,000 PKR, split.', 'hunza-valley'],
        ['safety',  'Heads up — someone is running a fake booking page using a real agency name in Gilgit. Verify on the platform before you transfer anything.', 'gilgit'],
    ];

    public function run(): void
    {
        $authors = User::where('type', 'traveler')->orderBy('id')->take(6)->get();

        if ($authors->isEmpty()) {
            $this->command->warn('CommunityPostSeeder: no traveller accounts — run TestUsersSeeder first.');
            return;
        }

        // Reuse the scenery already downloaded by ImageSeeder; no new network calls.
        $images = collect(Storage::disk('public')->files('seed/scenery'))->values();
        $created = 0;

        foreach (self::POSTS as $i => [$type, $body, $slug]) {
            $exists = CommunityPost::where('body', $body)->exists();
            if ($exists) {
                continue;
            }

            CommunityPost::create([
                'user_id'        => $authors[$i % $authors->count()]->id,
                'destination_id' => $slug ? Destination::where('slug', $slug)->value('id') : null,
                'type'           => $type,
                'body'           => $body,
                // Give roughly half the posts a photo so the feed isn't uniform.
                'media_url'      => ($i % 2 === 0 && $images->isNotEmpty())
                    ? $images[$i % $images->count()]
                    : null,
                'media_type'     => ($i % 2 === 0 && $images->isNotEmpty()) ? 'image' : null,
                'created_at'     => now()->subHours(($i + 1) * 7),
                'updated_at'     => now()->subHours(($i + 1) * 7),
            ]);
            $created++;
        }

        $this->command->info("CommunityPostSeeder: {$created} post(s) created");
    }
}
