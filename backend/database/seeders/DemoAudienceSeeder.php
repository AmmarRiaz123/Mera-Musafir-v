<?php

namespace Database\Seeders;

use App\Models\CommunityPost;
use App\Models\User;
use App\Services\FeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Creates a demo audience so showcase posts carry believable engagement.
 *
 * Likes are real rows (post_likes is unique per user), so a "several hundred
 * likes" figure genuinely needs several hundred accounts — faking the counter
 * would just reset the moment anyone liked the post, because counts are derived
 * from rows rather than incremented.
 *
 * These accounts use the @demo.meramusafir.test domain and can be removed with:
 *   User::where('email', 'like', '%@demo.meramusafir.test')->forceDelete();
 */
class DemoAudienceSeeder extends Seeder
{
    private const COUNT = 320;

    private const FIRST = [
        'Ayesha', 'Bilal', 'Hamza', 'Zara', 'Usman', 'Mahnoor', 'Faizan', 'Iqra',
        'Talha', 'Sana', 'Danish', 'Areeba', 'Shahzad', 'Noor', 'Hassan', 'Maryam',
        'Kamran', 'Hira', 'Owais', 'Fatima', 'Junaid', 'Amna', 'Rehan', 'Saba',
        'Adnan', 'Laiba', 'Waleed', 'Nimra', 'Salman', 'Rabia',
    ];

    private const LAST = [
        'Khan', 'Ahmed', 'Malik', 'Butt', 'Chaudhry', 'Qureshi', 'Shah', 'Baig',
        'Farooq', 'Siddiqui', 'Raza', 'Javed', 'Aslam', 'Hussain', 'Nawaz', 'Abbas',
    ];

    private const CITIES = [
        'Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Peshawar', 'Multan',
        'Faisalabad', 'Sialkot', 'Quetta', 'Gujranwala', 'Abbottabad', 'Gilgit',
    ];

    private const PALETTE = ['5C1A5C', '4A148C', '6A1B9A', '00695C', 'AD1457', '283593', 'EF6C00', '37474F'];

    public function run(): void
    {
        $created = 0;

        for ($i = 1; $i <= self::COUNT; $i++) {
            $email = "demo{$i}@demo.meramusafir.test";

            if (User::where('email', $email)->exists()) {
                continue;
            }

            $name = self::FIRST[$i % count(self::FIRST)] . ' ' . self::LAST[($i * 7) % count(self::LAST)];

            $user = User::create([
                'name'        => $name,
                'email'       => $email,
                'password'    => Hash::make('password'),
                'type'        => 'traveler',
                'city'        => self::CITIES[$i % count(self::CITIES)],
                'gender'      => $i % 2 === 0 ? 'female' : 'male',
                'is_verified' => $i % 5 === 0,
            ]);

            $user->assignRole('traveler');
            $this->avatarFor($user, $i);
            $created++;
        }

        $this->command->info("DemoAudienceSeeder: {$created} demo account(s) created");

        $this->likeShowcasePosts();
    }

    private function avatarFor(User $user, int $i): void
    {
        $path = "seed/avatars/demo-{$user->id}.svg";

        if (!Storage::disk('public')->exists($path)) {
            $initial = strtoupper(mb_substr($user->name, 0, 1));
            $bg      = self::PALETTE[$i % count(self::PALETTE)];

            Storage::disk('public')->put($path, <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">
              <rect width="256" height="256" fill="#{$bg}"/>
              <text x="50%" y="50%" dy=".35em" text-anchor="middle"
                    font-family="Helvetica,Arial,sans-serif" font-size="120"
                    font-weight="600" fill="#ffffff">{$initial}</text>
            </svg>
            SVG);
        }

        $user->forceFill(['avatar' => $path])->save();
    }

    /**
     * Spreads likes over the richest posts — most on the newest gallery post,
     * a long tail on the rest, so the feed doesn't look uniformly popular.
     */
    private function likeShowcasePosts(): void
    {
        $demoIds = User::where('email', 'like', '%@demo.meramusafir.test')->pluck('id');

        if ($demoIds->isEmpty()) {
            return;
        }

        $posts = CommunityPost::visible()->latest('id')->take(6)->get();
        $feed  = app(FeedService::class);

        foreach ($posts as $index => $post) {
            // Newest gallery post gets nearly everyone; older ones taper off.
            $share = max(8, (int) round($demoIds->count() / (1.9 ** $index)));
            $rows  = $demoIds->shuffle()->take($share)->map(fn ($id) => [
                'community_post_id' => $post->id,
                'user_id'           => $id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ])->all();

            // insertOrIgnore respects the unique (post, user) constraint.
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('post_likes')->insertOrIgnore($chunk);
            }

            $feed->syncCounters($post);
            $this->command->info("  post #{$post->id}: {$post->fresh()->likes_count} likes");
        }
    }
}
