<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Http\Client;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Http\FakeHttpClient;
use Codad5\WPToolkit\Adapters\Http\WpHttpClient;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Exceptions\HttpException;
use Codad5\WPToolkit\Http\Client\ApiClient;
use Codad5\WPToolkit\Http\Client\Auth;
use Codad5\WPToolkit\Tests\TestCase;

final class ApiClientTest extends TestCase
{
    private FakeHttpClient $http;

    /** @var list<float> */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttpClient();
    }

    public function test_joins_the_base_url_and_sends_auth_and_json(): void
    {
        $this->http->respond(201, ['id' => 7]);

        $response = $this->client(auth: Auth::bearer('secret-token'))->post('/member', ['name' => 'Ada']);

        $sent = $this->http->lastRequest();
        self::assertSame('https://api.example.com/v1/member', $sent?->url);
        self::assertSame('Bearer secret-token', $sent->header('authorization'));
        self::assertSame('{"name":"Ada"}', $sent->encodedBody());
        self::assertSame('application/json', $sent->sentHeaders()['content-type']);
        self::assertSame(['id' => 7], $response->json());
    }

    public function test_retries_server_errors_with_exponential_backoff_then_succeeds(): void
    {
        $this->http->respond(503)->respond(502)->respond(200, ['ok' => true]);

        $response = $this->client()->get('status');

        self::assertSame(200, $response->status);
        self::assertCount(3, $this->http->sent);
        self::assertSame([0.5, 1.0], $this->sleeps);
    }

    public function test_honours_retry_after_but_caps_it(): void
    {
        $this->http->respond(429, '', ['Retry-After' => '3'])->respond(429, '', ['Retry-After' => '9999'])->respond(200);

        $this->client()->get('status');

        self::assertSame([3.0, 30.0], $this->sleeps);
    }

    public function test_gives_up_after_max_retries_and_returns_the_last_response(): void
    {
        $this->http->respond(500)->respond(500)->respond(500);

        $response = $this->client()->get('status');

        self::assertSame(500, $response->status);
        self::assertCount(3, $this->http->sent);
    }

    public function test_client_errors_are_never_retried(): void
    {
        $this->http->respond(404);

        self::assertSame(404, $this->client()->get('missing')->status);
        self::assertCount(1, $this->http->sent);
    }

    public function test_transport_failures_are_retried_then_thrown(): void
    {
        $this->http->failWith('timeout')->failWith('timeout')->failWith('timeout');
        $logger = new ArrayLogger();

        try {
            $this->client(logger: $logger)->get('status');
            self::fail('expected an HttpException');
        } catch (HttpException $error) {
            self::assertTrue($error->isTransportError());
        }

        self::assertCount(3, $this->http->sent);
        self::assertStringContainsString('failed after 3 attempt(s)', $logger->messages()[0]);
    }

    public function test_cached_get_is_sent_once(): void
    {
        $this->http->respond(200, ['n' => 1]);
        $client = $this->client(cache: new ArrayStore(new FrozenClock()));

        $first = $client->get('items', ['page' => 1], cacheTtl: 60);
        $second = $client->get('items', ['page' => 1], cacheTtl: 60);

        self::assertCount(1, $this->http->sent);
        self::assertSame($first->body, $second->body);
    }

    public function test_failed_responses_are_not_cached(): void
    {
        $this->http->respond(500)->respond(500)->respond(500)->respond(200);
        $client = $this->client(cache: new ArrayStore(new FrozenClock()));

        $client->get('items', [], cacheTtl: 60);
        $client->get('items', [], cacheTtl: 60);

        self::assertCount(4, $this->http->sent);
    }

    public function test_get_json_throws_on_an_error_status(): void
    {
        $this->http->respond(403, ['message' => 'no']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('returned HTTP 403');

        $this->client()->getJson('private');
    }

    public function test_logs_never_contain_the_auth_header_or_query_string(): void
    {
        $this->http->respond(200);
        $logger = new ArrayLogger();

        $this->client(auth: Auth::bearer('secret-token'), logger: $logger)->get('items', ['api_key' => 'k']);

        $logged = json_encode($logger->records);
        self::assertStringNotContainsString('secret-token', (string) $logged);
        self::assertStringNotContainsString('api_key=k', (string) $logged);
    }

    public function test_wordpress_transport_maps_responses_and_errors(): void
    {
        Functions\expect('wp_remote_request')->once()->andReturn(['response' => ['code' => 204]]);
        Functions\when('wp_remote_retrieve_headers')->justReturn(['X-Thing' => 'a']);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(204);
        Functions\when('wp_remote_retrieve_body')->justReturn('');

        $response = (new WpHttpClient())->send(HttpRequest::get('https://example.com'));

        self::assertSame(204, $response->status);
        self::assertSame('a', $response->header('x-thing'));
    }

    public function test_wordpress_transport_error_becomes_an_exception(): void
    {
        Functions\when('wp_remote_request')->justReturn(new \WP_Error('http_request_failed', 'cURL error 28'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('cURL error 28');

        (new WpHttpClient())->send(HttpRequest::get('https://example.com?token=x'));
    }

    private function client(?Auth $auth = null, ?ArrayStore $cache = null, ?ArrayLogger $logger = null): ApiClient
    {
        return new ApiClient(
            http: $this->http,
            baseUrl: 'https://api.example.com/v1/',
            auth: $auth,
            cache: $cache ?? new \Codad5\WPToolkit\Adapters\Cache\NullStore(),
            logger: $logger ?? new ArrayLogger(),
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
            }
        );
    }
}
