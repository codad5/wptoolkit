<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Http\WpHttpClient;
use Codad5\WPToolkit\Contracts\Http\HttpRequest;
use Codad5\WPToolkit\Exceptions\HttpException;
use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * WpHttpClient through WordPress's real HTTP API (answered by pre_http_request), and the
 * application lifecycle on a real WordPress.
 */
final class HttpAndApplicationOnWordPressTest extends TestCase
{
    /** @var callable|null */
    private $filter = null;

    protected function tearDown(): void
    {
        if ($this->filter !== null) {
            remove_filter('pre_http_request', $this->filter);
        }
    }

    public function test_requests_go_through_wordpress_http_api(): void
    {
        $seen = null;
        $this->answer(function ($preempt, array $args, string $url) use (&$seen) {
            $seen = ['url' => $url, 'method' => $args['method'], 'body' => $args['body'] ?? null, 'type' => $args['headers']['content-type'] ?? null];
            return ['headers' => ['x-request-id' => 'abc'], 'body' => '{"ok":true}', 'response' => ['code' => 201, 'message' => 'Created'], 'cookies' => []];
        });

        $response = (new WpHttpClient())->send(HttpRequest::post('https://api.example.test/items', ['name' => 'Ada']));

        self::assertSame(201, $response->status);
        self::assertSame(['ok' => true], $response->json());
        self::assertSame('abc', $response->header('X-Request-Id'));
        self::assertSame(['url' => 'https://api.example.test/items', 'method' => 'POST', 'body' => '{"name":"Ada"}', 'type' => 'application/json'], $seen);
    }

    public function test_a_wordpress_transport_error_becomes_an_http_exception(): void
    {
        $this->answer(fn () => new \WP_Error('http_request_failed', 'cURL error 6: Could not resolve host'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Could not resolve host');

        (new WpHttpClient())->send(HttpRequest::get('https://unreachable.example.test'));
    }

    public function test_an_application_boots_and_shuts_down_on_real_wordpress(): void
    {
        $app = Application::create(__FILE__, ['slug' => 'wptoolkit-it-app', 'contain_hook_errors' => false])
            ->providers([ItHookProvider::class])
            ->boot();

        self::assertTrue($app->isBooted(), 'init already ran, so providers boot immediately');
        self::assertNotFalse(has_filter('the_title', [ItHookProvider::class, 'shout']));
        self::assertSame('HELLO', apply_filters('the_title', 'hello'));

        $app->deactivate();

        self::assertFalse(has_filter('the_title', [ItHookProvider::class, 'shout']));
        self::assertSame('hello', apply_filters('the_title', 'hello'));
    }

    private function answer(callable $answer): void
    {
        $this->filter = static fn ($preempt, $args, $url) => $answer($preempt, $args, $url);
        add_filter('pre_http_request', $this->filter, 10, 3);
    }
}

/**
 * @internal Test provider for HttpAndApplicationOnWordPressTest.
 */
final class ItHookProvider extends ServiceProvider
{
    public function boot(HookRegistrar $hooks): void
    {
        $hooks->addFilter('the_title', [self::class, 'shout']);
    }

    public static function shout(string $title): string
    {
        return strtoupper($title);
    }
}
