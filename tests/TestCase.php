<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case: Brain Monkey lifecycle plus the WordPress functions almost
 * every test needs.
 *
 * `wp_send_json_*` end the request in WordPress (they call `die`). Here they
 * throw a JsonResponse instead, so a test can assert what would have been sent
 * and know that nothing after it ran.
 */
abstract class TestCase extends PHPUnitTestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\stubs([
            'sanitize_key' => static fn ($key) => strtolower((string) preg_replace('/[^a-z0-9_\-]/i', '', (string) $key)),
            'sanitize_text_field' => static fn ($value) => trim(strip_tags((string) $value)),
            'absint' => static fn ($value) => abs((int) $value),
            'wp_unslash' => static fn ($value) => $value,
            'is_wp_error' => static fn ($thing) => $thing instanceof \WP_Error,
            // Matches the WP_PLUGIN_DIR layout defined in tests/bootstrap.php.
            'get_theme_root' => static fn () => ABSPATH . 'wp-content/themes',
            // Unit tests only check that output goes through it; the real filtering is WordPress's.
            'wp_kses_post' => static fn ($html) => (string) $html,
        ]);

        Functions\when('wp_send_json_success')->alias(
            static function ($data = null, $status = null): void {
                throw new JsonResponse(true, $data, $status ?? 200);
            }
        );
        Functions\when('wp_send_json_error')->alias(
            static function ($data = null, $status = null): void {
                throw new JsonResponse(false, $data, $status ?? 200);
            }
        );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Run a handler that is expected to send a JSON response, and return it.
     */
    protected function captureJson(callable $handler): JsonResponse
    {
        try {
            $handler();
        } catch (JsonResponse $response) {
            return $response;
        }

        self::fail('Expected the handler to send a JSON response, but it returned normally.');
    }
}
