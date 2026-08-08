<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by BinaryUploadService when a file that announced itself as a HEIF-family
 * image cannot be decoded — a truncated upload, a codec this ImageMagick has no
 * delegate for, or a coder the security policy refuses.
 *
 * Maps to 415, not 5xx, on purpose: the client's HTTP layer retries 5xx with
 * exponential backoff, so a permanently undecodable file returned as a server error
 * produces a retry storm that never converges.
 */
class UnsupportedImageException extends RuntimeException
{
    /**
     * Identifying facts about the rejected bytes, merged into the log by the
     * renderer in bootstrap/app.php.
     *
     * Intervention flattens every cause into one sentence, so the message alone
     * cannot separate a truncated body from a missing codec — and the bytes are
     * gone by the time anyone reads the log, because nothing is stored when the
     * decode fails. A production incident stalled on exactly that: the same file
     * rejected five times, with no way to tell what it was.
     *
     * Deliberately never the bytes themselves. A failed upload is still someone's
     * private photo, so this carries only what identifies the FORMAT.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
