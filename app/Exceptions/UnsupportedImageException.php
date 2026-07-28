<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by BinaryUploadService when a file that announced itself as a HEIF-family
 * image cannot be decoded — a truncated upload, a codec this ImageMagick has no
 * delegate for, or a coder the security policy refuses.
 *
 * Maps to 415, not 5xx, on purpose: the client's HTTP layer retries 5xx with
 * exponential backoff, so a permanently undecodable file returned as a server error
 * produces a retry storm that never converges.
 */
class UnsupportedImageException extends RuntimeException {}
