<?php

namespace App\Domain\Menus\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Re-encodes uploads to WebP so embedded payloads and metadata never reach storage. */
class StoreMenuImage
{
    public function __invoke(UploadedFile $file, string $directory = 'menu', int $maxWidth = 1200): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw ValidationException::withMessages(['image' => 'That image could not be read.']);
        }

        if (imagesx($image) > $maxWidth) {
            $image = imagescale($image, $maxWidth);
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, 82);
        $bytes = ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
