<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxies the GIF and music catalogues.
 *
 * These run server-side on purpose: the API keys never reach the browser, and
 * we get one place to cache, normalise and rate-limit the responses. Both
 * endpoints degrade gracefully when a key isn't configured, so the UI can show
 * "not set up yet" instead of breaking.
 */
class MediaSearchController extends Controller
{
    /** GET /media/gifs?q= — Giphy search. */
    public function gifs(Request $request)
    {
        $key = config('services.giphy.key');

        if (!$key) {
            Log::info('Giphy key missing — set GIPHY_KEY in .env to enable the GIF picker.');

            return response()->json([
                'configured' => false,
                // User-facing copy only — the setup hint goes to the log, not the UI.
                'message'    => "GIFs aren't available right now.",
                'data'       => [],
            ]);
        }

        $query = trim((string) $request->input('q', ''));
        $limit = min(30, max(1, (int) $request->input('limit', 24)));

        $cacheKey = 'giphy:' . md5($query . '|' . $limit);

        $data = Cache::remember($cacheKey, 600, function () use ($key, $query, $limit) {
            $endpoint = $query === ''
                ? 'https://api.giphy.com/v1/gifs/trending'
                : 'https://api.giphy.com/v1/gifs/search';

            $response = Http::timeout(10)->get($endpoint, array_filter([
                'api_key' => $key,
                'q'       => $query ?: null,
                'limit'   => $limit,
                'rating'  => 'g', // keep the picker safe for a travel app
            ]));

            if (!$response->successful()) {
                return null;
            }

            return collect($response->json('data', []))->map(fn ($gif) => [
                'id'      => $gif['id'] ?? null,
                'title'   => $gif['title'] ?? '',
                // Hotlinked, not copied to our disk — that's how Giphy is meant
                // to be used and it keeps our storage small.
                'url'     => data_get($gif, 'images.downsized.url'),
                'preview' => data_get($gif, 'images.fixed_width_small.url')
                          ?? data_get($gif, 'images.downsized.url'),
                'width'   => (int) data_get($gif, 'images.downsized.width', 0),
                'height'  => (int) data_get($gif, 'images.downsized.height', 0),
            ])->filter(fn ($g) => !empty($g['url']))->values()->all();
        });

        if ($data === null) {
            Cache::forget($cacheKey);
            return response()->json([
                'configured' => true,
                'message'    => "GIFs aren't available right now.",
                'data'       => [],
            ], 502);
        }

        // An empty result is usually transient upstream — don't let it stick
        // around for the full cache window.
        if ($data === []) {
            Cache::forget($cacheKey);
        }

        return response()->json(['configured' => true, 'data' => $data]);
    }

    /**
     * Curated shelves for the music picker.
     *
     * An empty search box is a bad prompt — people don't know what a
     * royalty-free catalogue contains, so they type an artist name, get
     * nothing, and conclude music is broken. Each shelf is a Jamendo tag that
     * was checked to actually return tracks; the labels are ours, phrased for
     * the kind of post they'd sit under rather than as genre jargon.
     */
    private const MOODS = [
        'chill'     => ['label' => 'Chill',      'tag' => 'chill'],
        'cinematic' => ['label' => 'Cinematic',  'tag' => 'cinematic'],
        'roadtrip'  => ['label' => 'Road trip',  'tag' => 'upbeat'],
        'adventure' => ['label' => 'Adventure',  'tag' => 'adventure'],
        'acoustic'  => ['label' => 'Acoustic',   'tag' => 'acoustic'],
        'desi'      => ['label' => 'Desi & world', 'tag' => 'bollywood'],
        'calm'      => ['label' => 'Calm',       'tag' => 'ambient'],
        'folk'      => ['label' => 'Folk',       'tag' => 'folk'],
    ];

    /** GET /media/music?q=&mood= — royalty-free catalogue (Jamendo). */
    public function music(Request $request)
    {
        $key = config('services.jamendo.client_id');

        // The shelf rail is ours, not Jamendo's, so it ships even when the
        // catalogue is unreachable — the picker still looks intentional.
        $moods = collect(self::MOODS)
            ->map(fn ($m, $slug) => ['slug' => $slug, 'label' => $m['label']])
            ->values()
            ->all();

        if (!$key) {
            Log::info('Jamendo client id missing — set JAMENDO_CLIENT_ID in .env to enable the music picker.');

            return response()->json([
                'configured' => false,
                'message'    => "Music isn't available right now.",
                'moods'      => $moods,
                'data'       => [],
            ]);
        }

        $query = trim((string) $request->input('q', ''));
        $mood  = (string) $request->input('mood', '');
        $limit = min(30, max(1, (int) $request->input('limit', 20)));

        // An explicit search wins over a shelf — typing means you know what you want.
        $tag = ($query === '' && isset(self::MOODS[$mood])) ? self::MOODS[$mood]['tag'] : null;

        $cacheKey = 'jamendo:' . md5($query . '|' . ($tag ?? '') . '|' . $limit);

        $data = Cache::remember(
            $cacheKey,
            600,
            fn () => $this->fetchTracks($key, $query, $tag, $limit)
        );

        if ($data === null) {
            Cache::forget($cacheKey);

            return response()->json([
                'configured' => true,
                'message'    => "Music isn't available right now.",
                'moods'      => $moods,
                'data'       => [],
            ], 502);
        }

        if ($data === []) {
            Cache::forget($cacheKey);
        }

        return response()->json([
            'configured' => true,
            'moods'      => $moods,
            'data'       => $data,
        ]);
    }

    /**
     * One Jamendo lookup, retried on an empty result.
     *
     * Jamendo intermittently answers a perfectly good tag with zero tracks —
     * the same request a second later returns twenty, at a measured rate of
     * roughly one in three. Without the retries a shelf looks permanently empty
     * on an unlucky roll; three attempts take that to about one in thirty.
     *
     * @return array<int, array>|null  null means the call itself failed
     */
    private function fetchTracks(string $key, string $query, ?string $tag, int $limit): ?array
    {
        foreach (range(1, 3) as $attempt) {
            $response = Http::timeout(10)->get('https://api.jamendo.com/v3.0/tracks/', array_filter([
                'client_id'   => $key,
                'format'      => 'json',
                'limit'       => $limit,
                'search'      => $query ?: null,
                'tags'        => $tag,
                'order'       => $query ? null : 'popularity_total',
                'audioformat' => 'mp32',
                'include'     => 'musicinfo',
            ]));

            if (!$response->successful() || $response->json('headers.status') !== 'success') {
                return null;
            }

            $tracks = collect($response->json('results', []))->map(fn ($track) => [
                'provider'  => 'jamendo',
                'id'        => (string) ($track['id'] ?? ''),
                // Jamendo HTML-escapes these, so a band called "Dada & the
                // Weathermen" arrives as "Dada &amp; the Weathermen" and Vue
                // renders the entity literally.
                'title'     => html_entity_decode($track['name'] ?? 'Untitled', ENT_QUOTES | ENT_HTML5),
                'artist'    => html_entity_decode($track['artist_name'] ?? 'Unknown artist', ENT_QUOTES | ENT_HTML5),
                'audio_url' => $track['audio'] ?? null,
                'cover'     => $track['album_image'] ?? $track['image'] ?? null,
                'duration'  => (int) ($track['duration'] ?? 0),
                // Jamendo tracks are Creative Commons licensed — surfaced so the
                // UI can credit the artist, which most CC licences require.
                'license'   => $track['license_ccurl'] ?? null,
            ])->filter(fn ($t) => !empty($t['audio_url']))->values()->all();

            if ($tracks !== [] || $attempt === 3) {
                return $tracks;
            }

            // Back-to-back retries tend to hit whatever upstream state caused
            // the empty in the first place, so give it a moment.
            usleep(250_000);
        }

        return [];
    }
}
