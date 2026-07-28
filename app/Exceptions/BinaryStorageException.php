<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the object store accepted the call but did not write the object.
 *
 * The `s3` disk is deliberately configured with `'throw' => false` so a transient
 * outage never surfaces as an unhandled exception mid-sync. The cost is that
 * `put()`/`putFileAs()` return false instead of raising, and an unchecked call reports
 * success while storing nothing — which is how an empty bucket and a database full of
 * `remote_uri` values pointing at absent objects both went unnoticed for weeks.
 *
 * Left to bubble into a 5xx on purpose: unlike an undecodable file, a failed write is
 * worth retrying, and the client's backoff is the right mechanism for that.
 */
class BinaryStorageException extends RuntimeException {}
