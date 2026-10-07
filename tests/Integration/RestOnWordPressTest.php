<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Foundation\ServiceProvider;
use Codad5\WPToolkit\Http\HttpError;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Router;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * Phase 3 on a real WordPress: routes registered by a provider, dispatched by WordPress's own REST
 * server, with the security behaviour from §3.8.
 */
final class RestOnWordPressTest extends TestCase
{
    private static bool $booted = false;

    protected function setUp(): void
    {
        if (!self::$booted) {
            Application::create(__FILE__, ['slug' => 'wptoolkit-it-rest', 'contain_hook_errors' => false])
                ->providers([ItRoutesProvider::class])
                ->boot();
            self::$booted = true;
        }
        wp_set_current_user(0);
    }

    public function test_a_public_route_answers_with_its_data(): void
    {
        $response = $this->call('GET', '/wptoolkit-it-rest/v1/books/7');

        self::assertSame(200, $response->get_status());
        self::assertSame(['id' => 7, 'title' => 'Book 7'], $response->get_data());
    }

    public function test_route_without_access_rule_is_denied(): void
    {
        self::assertSame(403, $this->call('GET', '/wptoolkit-it-rest/v1/forgotten')->get_status());
    }

    public function test_anonymous_user_cannot_call_a_logged_in_route(): void
    {
        $response = $this->call('GET', '/wptoolkit-it-rest/v1/me');

        self::assertSame(401, $response->get_status());
        self::assertSame('unauthenticated', $response->get_data()['code']);
    }

    public function test_a_logged_in_user_can(): void
    {
        wp_set_current_user(1);

        self::assertSame(200, $this->call('GET', '/wptoolkit-it-rest/v1/me')->get_status());
    }

    public function test_exception_message_never_reaches_the_client(): void
    {
        $response = $this->call('GET', '/wptoolkit-it-rest/v1/boom');

        self::assertSame(500, $response->get_status());
        self::assertStringNotContainsString('wp_secret_table', (string) wp_json_encode($response->get_data()));
    }

    public function test_error_responses_carry_the_real_http_status(): void
    {
        self::assertSame(404, $this->call('GET', '/wptoolkit-it-rest/v1/books/999')->get_status());
    }

    public function test_validation_failure_is_a_422(): void
    {
        $response = $this->call('POST', '/wptoolkit-it-rest/v1/subscribe', ['email' => 'not-an-email']);

        self::assertSame(422, $response->get_status());
        self::assertArrayHasKey('email', $response->get_data()['details']['fields']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function call(string $method, string $route, array $body = []): \WP_REST_Response
    {
        $request = new WP_REST_Request($method, $route);
        if ($body !== []) {
            $request->set_body_params($body);
        }

        return rest_ensure_response(rest_do_request($request));
    }
}

/**
 * @internal Routes for RestOnWordPressTest.
 */
final class ItRoutesProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->get('books/{id}', static function (Request $request): array {
            $id = (int) $request->input('id');
            if ($id === 999) {
                throw HttpError::notFound();
            }
            return ['id' => $id, 'title' => 'Book ' . $id];
        })->public()->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);

        $router->get('forgotten', static fn () => ['secret' => true]);
        $router->get('me', static fn () => ['user' => get_current_user_id()])->loggedIn();
        $router->get('boom', static function (): void {
            throw new \RuntimeException('SQLSTATE: wp_secret_table missing');
        })->public();
        $router->post('subscribe', static fn () => null)->public()->args(['email' => ['rules' => 'required|email', 'type' => 'email']]);
    }
}
