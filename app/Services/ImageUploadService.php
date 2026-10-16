<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Desa imatges pujades de manera segura: es tornen a codificar en PNG (s'elimina qualsevol contingut
 * incrustat), es redueixen a una mida màxima i es guarden amb un nom aleatori al disc privat.
 */
class ImageUploadService
{
    public function storePng(UploadedFile $file, string $directory, string $field = 'logo', int $maxSize = 1024): string
    {
        try {
            $image = (new ImageManager(new Driver))->read($file->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('messages.validation.logo_unreadable')]);
        }

        $image->scaleDown(width: $maxSize, height: $maxSize);
        $path = trim($directory, '/').'/'.Str::ulid()->toBase32().'.png';

        Storage::disk('local')->put($path, (string) $image->toPng());

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
    }
}
