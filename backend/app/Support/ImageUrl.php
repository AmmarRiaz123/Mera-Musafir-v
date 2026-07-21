<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUrl
{
    /**
     * Images are stored as paths on the public disk so they stay portable
     * across domains. Anything already absolute (an external URL or a data URI)
     * is passed straight through.
     */
    public static function resolve(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (Str::startsWith($value, ['http://', 'https://', 'data:', '//'])) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Inverse of resolve(): store a relative path, never our own absolute URL.
     * The client round-trips the resolved URL back to us on save, and hard-coding
     * the current host into the database would break on any domain change.
     */
    public static function toPath(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $prefix = rtrim((string) config('app.url'), '/') . '/storage/';

        return Str::startsWith($value, $prefix) ? Str::after($value, $prefix) : $value;
    }

    /** Resolve a list (e.g. a gallery column). */
    public static function resolveMany(?array $values): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => self::resolve(is_string($v) ? $v : null),
            $values ?? []
        )));
    }
}
