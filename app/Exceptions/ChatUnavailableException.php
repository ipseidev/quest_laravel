<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an interactive AI call (chat / interviewer) cannot complete because
 * of an infrastructure failure — connection error, transient or permanent HTTP
 * error, or a truncated response. Unlike the chapter pipeline there is no queue to
 * retry, so the caller decides what to surface (the chat controller maps it to a
 * 503; the interviewer swallows it and shows no question).
 */
class ChatUnavailableException extends RuntimeException {}
