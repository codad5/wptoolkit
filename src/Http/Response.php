<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http;

use JsonSerializable;

/**
 * An outgoing response, sent as JSON by either transport (immutable).
 *
 * Success bodies are the data itself. Error bodies always have the same shape on both transports:
 * `{ "code": "...", "message": "...", "details": {...}? }`.
 */
final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly mixed $data = null,
        public readonly int $status = 200,
        public readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self($data, $status, $headers);
    }

    public static function created(mixed $data = null): self
    {
        return new self($data, 201);
    }

    public static function noContent(): self
    {
        return new self(null, 204);
    }

    /**
     * @param array<string, mixed> $details
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $code, string $message, array $details = [], array $headers = []): self
    {
        $body = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $body['details'] = $details;
        }

        return new self($body, $status, $headers);
    }

    public static function fromError(HttpError $error): self
    {
        return self::error($error->status, $error->errorCode, $error->getMessage(), $error->details, $error->headers);
    }

    /**
     * Turn whatever a controller returned into a Response.
     */
    public static function from(mixed $result): self
    {
        if ($result instanceof self) {
            return $result;
        }
        if ($result === null) {
            return self::noContent();
        }

        return self::json($result instanceof JsonSerializable ? $result->jsonSerialize() : $result);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->data, $this->status, $headers + $this->headers);
    }

    public function isError(): bool
    {
        return $this->status >= 400;
    }
}
