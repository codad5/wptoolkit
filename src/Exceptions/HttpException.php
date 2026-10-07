<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Exceptions;

use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Contracts\Http\HttpResponse;
use RuntimeException;
use Throwable;

/**
 * An outgoing HTTP request failed: no response (transport), or — from ApiClient — an error status.
 */
final class HttpException extends RuntimeException implements WPToolkitException
{
    public function __construct(
        string $message,
        public readonly HttpRequest $request,
        public readonly ?HttpResponse $response = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $response->status ?? 0, $previous);
    }

    public static function transport(HttpRequest $request, string $reason): self
    {
        return new self(sprintf('%s %s failed: %s', $request->method, self::safeUrl($request->url), $reason), $request);
    }

    public static function status(HttpRequest $request, HttpResponse $response): self
    {
        return new self(
            sprintf('%s %s returned HTTP %d', $request->method, self::safeUrl($request->url), $response->status),
            $request,
            $response
        );
    }

    public function isTransportError(): bool
    {
        return $this->response === null;
    }

    /**
     * Drop the query string: it may carry keys or tokens.
     */
    private static function safeUrl(string $url): string
    {
        return explode('?', $url, 2)[0];
    }
}
