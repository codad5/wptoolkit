<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Http\Client;

use Closure;
use Codad5\WPToolkit\Adapters\Cache\NullStore;
use Codad5\WPToolkit\Adapters\Log\NullLogger;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Http\HttpClient;
use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Contracts\Http\HttpResponse;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Exceptions\HttpException;

/**
 * A client for one remote API (ports 0.x `APIHelper`): base URL, authentication, JSON, retries
 * with backoff, cached GETs and logging — over any HttpClient (ADR-0004).
 *
 * Retries: transport failures, 429 and 5xx, up to `maxRetries`, waiting 2^attempt × base delay
 * (or the server's Retry-After, capped). 4xx other than 429 is never retried.
 */
final class ApiClient
{
    private const MAX_RETRY_AFTER = 30;

    /** @var Closure(float): void */
    private readonly Closure $sleep;

    /**
     * @param array<string, string> $headers Sent with every request.
     * @param (Closure(float): void)|null $sleep How to wait between retries (seconds); injectable for tests.
     */
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $baseUrl,
        private readonly ?Auth $auth = null,
        private readonly array $headers = [],
        private readonly int $maxRetries = 2,
        private readonly float $retryDelay = 0.5,
        private readonly float $timeout = 10.0,
        private readonly CacheStore $cache = new NullStore(),
        private readonly Logger $logger = new NullLogger(),
        ?Closure $sleep = null
    ) {
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * GET a path. With `$cacheTtl`, a successful response is cached for that many seconds.
     *
     * @param array<string, scalar|null> $query
     */
    public function get(string $path, array $query = [], ?int $cacheTtl = null): HttpResponse
    {
        $request = HttpRequest::get($this->url($path), $query, $this->headers);

        if ($cacheTtl === null) {
            return $this->send($request);
        }

        $key = 'api:' . md5($request->url);
        $cached = $this->cache->get($key);
        if (is_array($cached) && isset($cached['status'], $cached['headers'], $cached['body'])) {
            return new HttpResponse((int) $cached['status'], (array) $cached['headers'], (string) $cached['body']);
        }

        $response = $this->send($request);
        if ($response->isSuccessful()) {
            $this->cache->set($key, ['status' => $response->status, 'headers' => $response->headers, 'body' => $response->body], $cacheTtl);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|string|null $body
     */
    public function post(string $path, array|string|null $body = null): HttpResponse
    {
        return $this->send(new HttpRequest('POST', $this->url($path), $this->headers, $body));
    }

    /**
     * @param array<string, mixed>|string|null $body
     */
    public function put(string $path, array|string|null $body = null): HttpResponse
    {
        return $this->send(new HttpRequest('PUT', $this->url($path), $this->headers, $body));
    }

    /**
     * @param array<string, mixed>|string|null $body
     */
    public function patch(string $path, array|string|null $body = null): HttpResponse
    {
        return $this->send(new HttpRequest('PATCH', $this->url($path), $this->headers, $body));
    }

    public function delete(string $path): HttpResponse
    {
        return $this->send(new HttpRequest('DELETE', $this->url($path), $this->headers));
    }

    /**
     * Like get(), but returns the decoded JSON and throws on a non-2xx status.
     *
     * @param array<string, scalar|null> $query
     * @throws HttpException
     */
    public function getJson(string $path, array $query = [], ?int $cacheTtl = null): mixed
    {
        $response = $this->get($path, $query, $cacheTtl);
        if (!$response->isSuccessful()) {
            throw HttpException::status(HttpRequest::get($this->url($path), $query), $response);
        }

        return $response->json();
    }

    /**
     * Send with auth, timeout, retries and logging.
     *
     * @throws HttpException When every attempt failed without a response.
     */
    public function send(HttpRequest $request): HttpResponse
    {
        $request = $request->withTimeout($this->timeout);
        if ($this->auth !== null) {
            $request = $this->auth->apply($request);
        }

        $attempt = 0;
        while (true) {
            try {
                $response = $this->http->send($request);
            } catch (HttpException $error) {
                if ($attempt >= $this->maxRetries) {
                    $this->logger->error('{method} {url} failed after {attempts} attempt(s): {reason}', [
                        'method' => $request->method,
                        'url' => $this->safeUrl($request->url),
                        'attempts' => $attempt + 1,
                        'reason' => $error->getMessage(),
                    ]);
                    throw $error;
                }
                $this->wait($attempt++, null);
                continue;
            }

            if ($this->isRetryable($response) && $attempt < $this->maxRetries) {
                $this->logger->warning('{method} {url} returned {status}; retrying', [
                    'method' => $request->method,
                    'url' => $this->safeUrl($request->url),
                    'status' => $response->status,
                ]);
                $this->wait($attempt++, $response);
                continue;
            }

            $this->logger->debug('{method} {url} → {status}', [
                'method' => $request->method,
                'url' => $this->safeUrl($request->url),
                'status' => $response->status,
                'headers' => $request->headers,
            ]);

            return $response;
        }
    }

    private function isRetryable(HttpResponse $response): bool
    {
        return $response->status === 429 || $response->status >= 500;
    }

    private function wait(int $attempt, ?HttpResponse $response): void
    {
        $retryAfter = $response?->header('retry-after');
        $seconds = $retryAfter !== null && is_numeric($retryAfter)
            ? min((float) $retryAfter, self::MAX_RETRY_AFTER)
            : $this->retryDelay * (2 ** $attempt);

        ($this->sleep)($seconds);
    }

    private function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function safeUrl(string $url): string
    {
        return explode('?', $url, 2)[0];
    }
}
