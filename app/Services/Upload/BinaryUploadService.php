<?php

namespace App\Services\Upload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class BinaryUploadService
{
    private const DISK = 's3';

    private const HEIC_MIMES = ['image/heic', 'image/heif'];

    private const JPEG_QUALITY = 85;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/heic' => 'heic',
        'image/heif' => 'heif',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'audio/mp4' => 'm4a',
        'audio/m4a' => 'm4a',
        'audio/aac' => 'aac',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
    ];

    public function store(string $kind, string $userId, string $entityId, UploadedFile $file): string
    {
        $mime = $file->getClientMimeType();

        if (in_array($mime, self::HEIC_MIMES, true)) {
            return $this->storeAsJpeg($kind, $userId, $entityId, $file);
        }

        $ext = self::EXTENSIONS[$mime] ?? 'bin';
        $path = "{$kind}/{$userId}/{$entityId}.{$ext}";

        Storage::disk(self::DISK)->putFileAs("{$kind}/{$userId}", $file, "{$entityId}.{$ext}", 'public');

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Re-encode HEIC/HEIF to JPEG. EXIF orientation is applied and then all
     * metadata is dropped (no GPS, no device info leaked).
     */
    private function storeAsJpeg(string $kind, string $userId, string $entityId, UploadedFile $file): string
    {
        $manager = new ImageManager(new ImagickDriver);
        $image = $manager->decodeBinary((string) file_get_contents($file->getPathname()));
        $image = $image->orient(); // applies EXIF rotation, leaves canvas upright

        // JpegEncoder strips ICC/EXIF metadata by default in Intervention v4.
        $jpegBytes = (string) $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = "{$kind}/{$userId}/{$entityId}.jpg";

        Storage::disk(self::DISK)->put(
            $path,
            $jpegBytes,
            ['visibility' => 'public', 'ContentType' => 'image/jpeg']
        );

        return Storage::disk(self::DISK)->url($path);
    }
}
