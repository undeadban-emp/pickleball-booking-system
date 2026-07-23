<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WebpImageStorer
{
    /**
     * Re-encode an uploaded image as WebP and store it on the public disk,
     * so every image in a gallery is a consistent, smaller format regardless
     * of what the admin originally uploaded (jpg/png/gif/etc).
     */
    public static function store(UploadedFile $file, string $directory, int $quality = 82): string
    {
        $source = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/gif' => imagecreatefromgif($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            'image/bmp' => imagecreatefrombmp($file->getRealPath()),
            default => throw new RuntimeException('Unsupported image type: '.$file->getMimeType()),
        };

        if ($source === false) {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        // Preserve transparency (PNG/GIF) instead of it turning black on re-encode.
        imagepalettetotruecolor($source);
        imagealphablending($source, true);
        imagesavealpha($source, true);

        $directory = trim($directory, '/');
        Storage::disk('public')->makeDirectory($directory);

        $relativePath = $directory.'/'.Str::random(40).'.webp';
        $absolutePath = Storage::disk('public')->path($relativePath);

        imagewebp($source, $absolutePath, $quality);
        imagedestroy($source);

        return $relativePath;
    }
}
