<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by ChapterGenerator when generation hits a TRANSIENT failure that is
 * worth retrying on the queue (an Anthropic 5xx/429/408/529, a connection
 * failure, or a max_tokens-truncated response). Non-retryable outcomes — a
 * refusal, a permanent 4xx, or malformed JSON that a retry won't fix — return
 * null instead, so the job treats them as "nothing to generate" and does not
 * retry.
 */
class ChapterGenerationException extends RuntimeException {}
