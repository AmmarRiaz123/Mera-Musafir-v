<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\AgencyPackage;
use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Populates cover images / avatars so the UI isn't full of grey placeholders.
 *
 * IMPORTANT: these are generic scenic stock photos, downloaded once and served
 * from local storage. They are NOT verified photographs of the specific places
 * they're attached to — swap in licensed, correctly-attributed images before
 * showing this to real users.
 *
 * Idempotent: only fills rows that have no image yet. Re-running is safe and
 * won't re-download anything already on disk.
 */
class ImageSeeder extends Seeder
{
    /** Landscape/scenic pool (Unsplash photo ids). */
    private const SCENERY = [
        '1506905925346-21bda4d32df4',
        '1464822759023-fed622ff2c3b',
        '1454496522488-7a8e488e8606',
        '1470071459604-3b5ec3a7fe05',
        '1439853949127-fa647821eba0',
        '1519681393784-d120267933ba',
        '1486911278844-a81c5267e227',
        '1500534314209-a25ddb2bd429',
        '1501785888041-af3ef285b470',
        '1476514525535-07fb3b4ae5f1',
    ];

    public function run(): void
    {
        $scenery = $this->fetchPool('seed/scenery', self::SCENERY, 1200);

        if (empty($scenery)) {
            $this->command->warn('ImageSeeder: no images could be downloaded — skipping (are you offline?).');
            return;
        }

        $this->fill(Destination::class,  'cover_image', $scenery, 'destinations');
        $this->fill(Trip::class,         'cover_image', $scenery, 'trips');
        $this->fill(AgencyPackage::class, 'cover_image', $scenery, 'packages');
        $this->fill(Agency::class,       'cover_image', $scenery, 'agencies');

        $this->fillAvatars();
    }

    /**
     * Download each source once into the public disk; returns the stored paths.
     */
    private function fetchPool(string $dir, array $ids, int $width): array
    {
        $paths = [];

        foreach ($ids as $i => $id) {
            $path = "{$dir}/{$i}.jpg";

            if (Storage::disk('public')->exists($path)) {
                $paths[] = $path;
                continue;
            }

            $url   = "https://images.unsplash.com/photo-{$id}?w={$width}&q=75&fit=crop";
            $bytes = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 20, 'user_agent' => 'MeraMusafir/seed'],
            ]));

            if ($bytes === false || strlen($bytes) < 1024) {
                continue;
            }

            Storage::disk('public')->put($path, $bytes);
            $paths[] = $path;
        }

        $this->command->info('ImageSeeder: ' . count($paths) . " image(s) ready in {$dir}");

        return $paths;
    }

    /**
     * Assign images round-robin, seeded by row id so each record keeps a
     * stable picture across re-runs.
     */
    private function fill(string $model, string $column, array $pool, string $label): void
    {
        $filled = 0;

        $model::whereNull($column)->orderBy('id')->get()->each(function ($row) use ($column, $pool, &$filled) {
            $row->forceFill([$column => $pool[$row->id % count($pool)]])->save();
            $filled++;
        });

        $this->command->info("ImageSeeder: {$label} — {$filled} image(s) attached");
    }

    private function fillAvatars(): void
    {
        // Deterministic, friendly-looking avatars generated from the user's
        // name — no faces of real people, and no external dependency at runtime.
        $palette = ['5C1A5C', '4A148C', '6A1B9A', '00695C', 'AD1457', '283593', 'EF6C00', '37474F'];
        $filled  = 0;

        User::whereNull('avatar')->orderBy('id')->get()->each(function (User $user) use ($palette, &$filled) {
            $path = "seed/avatars/{$user->id}.svg";

            if (!Storage::disk('public')->exists($path)) {
                $initial = strtoupper(mb_substr(trim($user->name), 0, 1)) ?: 'M';
                $bg      = $palette[$user->id % count($palette)];

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
            $filled++;
        });

        $this->command->info("ImageSeeder: avatars — {$filled} generated");
    }
}
