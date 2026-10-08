<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Http;

use Brain\Monkey\Functions;
use Closure;
use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Contracts\Http\Middleware;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\HttpError;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Response;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;
use Codad5\WPToolkit\Tests\TestCase;
use RuntimeException;

/**
 * Phase 3: the one pipeline both transports use. The tests named in Phase 3 §3.8 carry the 0.x
 * security findings (S3, S4, C2) into 1.0.
 */
final class DispatcherTest extends TestCase
{
    private ArrayLogger $logger;

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new ArrayLogger();
        $this->container = new Container();
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\stubs([
            'sanitize_email' => static fn ($v) => (string) $v,
            'esc_url_raw' => static fn ($v) => (string) $v,
            'sanitize_textarea_field' => static fn ($v) => (string) $v,
            'wp_kses_post' => static fn ($v) => (string) $v,
            'sanitize_title' => static fn ($v) => (string) $v,
        ]);
    }

    // --- §3.8 security acceptance scenarios --------------------------------------------------

    public function test_route_without_access_rule_is_denied(): void
    {
        $route = new Route(['GET'], 'secrets', fn () => ['api_key' => 'leaked']);

        $response = $this->dispatch($route, $this->request(userId: 1));

        self::assertSame(403, $response->status);
        self::assertStringNotContainsString('leaked', (string) json_encode($response->data));
        self::assertStringContainsString('has no access rule', $this->logger->messages()[0]);
    }

    public function test_anonymous_user_cannot_call_a_logged_in_route(): void
    {
        $route = (new Route(['GET'], 'me', fn () => 'private'))->loggedIn();

        $response = $this->dispatch($route, $this->request());

        self::assertSame(401, $response->status);
        self::assertSame('unauthenticated', $response->data['code']);
    }

    public function test_exception_message_never_reaches_the_client(): void
    {
        $route = (new Route(['GET'], 'boom', function () {
            throw new RuntimeException('SQLSTATE[42S02]: wp_secret_table missing');
        }))->public();

        foreach (['rest', 'ajax'] as $transport) {
            $response = $this->dispatch($route, $this->request(transport: $transport));
            $body = (string) json_encode($response->data);

            self::assertSame(500, $response->status, $transport);
            self::assertStringNotContainsString('SQLSTATE', $body, $transport);
            self::assertStringNotContainsString('wp_secret_table', $body, $transport);
            self::assertArrayHasKey('request_id', $response->data['details'], $transport);
        }
        self::assertStringContainsString('wp_secret_table', $this->logger->messages()[0], 'the detail is logged instead');
    }

    public function test_error_responses_carry_the_real_http_status(): void
    {
        $route = (new Route(['GET'], 'books/{id}', function () {
            throw HttpError::notFound();
        }))->public();

        foreach (['rest', 'ajax'] as $transport) {
            self::assertSame(404, $this->dispatch($route, $this->request(transport: $transport))->status, $transport);
        }
    }

    // --- Access rules -----------------------------------------------------------------------

    public function test_capability_routes_need_login_then_the_capability(): void
    {
        $route = (new Route(['GET'], 'admin', fn () => 'ok'))->can('manage_options');

        self::assertSame(401, $this->dispatch($route, $this->request())->status);
        self::assertSame(403, $this->dispatch($route, $this->request(userId: 5))->status);

        Functions\when('current_user_can')->alias(static fn (string $cap) => $cap === 'manage_options');
        self::assertSame(200, $this->dispatch($route, $this->request(userId: 5))->status);
    }

    public function test_policies_decide_with_the_request(): void
    {
        $route = (new Route(['GET'], 'posts/{id}', fn () => 'ok'))
            ->authorize(static fn (Request $r): bool => $r->userId === 7);

        self::assertSame(401, $this->dispatch($route, $this->request())->status);
        self::assertSame(403, $this->dispatch($route, $this->request(userId: 8))->status);
        self::assertSame(200, $this->dispatch($route, $this->request(userId: 7))->status);
    }

    // --- Nonces -----------------------------------------------------------------------------

    public function test_ajax_mutations_by_logged_in_users_need_a_valid_nonce(): void
    {
        $route = (new Route(['POST'], 'books', fn () => 'created'))->loggedIn();

        self::assertSame(403, $this->dispatch($route, $this->request('POST', transport: 'ajax', userId: 3))->status);

        Functions\when('wp_verify_nonce')->alias(static fn ($nonce, $action) => $nonce === 'good' && $action === 'my-plugin:books' ? 1 : false);
        $response = $this->dispatch($route, $this->request('POST', ['_wpnonce' => 'good'], transport: 'ajax', userId: 3));

        self::assertSame(200, $response->status);
    }

    public function test_rest_relies_on_cores_own_nonce_check(): void
    {
        $route = (new Route(['POST'], 'books', fn () => 'created'))->loggedIn();

        self::assertSame(200, $this->dispatch($route, $this->request('POST', transport: 'rest', userId: 3))->status);
    }

    // --- Validation -------------------------------------------------------------------------

    public function test_invalid_input_is_a_422_with_messages_per_field(): void
    {
        $route = (new Route(['POST'], 'signup', fn () => 'ok'))->public()->args([
            'email' => ['rules' => 'required|email'],
            'age' => ['rules' => 'integer|min:18', 'type' => 'int'],
        ]);

        $response = $this->dispatch($route, $this->request('POST', ['email' => 'nope', 'age' => '12']));

        self::assertSame(422, $response->status);
        self::assertSame('validation_failed', $response->data['code']);
        self::assertArrayHasKey('email', $response->data['details']['fields']);
        self::assertArrayHasKey('age', $response->data['details']['fields']);
    }

    public function test_only_declared_input_reaches_the_controller_sanitized(): void
    {
        $seen = null;
        $route = (new Route(['POST'], 'profile', function (Request $request) use (&$seen) {
            $seen = $request->validated();
            return 'ok';
        }))->public()->args([
            'name' => ['rules' => 'required|max:50'],
            'age' => ['rules' => 'integer', 'type' => 'int'],
        ]);

        $this->dispatch($route, $this->request('POST', ['name' => ' <b>Ada</b> ', 'age' => '36', 'role' => 'administrator']));

        self::assertSame(['name' => 'Ada', 'age' => 36], $seen);
    }

    // --- Rate limiting ----------------------------------------------------------------------

    public function test_rate_limited_route_returns_429_with_retry_after(): void
    {
        $route = (new Route(['POST'], 'contact', fn () => 'sent'))->public()->rateLimit(1, perSeconds: 60);
        $dispatcher = $this->dispatcher(new RateLimiter(new ArrayStore(new FrozenClock()), new FrozenClock()));

        $first = $dispatcher->dispatch($route, $this->request('POST'));
        $second = $dispatcher->dispatch($route, $this->request('POST'));

        self::assertSame(200, $first->status);
        self::assertSame('1', $first->headers['X-RateLimit-Limit']);
        self::assertSame(429, $second->status);
        self::assertSame('60', $second->headers['Retry-After']);
    }

    // --- Controllers and middleware ---------------------------------------------------------

    public function test_controller_classes_come_from_the_container_with_the_request_injected(): void
    {
        $route = (new Route(['GET'], 'echo', [EchoController::class, 'show']))->public()->args(['q' => []]);

        $response = $this->dispatch($route, $this->request(query: ['q' => 'hello']));

        self::assertSame(['echo' => 'hello'], $response->data);
    }

    public function test_return_values_become_responses(): void
    {
        self::assertSame(204, $this->dispatch((new Route(['GET'], 'a', fn () => null))->public(), $this->request())->status);
        self::assertSame(201, $this->dispatch((new Route(['GET'], 'b', fn () => Response::created(['id' => 1])))->public(), $this->request())->status);
        self::assertSame([1, 2], $this->dispatch((new Route(['GET'], 'c', fn () => [1, 2]))->public(), $this->request())->data);
    }

    public function test_middleware_runs_in_order_and_can_stop_the_request(): void
    {
        $trace = [];
        $tag = static function (string $name) use (&$trace): Middleware {
            return new class ($name, $trace) implements Middleware {
            /** @param list<string> $trace */
            public function __construct(private string $name, private array &$trace)
            {
            }

            public function handle(Request $request, Closure $next): Response
            {
                $this->trace[] = $this->name;
                return $this->name === 'stop' ? Response::json('stopped', 418) : $next($request);
            }
            };
        };

        $route = (new Route(['GET'], 'm', function () use (&$trace) {
            $trace[] = 'controller';
            return 'ok';
        }))->public()->middleware($tag('first'), $tag('second'));
        $this->dispatch($route, $this->request());
        self::assertSame(['first', 'second', 'controller'], $trace);

        $trace = [];
        $stopped = (new Route(['GET'], 'n', fn () => 'never'))->public()->middleware($tag('stop'));
        self::assertSame(418, $this->dispatch($stopped, $this->request())->status);
        self::assertSame(['stop'], $trace);
    }

    public function test_administrators_in_development_see_the_exception_class(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $route = (new Route(['GET'], 'boom', function () {
            throw new RuntimeException('detail');
        }))->public();

        $response = $this->dispatcher(null, development: true)->dispatch($route, $this->request(userId: 1));

        self::assertSame('RuntimeException: detail', $response->data['details']['exception']);
    }

    private function dispatch(Route $route, Request $request): Response
    {
        return $this->dispatcher()->dispatch($route, $request);
    }

    private function dispatcher(?RateLimiter $limiter = null, bool $development = false): Dispatcher
    {
        return new Dispatcher($this->container, new Identity('my-plugin'), $this->logger, $limiter, $development);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $query
     */
    private function request(string $method = 'GET', array $body = [], string $transport = 'rest', int $userId = 0, array $query = []): Request
    {
        return new Request($method, $transport, [], $query, $body, [], [], $userId, '203.0.113.9');
    }
}

final class EchoController
{
    public function show(Request $request): array
    {
        return ['echo' => $request->input('q')];
    }
}
