<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Http;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Response;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;
use Codad5\WPToolkit\Tests\TestCase;
use WP_REST_Request;

final class RouterAndTransportsTest extends TestCase
{
    private Identity $identity;

    private Dispatcher $dispatcher;

    /** @var list<Response> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->identity = new Identity('my-plugin');
        $this->dispatcher = new Dispatcher(new Container(), $this->identity, new ArrayLogger());
        Functions\when('get_current_user_id')->justReturn(0);
    }

    protected function tearDown(): void
    {
        $_GET = $_POST = [];
        unset($_SERVER['REQUEST_METHOD']);
        parent::tearDown();
    }

    public function test_routes_convert_paths_and_names(): void
    {
        $route = new Route(['GET'], 'books/{id}/chapters/{chapter}', fn () => null);

        self::assertSame('/books/(?P<id>[^/]+)/chapters/(?P<chapter>[^/]+)', $route->restPattern());
        self::assertSame('books_id_chapters_chapter', $route->routeName());
        self::assertSame('lookup', $route->name('lookup')->routeName());
        self::assertTrue((new Route(['POST'], 'x', fn () => null))->isMutation());
    }

    public function test_unknown_transport_is_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new Route(['GET'], 'x', fn () => null))->exposeVia('graphql');
    }

    public function test_in_development_a_route_without_an_access_rule_fails_at_registration(): void
    {
        $router = $this->router(development: true);
        $router->get('open', fn () => 'oops');

        $this->expectException(LifecycleException::class);
        $this->expectExceptionMessage('has no access rule');

        $router->register();
    }

    public function test_rest_routes_register_under_the_consumer_namespace_on_rest_api_init(): void
    {
        $registered = [];
        Functions\when('register_rest_route')->alias(function (string $namespace, string $pattern, array $args) use (&$registered) {
            $registered[] = [$namespace, $pattern, $args['methods']];
        });
        $hooks = new HookRegistrar();
        $router = $this->router(hooks: $hooks);
        $router->get('books/{id}', fn () => 'book')->public();
        $router->post('books', fn () => 'created')->can('edit_posts')->version('v2');

        $router->register();
        self::assertSame([], $registered, 'nothing before rest_api_init');

        ($hooks->all()[0]['callback'])(); // WordPress fires rest_api_init

        self::assertSame([
            ['my-plugin/v1', '/books/(?P<id>[^/]+)', ['GET']],
            ['my-plugin/v2', '/books', ['POST']],
        ], $registered);
    }

    public function test_ajax_registers_logged_out_access_only_for_public_routes(): void
    {
        Actions\expectAdded('wp_ajax_my_plugin_search')->once();
        Actions\expectAdded('wp_ajax_nopriv_my_plugin_search')->once();
        Actions\expectAdded('wp_ajax_my_plugin_orders')->once();
        Actions\expectAdded('wp_ajax_nopriv_my_plugin_orders')->never();

        $router = $this->router();
        $router->get('search', fn () => [])->public()->exposeVia('ajax');
        $router->get('orders', fn () => [])->loggedIn()->exposeVia('ajax');
        $router->register();
    }

    public function test_ajax_answers_with_the_real_status_and_rejects_the_wrong_method(): void
    {
        $route = (new Route(['POST'], 'save', fn () => 'saved'))->loggedIn();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        Functions\when('wp_unslash')->returnArg();

        $this->ajax()->handle($route);
        self::assertSame(405, $this->sent[0]->status);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->ajax()->handle($route);
        self::assertSame(401, $this->sent[1]->status, 'not 200 with success:false, as 0.x did (C2)');
    }

    public function test_ajax_reads_input_from_the_request_without_the_action(): void
    {
        $route = (new Route(['POST'], 'echo', fn (\Codad5\WPToolkit\Http\Request $r) => $r->raw()))->public();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'my_plugin_echo', 'name' => 'Ada'];
        Functions\when('wp_unslash')->returnArg();

        $this->ajax()->handle($route);

        self::assertSame(['name' => 'Ada'], $this->sent[0]->data);
    }

    public function test_rest_maps_wordpress_requests_and_responses(): void
    {
        $wp = new WP_REST_Request('POST', '/my-plugin/v1/books/7');
        $wp->set_url_params(['id' => '7']);
        $wp->set_query_params(['expand' => '1']);
        $wp->set_json_params(['title' => 'Dune']);
        $wp->set_header('X-Request-Id', 'abc');
        $rest = new RestTransport($this->dispatcher, $this->identity);

        $request = $rest->fromWordPress($wp);
        self::assertSame('rest', $request->transport);
        self::assertSame(['id' => '7', 'title' => 'Dune', 'expand' => '1'], $request->raw());
        self::assertSame('abc', $request->header('x-request-id'));

        $response = $rest->toWordPress(Response::json(['ok' => true], 201, ['X-Thing' => 'y']));
        self::assertSame(201, $response->get_status());
        self::assertSame(['X-Thing' => 'y'], $response->headers);
    }

    public function test_ajax_client_config_gives_the_action_and_its_nonce(): void
    {
        Functions\when('wp_create_nonce')->alias(static fn (string $action) => 'nonce-for-' . $action);
        $route = (new Route(['POST'], 'books', fn () => null))->loggedIn();

        self::assertSame(
            ['action' => 'my_plugin_books', 'nonce' => 'nonce-for-my-plugin:books'],
            $this->ajax()->clientConfig($route)
        );
    }

    private function router(bool $development = false, ?HookRegistrar $hooks = null): Router
    {
        $hooks ??= new HookRegistrar();

        return new Router($hooks, new RestTransport($this->dispatcher, $this->identity), $this->ajax($hooks), $development);
    }

    private function ajax(?HookRegistrar $hooks = null): AjaxTransport
    {
        return new AjaxTransport($this->dispatcher, $this->identity, $hooks ?? new HookRegistrar(), send: function (Response $response): void {
            $this->sent[] = $response;
        });
    }
}
