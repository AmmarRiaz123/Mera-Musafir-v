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
     * Where each kind of upload goes, the longest edge it's downscaled to, and
     * the size ceiling. Images are re-encoded to JPEG so a 12MP phone photo
     * can't be served to every visitor.
     */
    private const TYPES = [
        'avatar'         => ['dir' => 'avatars',      'max' => 512,  'kb' => 5120],
        'trip_cover'     => ['dir' => 'trips',        'max' => 1600, 'kb' => 5120],
        'package_cover'  => ['dir' => 'packages',     'max' => 1600, 'kb' => 5120],
        'agency_logo'    => ['dir' => 'agencies',     'max' => 512,  'kb' => 5120],
        'destination'    => ['dir' => 'destinations', 'max' => 1600, 'kb' => 5120],
        // Community media accepts video and animated GIFs as well as stills.
        'post_media'     => ['dir' => 'posts',        'max' => 1600, 'kb' => 51200, 'rich' => true],
    ];

    private const VIDEO_MIMES = ['mp4', 'webm', 'mov', 'quicktime'];

    public function store(Request $request)
    {
        $type   = $request->input('type');
        $config = self::TYPES[$type] ?? null;

        if (!$config) {
            return response()->json([
                'message' => 'Unknown upload type.',
                'errors'  => ['type' => ['Unknown upload type.']],
            ], 422);
        }

        $rich  = $config['rich'] ?? false;
        $mimes = $rich ? 'jpg,jpeg,png,webp,gif,mp4,webm,mov' : 'jpg,jpeg,png,webp';

        $request->validate([
            'file' => ['required', 'file', "mimes:{$mimes}", "max:{$config['kb']}"],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], [
            'file.max'   => $rich
                ? 'Videos must be 50MB or smaller.'
                : 'Images must be 5MB or smaller.',
            'file.mimes' => $rich
                ? 'Use a JPG, PNG, WebP, GIF, MP4, WebM or MOV file.'
                : 'Use a JPG, PNG or WebP image.',
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime      = $file->getMimeType();

        $isVideo = in_array($extension, self::VIDEO_MIMES, true) || str_starts_with($mime, 'video/');
        $isGif   = $extension === 'gif' || $mime === 'image/gif';

        // Video and GIF are stored untouched: re-encoding video needs FFmpeg,
        // and running a GIF through GD would flatten it to a single frame.
        if ($isVideo || $isGif) {
            $path = $config['dir'] . '/' . Str::uuid() . '.' . ($isGif ? 'gif' : $extension);
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

            return response()->json([
                'path'       => $path,
                'url'        => Storage::disk('public')->url($path),
                'media_type' => $isGif ? 'gif' : 'video',
            ], 201);
        }

        $path = $config['dir'] . '/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, $this->normalise($file->getRealPath(), $config['max']));

        return response()->json([
            'path'       => $path,
            'url'        => Storage::disk('public')->url($path),
            'media_type' => 'image',
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
