<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UploadController extends Controller
{
    /**
     * Where each kind of image goes, and the longest edge it's downscaled to.
     * Everything is re-encoded to JPEG, so a 12MP phone photo can't end up
     * being served to every visitor.
     */
    private const TYPES = [
        'avatar'         => ['dir' => 'avatars',      'max' => 512],
        'trip_cover'     => ['dir' => 'trips',        'max' => 1600],
        'package_cover'  => ['dir' => 'packages',     'max' => 1600],
        'agency_logo'    => ['dir' => 'agencies',     'max' => 512],
        'destination'    => ['dir' => 'destinations', 'max' => 1600],
    ];

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], [
            'file.max'    => 'Images must be 5MB or smaller.',
            'file.mimes'  => 'Use a JPG, PNG or WebP image.',
            'file.image'  => "That file doesn't look like an image.",
        ]);

        $config = self::TYPES[$validated['type']];
        $path   = $config['dir'] . '/' . Str::uuid() . '.jpg';

        Storage::disk('public')->put(
            $path,
            $this->normalise($request->file('file')->getRealPath(), $config['max'])
        );

        return response()->json([
            'path' => $path,
            'url'  => Storage::disk('public')->url($path),
        ], 201);
    }

    /**
     * Downscale to a sane size and flatten to JPEG. Falls back to the original
     * bytes if GD can't read the file, so an upload never hard-fails here.
     */
    private function normalise(string $source, int $maxEdge): string
    {
        $info = @getimagesize($source);
        if (!$info) {
            return file_get_contents($source);
        }

        [$width, $height] = $info;

        $image = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png'  => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
            default      => null,
        };

        if (!$image) {
            return file_get_contents($source);
        }

        $scale     = min(1, $maxEdge / max($width, $height));
        $newWidth  = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // PNGs may be transparent; JPEG has no alpha, so flatten onto white.
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 82);
        $bytes = ob_get_clean();

        imagedestroy($image);
        imagedestroy($canvas);

        return $bytes;
    }
}
