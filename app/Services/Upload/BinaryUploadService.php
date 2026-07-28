<?php

namespace App\Services\Upload;

use App\Exceptions\BinaryStorageException;
use App\Exceptions\UnsupportedImageException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class BinaryUploadService
{
    private const DISK = 's3';

    private const JPEG_QUALITY = 85;

    /** Declared types that claim to be a HEIF-family still image. */
    private const HEIF_MIMES = ['image/heic', 'image/heif'];

    /**
     * ISOBMFF brands that mean "HEIF-family still image", read from the file's own
     * `ftyp` box.
     *
     * The brand is the authority here rather than the client's Content-Type (a claim
     * anyone can edit) or finfo (whose HEIC support depends on the host's libmagic:
     * it recognises HEIC on Ubuntu 24.04 and not necessarily on 22.04, which would
     * make behaviour differ between local and production).
     *
     * The list must stay limited to still-image brands. Voice notes are ISOBMFF too —
     * an `.m4a` also carries an `ftyp` box, with brands like `M4A `/`mp42`/`isom` — so
     * a looser match would send audio to the JPEG encoder.
     */
    private const HEIF_BRANDS = ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx', 'mif1', 'msf1'];

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
        if ($this->isHeif($file)) {
            return $this->storeAsJpeg($kind, $userId, $entityId, $file);
        }

        $ext = self::EXTENSIONS[$this->extensionMime($file)] ?? 'bin';
        $path = "{$kind}/{$userId}/{$entityId}.{$ext}";

        // Visibility is deliberately not passed: the bucket is Cloudflare R2, which has
        // no object-level ACLs — public reads are granted by the bucket's public domain.
        // Sending `public` would make Flysystem attach an ACL header R2 does not honour.
        $stored = Storage::disk(self::DISK)->putFileAs("{$kind}/{$userId}", $file, "{$entityId}.{$ext}");

        if ($stored === false) {
            throw new BinaryStorageException("Failed to write {$path} to the ".self::DISK.' disk.');
        }

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Re-encode a HEIF-family image to JPEG. EXIF orientation is applied and then all
     * metadata is dropped (no GPS, no device info leaked).
     */
    private function storeAsJpeg(string $kind, string $userId, string $entityId, UploadedFile $file): string
    {
        $bytes = (string) file_get_contents($file->getPathname());

        try {
            $manager = new ImageManager(new ImagickDriver);
            $image = $manager->decodeBinary($bytes);
            $image = $image->orient(); // applies EXIF rotation, leaves canvas upright

            // JpegEncoder strips ICC/EXIF metadata by default in Intervention v4.
            $jpegBytes = (string) $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));
        } catch (Throwable $e) {
            /*
             * Intervention flattens every cause into "unsupported image format": a
             * missing imagick extension, an ImageMagick policy that denies the HEIC
             * coder, libheif without an HEVC decoder, and a truncated body all arrive
             * here with the same sentence. The underlying message is the only thing
             * that distinguishes them, so it is carried through rather than swallowed.
             */
            throw new UnsupportedImageException($e->getMessage(), previous: $e);
        }

        $path = "{$kind}/{$userId}/{$entityId}.jpg";

        if (Storage::disk(self::DISK)->put($path, $jpegBytes, ['ContentType' => 'image/jpeg']) === false) {
            throw new BinaryStorageException("Failed to write {$path} to the ".self::DISK.' disk.');
        }

        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Whether the bytes really are a HEIF-family still image.
     *
     * Layout of the leading `ftyp` box: a big-endian length, the literal `ftyp`, the
     * major brand, a minor version, then the compatible-brands list — four bytes each.
     */
    private function isHeif(UploadedFile $file): bool
    {
        $head = (string) file_get_contents($file->getPathname(), false, null, 0, 64);

        if (strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp') {
            return false;
        }

        $boxSize = min((int) unpack('N', substr($head, 0, 4))[1], strlen($head));
        $brands = [substr($head, 8, 4)];

        for ($offset = 16; $offset + 4 <= $boxSize; $offset += 4) {
            $brands[] = substr($head, $offset, 4);
        }

        return array_intersect($brands, self::HEIF_BRANDS) !== [];
    }

    /**
     * The type used to pick the stored extension — a cosmetic choice, never a
     * processing decision.
     *
     * A file that claims HEIC while its bytes say otherwise has already been routed to
     * the direct path by `isHeif()`. Storing it as `.heic` anyway would leave an object
     * no client can render, so the sniffed type wins for naming in that one case.
     */
    private function extensionMime(UploadedFile $file): string
    {
        $declared = $file->getClientMimeType();

        if (in_array($declared, self::HEIF_MIMES, true)) {
            return $file->getMimeType() ?? $declared;
        }

        return $declared;
    }
}
