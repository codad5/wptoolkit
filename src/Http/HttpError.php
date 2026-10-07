<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

use Codad5\WPToolkit\Exceptions\WPToolkitException;
use RuntimeException;
use Throwable;

/**
 * An error meant for the client: it carries the HTTP status, a stable machine-readable code and a
 * message safe to show. Anything else thrown by a controller becomes a generic 500 (ADR-0008).
 *
 * Throw these from controllers and middleware: `throw HttpError::notFound()`.
 */
class HttpError extends RuntimeException implements WPToolkitException
{
    /**
     * @param array<string, mixed> $details Extra, client-safe data (e.g. validation errors).
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $headers = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function badRequest(?string $message = null): self
    {
        return new self(400, 'bad_request', $message ?? __('The request is not valid.', 'wptoolkit'));
    }

    public static function unauthenticated(?string $message = null): self
    {
        return new self(401, 'unauthenticated', $message ?? __('You need to be logged in.', 'wptoolkit'));
    }

    public static function forbidden(?string $message = null): self
    {
        return new self(403, 'forbidden', $message ?? __('You are not allowed to do this.', 'wptoolkit'));
    }

    public static function notFound(?string $message = null): self
    {
        return new self(404, 'not_found', $message ?? __('Not found.', 'wptoolkit'));
    }

    /**
     * @param array<string, list<string>> $errors Field => messages.
     */
    public static function validation(array $errors): self
    {
        return new self(422, 'validation_failed', __('Some fields are not valid.', 'wptoolkit'), ['fields' => $errors]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function tooManyRequests(int $retryAfter, array $headers = []): self
    {
        return new self(
            429,
            'too_many_requests',
            __('Too many requests. Please try again later.', 'wptoolkit'),
            ['retry_after' => $retryAfter],
            $headers + ['Retry-After' => (string) $retryAfter]
        );
    }

    public static function internal(): self
    {
        return new self(500, 'internal_error', __('Something went wrong. Please try again.', 'wptoolkit'));
    }
}
