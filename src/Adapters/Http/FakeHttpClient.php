<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Http;

use Closure;
use Codad5\WPToolkit\Contracts\Http\HttpClient;
use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Contracts\Http\HttpResponse;
use Codad5\WPToolkit\Exceptions\HttpException;
use LogicException;

/**
 * A scripted HTTP client for tests: queue responses (or failures), or answer with a closure, and
 * inspect what was sent. Ships in src/ so consumers can test their own API integrations with it.
 */
final class FakeHttpClient implements HttpClient
{
    /** @var list<HttpResponse|string> A string is a transport failure with that reason. */
    private array $queue = [];

    /** @var list<HttpRequest> */
    public array $sent = [];

    /**
     * @param (Closure(HttpRequest): HttpResponse)|null $responder Used when the queue is empty.
     */
    public function __construct(private readonly ?Closure $responder = null)
    {
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|string $body Arrays are JSON-encoded.
     */
    public function respond(int $status = 200, array|string $body = '', array $headers = []): self
    {
        $this->queue[] = new HttpResponse(
            $status,
            array_change_key_case($headers, CASE_LOWER),
            is_array($body) ? (string) json_encode($body) : $body
        );

        return $this;
    }

    public function failWith(string $reason): self
    {
        $this->queue[] = $reason;

        return $this;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->sent[] = $request;

        $next = array_shift($this->queue);
        if (is_string($next)) {
            throw HttpException::transport($request, $next);
        }
        if ($next instanceof HttpResponse) {
            return $next;
        }
        if ($this->responder !== null) {
            return ($this->responder)($request);
        }

        throw new LogicException(sprintf('FakeHttpClient has no response queued for %s %s.', $request->method, $request->url));
    }

    public function lastRequest(): ?HttpRequest
    {
        return $this->sent[count($this->sent) - 1] ?? null;
    }
}
